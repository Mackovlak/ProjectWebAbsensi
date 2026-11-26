package com.javag.absenkita;

import android.content.ActivityNotFoundException;
import android.content.ContentValues;
import android.content.Context;
import android.content.Intent;
import android.net.Uri;
import android.os.Build;
import android.os.Bundle;
import android.os.Environment;
import android.print.PrintAttributes;
import android.print.PrintDocumentAdapter;
import android.print.PrintManager;
import android.provider.MediaStore;
import android.util.Base64;
import android.util.Log;
import android.webkit.CookieManager;
import android.webkit.WebView;
import android.widget.Toast;

import androidx.core.content.FileProvider;
import androidx.webkit.WebViewCompat;
import androidx.webkit.WebViewFeature;

import com.getcapacitor.BridgeActivity;

import org.json.JSONObject;

import java.io.ByteArrayOutputStream;
import java.io.File;
import java.io.FileOutputStream;
import java.io.InputStream;
import java.io.OutputStream;
import java.nio.charset.StandardCharsets;
import java.util.Collections;

/**
 * Cangkang AbsenKita. Capacitor sudah menangani izin kamera (getUserMedia),
 * lokasi, dan pemilih file. Kelas ini menambahkan yang tidak ditangani
 * WebView bawaan:
 *  - native-app.js disuntikkan ke setiap halaman server (document-start),
 *  - jembatan "AbsenKitaAndroid" (hanya untuk origin server) untuk menyimpan
 *    unduhan ke Download/AbsenKita dan membuka dialog cetak/Simpan PDF,
 *  - DownloadListener untuk unduhan GET biasa,
 *  - cookie (sesi + token "Tetap masuk") ditulis ke disk saat aplikasi ke latar
 *    belakang, supaya login tidak hilang bila aplikasi lalu dimatikan sistem.
 */
public class MainActivity extends BridgeActivity {

    private static final String TAG = "AbsenKita";
    private static final int MAKS_FILE_BYTES = 50 * 1024 * 1024;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        if (getBridge() == null || getBridge().getWebView() == null) {
            return;
        }
        WebView webView = getBridge().getWebView();
        String origin = originServer();
        if (origin == null) {
            Log.w(TAG, "server.url tidak diatur; fitur unduh/cetak native dinonaktifkan");
            return;
        }

        if (WebViewFeature.isFeatureSupported(WebViewFeature.WEB_MESSAGE_LISTENER)) {
            WebViewCompat.addWebMessageListener(webView, "AbsenKitaAndroid", Collections.singleton(origin),
                (view, message, sourceOrigin, isMainFrame, replyProxy) -> {
                    if (isMainFrame && message.getData() != null) {
                        tanganiPesan(view, message.getData());
                    }
                });
        }
        if (WebViewFeature.isFeatureSupported(WebViewFeature.DOCUMENT_START_SCRIPT)) {
            String skrip = bacaAsset("public/native-app.js");
            if (skrip != null) {
                WebViewCompat.addDocumentStartJavaScript(webView, skrip, Collections.singleton(origin));
            }
        }

        // Unduhan GET (Content-Disposition: attachment) -> ambil ulang lewat
        // fetch di halaman supaya cookie sesi ikut, lalu simpan lewat jembatan.
        webView.setDownloadListener((url, userAgent, contentDisposition, mimetype, contentLength) -> {
            if (url != null && origin.equals(originDari(Uri.parse(url)))) {
                webView.evaluateJavascript(
                    "window.AbsenKitaNative && window.AbsenKitaNative.unduh(" + JSONObject.quote(url) + ")", null);
            } else if (url != null) {
                bukaLuar(Uri.parse(url));
            }
        });
    }

    @Override
    public void onPause() {
        super.onPause();
        CookieManager.getInstance().flush();
    }

    /** "https://host[:port]" dari server.url, format yang sama dengan Capacitor. */
    private String originServer() {
        String url = getBridge().getServerUrl();
        if (url == null || url.isEmpty()) {
            return null;
        }
        return originDari(Uri.parse(url));
    }

    private static String originDari(Uri u) {
        if (u.getScheme() == null || u.getHost() == null) {
            return null;
        }
        return u.getScheme() + "://" + u.getHost() + (u.getPort() != -1 ? ":" + u.getPort() : "");
    }

    private void tanganiPesan(WebView view, String json) {
        try {
            JSONObject m = new JSONObject(json);
            switch (m.optString("aksi")) {
                case "simpan":
                    simpanFile(m.optString("data"), m.optString("nama", "unduhan"), m.optString("mime", "application/octet-stream"));
                    break;
                case "cetak":
                    cetak(view, m.optString("judul", "AbsenKita"));
                    break;
                case "pesan":
                    Toast.makeText(this, m.optString("teks"), Toast.LENGTH_SHORT).show();
                    break;
                default:
                    break;
            }
        } catch (Exception e) {
            Log.e(TAG, "pesan jembatan tidak valid", e);
        }
    }

    private void cetak(WebView view, String judul) {
        PrintManager pm = (PrintManager) getSystemService(Context.PRINT_SERVICE);
        if (pm == null) {
            return;
        }
        String nama = judul.isEmpty() ? "AbsenKita" : judul;
        PrintDocumentAdapter adapter = view.createPrintDocumentAdapter(nama);
        pm.print(nama, adapter, new PrintAttributes.Builder().build());
    }

    private void simpanFile(String base64, String nama, String mime) {
        try {
            byte[] isi = Base64.decode(base64, Base64.DEFAULT);
            if (isi.length == 0 || isi.length > MAKS_FILE_BYTES) {
                throw new IllegalArgumentException("ukuran file tidak valid: " + isi.length);
            }
            nama = namaAman(nama);
            Uri uri;
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.Q) {
                // Android 10+: folder Download publik lewat MediaStore, tanpa izin penyimpanan.
                ContentValues v = new ContentValues();
                v.put(MediaStore.Downloads.DISPLAY_NAME, nama);
                v.put(MediaStore.Downloads.MIME_TYPE, mime);
                v.put(MediaStore.Downloads.RELATIVE_PATH, Environment.DIRECTORY_DOWNLOADS + "/AbsenKita");
                uri = getContentResolver().insert(MediaStore.Downloads.EXTERNAL_CONTENT_URI, v);
                if (uri == null) {
                    throw new IllegalStateException("MediaStore menolak file");
                }
                try (OutputStream os = getContentResolver().openOutputStream(uri)) {
                    os.write(isi);
                }
                Toast.makeText(this, "Tersimpan di Download/AbsenKita: " + nama, Toast.LENGTH_LONG).show();
            } else {
                // Android 7-9: folder unduhan milik aplikasi, tanpa izin penyimpanan.
                File dir = getExternalFilesDir(Environment.DIRECTORY_DOWNLOADS);
                File f = new File(dir, nama);
                try (FileOutputStream os = new FileOutputStream(f)) {
                    os.write(isi);
                }
                uri = FileProvider.getUriForFile(this, getPackageName() + ".fileprovider", f);
                Toast.makeText(this, "Tersimpan: " + nama, Toast.LENGTH_LONG).show();
            }
            // Buka langsung supaya bisa dilihat / dibagikan (mis. ke WhatsApp HRD).
            Intent buka = new Intent(Intent.ACTION_VIEW).setDataAndType(uri, mime)
                .addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION | Intent.FLAG_ACTIVITY_NEW_TASK);
            try {
                startActivity(buka);
            } catch (ActivityNotFoundException ignored) {
                // Tidak ada aplikasi untuk tipe ini; file tetap tersimpan.
            }
        } catch (Exception e) {
            Log.e(TAG, "gagal menyimpan unduhan", e);
            Toast.makeText(this, "Gagal menyimpan file.", Toast.LENGTH_LONG).show();
        }
    }

    private static String namaAman(String nama) {
        String n = nama.replaceAll("[\\\\/:*?\"<>|\\p{Cntrl}]", "_").trim();
        if (n.isEmpty() || n.startsWith(".")) {
            n = "unduhan" + n;
        }
        return n.length() > 120 ? n.substring(n.length() - 120) : n;
    }

    private void bukaLuar(Uri uri) {
        try {
            startActivity(new Intent(Intent.ACTION_VIEW, uri).addFlags(Intent.FLAG_ACTIVITY_NEW_TASK));
        } catch (ActivityNotFoundException ignored) {
            // abaikan
        }
    }

    private String bacaAsset(String path) {
        try (InputStream in = getAssets().open(path); ByteArrayOutputStream out = new ByteArrayOutputStream()) {
            byte[] buf = new byte[8192];
            int n;
            while ((n = in.read(buf)) != -1) {
                out.write(buf, 0, n);
            }
            return out.toString(StandardCharsets.UTF_8.name());
        } catch (Exception e) {
            Log.e(TAG, "asset tidak ditemukan: " + path, e);
            return null;
        }
    }
}
