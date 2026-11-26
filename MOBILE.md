# AbsenKita on Android & iOS

There are two ways to use AbsenKita on a phone, and both talk to the same PHP server:

| | **Native app** (`mobile/`, Capacitor) | **Installed web app** (PWA, see `PWA.md`) |
|---|---|---|
| Android | ✅ APK / Play Store | ✅ Chrome → *Install app* |
| iPhone | Needs a paid Apple Developer account + a Mac or cloud Mac (see §6) | ✅ **Free**: Safari → Share → *Add to Home Screen* |
| Login kept | 30 days ("Tetap masuk"), stored in the app's private cookie store | 30 days ("Tetap masuk"), in that browser/app |
| Camera / GPS permission | OS-level, asked once per app, managed in phone Settings → Apps → AbsenKita | Per site, inside the browser; easy to block by accident |
| Push notifications | **Not yet** (needs Firebase, see §7) | ✅ Web Push |
| CSV/XLSX export, Print/PDF | ✅ Saved to `Download/AbsenKita`, Android print dialog | ✅ Browser handles it |

**The app is only a shell.** It contains no PHP and no copy of the pages: its WebView opens the
production URL directly. So deploying PHP changes needs **no** new app release. A new app release is
only needed when something under `mobile/` changes (permissions, icons, plugins, `native-app.js`,
`MainActivity.java`).

## 1. Login that survives on phones (server side, applies to browser, PWA and app)

The old setup logged phones out after **1 hour** (session cookie `lifetime => 3600`), with no way to
stay signed in. Also, production used `SameSite=Strict`, so opening the app from a WhatsApp/SMS link
arrived without the session cookie and looked logged out.

Now:
- Login has a **"Tetap masuk di perangkat ini (30 hari)"** checkbox. It is pre-ticked inside the native
  app and the installed PWA (personal devices). In a plain browser it starts unticked, for shared PCs.
- Ticked → a 30-day `absenkita_ingat` cookie (`HttpOnly`, `Secure` on HTTPS, `SameSite=Lax`).
  `config.php` restores the session from it whenever the PHP session has expired
  (`remember_functions.php`). The same account checks as `login.php` apply on every restore
  (account active + employee active), and role/name are re-read from the DB.
- The DB stores only a SHA-256 of the secret (`user_remember_tokens`, migration `019`). A tampered
  cookie deletes its token. The expiry slides forward at most once a day while the device keeps using it.
- Tokens are revoked on: logout (this device), own password change (all *other* devices), admin
  resetting the password or deactivating the account (all devices), account deletion (FK cascade).
  Max 10 devices per account (oldest dropped).
- Session cookies are now `SameSite=Lax` in production too. Cross-site POSTs still don't carry the
  cookie, and every form keeps its CSRF token.

**Deploy:** run migration 019 (`php migrate.php status`, then `php migrate.php migrate --yes`, see
`MIGRATIONS.md`). Until it has run, the checkbox simply has no effect and nothing breaks.

Test: `docker compose exec web php tests/remember_token_test.php`.

## 2. Camera & location permission help (server side)

`assets/js/izin-perangkat.js` (loaded by `pwa_head.php`):
- `AbsenIzin.ambilLokasi()`: inside the native app it uses the `@capacitor/geolocation` plugin (one OS
  permission, no extra per-site prompt on iOS); elsewhere it uses `navigator.geolocation`.
- When the camera or location is blocked, the page shows **platform-specific steps** to re-enable it
  (Android app, iPhone app, iPhone Safari/PWA, Android Chrome, desktop) instead of a dead end.
  `absen.php` shows a *Coba lagi* button and retries automatically when the user comes back from Settings.
- Camera errors are told apart: denied vs. no camera vs. camera busy in another app vs. not HTTPS.

## 3. Prerequisites for building the app

- Node.js 20+ and npm.
- **JDK 21** (Capacitor 8). On Manjaro: `sudo archlinux-java set java-21-openjdk`, or prefix
  commands with `JAVA_HOME=/usr/lib/jvm/java-21-openjdk`.
- Android SDK (Android Studio, or the existing `~/Android/Sdk`). Gradle downloads platform 36 itself
  the first time, as long as the SDK licences are accepted.
- iOS only: a Mac with Xcode 26+, or a cloud macOS runner (§6).

```bash
cd mobile
npm install
```

`ABSENKITA_URL` (**required**) is the server the app opens. It is read on every `cap sync`/`cap copy`
and baked into the native projects, so **always sync with the URL of the environment you're building
for.** `ABSENKITA_APP_ID` (optional, default `com.javag.absenkita`) is the permanent package ID on
Play Store / App Store. Pick it once, before the first public release. Changing it later means a new app
listing, and the Java package folder under `android/app/src/main/java/` has to be renamed to match.

## 4. Android

### Try it against local Docker

Camera access requires a secure context. In an Android WebView `http://10.0.2.2` is **not** one, and
`localhost` collides with Capacitor's internal host. Use `127.0.0.1` through `adb reverse`, which works
for both the emulator and a USB-connected phone:

```bash
adb reverse tcp:8080 tcp:8080
ABSENKITA_URL=http://127.0.0.1:8080/ npm run build:android
adb install -r android/app/build/outputs/apk/debug/app-debug.apk
```

Debug builds can be inspected from desktop Chrome at `chrome://inspect`.
`npm run android` opens the project in Android Studio instead.

### Production build

1. Create the signing key **once** and back it up somewhere safe (password manager or company
   vault). If it is lost, installed apps can never be updated again.
   ```bash
   keytool -genkeypair -v -keystore absenkita-release.jks -alias absenkita \
     -keyalg RSA -keysize 2048 -validity 10000
   ```
   Keep it outside the repo (`*.jks` is gitignored anyway).
2. Point the release build at it. The simplest way is Android Studio → *Build → Generate Signed
   App Bundle / APK*. For the command line, add a `signingConfigs.release` block to
   `android/app/build.gradle` that reads from an untracked `keystore.properties`.
3. Bump `versionCode` (must increase on every release) and `versionName` in `android/app/build.gradle`.
4. `ABSENKITA_URL=https://absen.your-domain.co.id/ npm run build:android:release`
   → `.aab` (Play Store) and `.apk` (direct install) under `android/app/build/outputs/`.

### Distributing

- **Direct APK (free):** share the `.apk` (company site, WhatsApp, Drive). Users must allow
  *Install unknown apps* for the app they open it from. Updates are manual: send the new APK, signed
  with the **same key**.
- **Google Play (US$25 one-time):** upload the `.aab`. Use the *Internal testing* or *Closed testing*
  track to limit it to employees. Updates arrive automatically.
- The server must be on HTTPS with a valid certificate (same requirement as the PWA).

## 5. What the native shell adds (`mobile/`)

| File | Purpose |
|---|---|
| `capacitor.config.ts` | Server URL, app ID, `AbsenKitaApp/1.0` User-Agent marker, offline page. |
| `www/offline.html` | Shown when the server can't be reached; retries when the connection is back. |
| `www/native-app.js` | Injected by Android into **every** server page before page scripts run: saves POST exports (`export_*.php` → `.xlsx`) and `<a download>` data:/blob: files, routes `window.print()` to the Android print/Save-as-PDF dialog, and keeps same-site `window.open`/`target=_blank` inside the app (an external browser has no login). |
| `android/.../MainActivity.java` | Registers that script and an origin-restricted `AbsenKitaAndroid` message bridge (`WebViewCompat.addWebMessageListener`), saves files via MediaStore (no storage permission), flushes cookies to disk on pause so the login survives the app being killed. |
| `android/.../AndroidManifest.xml` | Camera + fine/coarse location permissions; `allowBackup="false"` so Android's cloud backup can't copy the login cookie onto another phone. |
| `ios/App/App/Info.plist` | Camera / location / photo usage texts (Apple rejects the app without them). |
| `assets/logo.png` | Source for `npm run icons` (regenerates all Android/iOS icons + splash). |

The User-Agent marker `AbsenKitaApp/` is what `isAplikasiNative()` (PHP), `pwa.js` and
`izin-perangkat.js` check. Don't change it in one place only.

In the app, `pwa.js` does not register the service worker, and the notification card says push isn't
available in the app yet. WebViews don't support Web Push.

## 6. iPhone: options and costs

**There is no free way to distribute a native iOS app that keeps working.** Apple requires every
installed app to be signed:

| Option | Cost | Catch |
|---|---|---|
| **PWA: Safari → Share → Add to Home Screen** | Free | Runs in WebKit, so camera/location permission is per-site and iOS may ask again. With §1 the login now stays for 30 days, and §2 shows users how to fix permissions. Web Push works on iOS 16.4+. **Recommended for iPhone users for now.** |
| Free Apple ID + Xcode, install by cable | Free, needs a Mac | The app **stops opening after 7 days** and must be re-installed from the Mac. Only practical for testing. |
| Apple Developer Program + TestFlight | US$99/year, needs a Mac or cloud Mac | Up to 10,000 testers by email/link, each build expires after 90 days. Fine for an internal company app. |
| Apple Developer Program + App Store (public or *Unlisted*) / Apple Business Manager custom app | US$99/year | App Review applies. A pure website wrapper can be rejected under guideline 4.2 ("minimum functionality"); native location, camera, offline screen and (later) native push help. |

No Mac: a GitHub Actions `macos` runner or Codemagic (free tiers exist) can build and upload to
TestFlight, but you still need the paid Apple account for signing.

When you get there:
```bash
cd mobile
ABSENKITA_URL=https://absen.your-domain.co.id/ npx cap sync ios
npx cap open ios   # on the Mac: set the Team under Signing & Capabilities, then Product → Archive
```
Not done for iOS yet (Android-only for now): the download/print bridge from §5. On iOS,
`native-app.js` is not injected, so exports and print behave like plain WKWebView: the `.xlsx` export
may not save. Login, camera, location and the rest of the app work.

## 7. Push notifications inside the app (not done yet)

Browsers deliver Web Push through the browser vendor's push service. That's what `push_functions.php`
uses today with your VAPID keys. Native apps can't receive Web Push. On Android, push goes through
**Firebase Cloud Messaging (FCM)**; on iOS through **Apple Push Notification service (APNs)**, which FCM
can also forward to.

Firebase is a Google service (free for push) that you sign into with a Google account. It is not a
library inside this repo. Adding it means:
1. Create a Firebase project at <https://console.firebase.google.com>, add an Android app with the same
   package ID, download `google-services.json` into `mobile/android/app/`.
2. Add the `@capacitor/push-notifications` plugin to the app; on login it registers and sends its device
   token to the server.
3. Server: store those tokens next to `push_subscriptions` and send through the FCM HTTP v1 API from
   `push_functions.php`, using a service-account key kept in `.env` (never in git). The existing
   `pushAntre*()` calls stay the same.
4. iOS additionally needs an APNs key, which requires the paid Apple Developer account.

Until then, Android app users get no push notifications. Users who need them can keep using the
browser/PWA, which still receives Web Push.

## 8. Troubleshooting

- **"Still asks me to log in every time"**: the checkbox wasn't ticked, migration 019 hasn't run, or
  the password was changed/reset (that intentionally signs out other devices).
- **Location "denied" in the app**: Settings → Apps → AbsenKita → Permissions → Location →
  *Allow only while using the app* **and** *Use precise location* on. Approximate location alone is
  rejected, because the branch radius needs precise GPS.
- **Blank screen / offline page on first launch**: the device can't reach `ABSENKITA_URL`, or it's
  HTTPS with an invalid certificate.
- **Changed `capacitor.config.ts` or `www/`, nothing happened**: re-run `npx cap sync` with
  `ABSENKITA_URL` set, then rebuild.
