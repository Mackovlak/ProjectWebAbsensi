<?php
require 'config.php';

// Script ini sebaiknya dijalankan via cron job atau Task Scheduler pada pukul 18:15 WIB

// Ambil wa_token dari pengaturan admin/users
$stmt_token = $conn->prepare("SELECT wa_token FROM users WHERE wa_token IS NOT NULL AND wa_token != '' LIMIT 1");
if ($stmt_token) {
    $stmt_token->execute();
    $result_token = $stmt_token->get_result();
    $token_data = $result_token->fetch_assoc();
    $stmt_token->close();
}

if (empty($token_data['wa_token'])) {
    die("Error: Token Fonnte tidak ditemukan di pengaturan (tabel users). Silahkan atur terlebih dahulu.");
}

$wa_token = $token_data['wa_token'];

// URL dasar aplikasi
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
// Karena ini dijalankan via CLI/Cron, HTTP_HOST mungkin tidak tersedia.
$domain = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost/absensi-karyawan'; 

// Coba bentuk base url yang benar
if (isset($_SERVER['HTTP_HOST']) && isset($_SERVER['PHP_SELF'])) {
    $base_url = $protocol . "://" . $domain . dirname($_SERVER['PHP_SELF']);
} else {
    // Fallback jika dijalankan via command line
    $base_url = "http://localhost/absensi-karyawan"; 
}
// Pastikan tidak ada double slash di akhir
$base_url = rtrim($base_url, '/');

// Cari karyawan yang hari ini BELUM melakukan absensi sama sekali
$query = "SELECT id_karyawan, nama_karyawan, no_whatsapp 
          FROM karyawan 
          WHERE id_karyawan NOT IN (
              SELECT id_karyawan FROM absensi WHERE tanggal = CURDATE()
          )";

$result = $conn->query($query);

if ($result && $result->num_rows > 0) {
    $berhasil = 0;
    $gagal = 0;
    
    while ($row = $result->fetch_assoc()) {
        $no_wa = $row['no_whatsapp'];
        $nama = $row['nama_karyawan'];
        $id_k = $row['id_karyawan'];
        
        if (empty($no_wa)) {
            $gagal++;
            continue;
        }

        $link_absen = $base_url . "/absen.php?id=" . urlencode($id_k);

        $pesan = "Hallo *" . $nama . "*,\n\n"
               . "Hari ini kamu belum melakukan absensi nih,\n"
               . "sedang OFF, SAKIT, atau CUTI?, silahkan bisa tetap isi absensi pada link berikut:\n\n"
               . $link_absen . "\n\n"
               . "yuk segera di isi sekarang juga agar tidak lupa lagi ya...\n"
               . "Terimakasih.";

        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://api.fonnte.com/send',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => array(
                'target' => $no_wa,
                'message' => $pesan, 
                'countryCode' => '62',
            ),
            CURLOPT_HTTPHEADER => array(
                'Authorization: ' . $wa_token
            ),
        ));

        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);

        if ($err) {
            $gagal++;
        } else {
            $berhasil++;
        }
        
        // Jeda sebentar agar tidak membebani API (rate limit)
        sleep(1);
    }
    
    echo "Cron eksekusi selesai. Berhasil kirim: $berhasil, Gagal: $gagal.";
} else {
    echo "Semua karyawan sudah melakukan absensi hari ini. Tidak ada notifikasi yang dikirim.";
}
?>
