# Installing AbsenKita Javag

The app includes an online-only PWA manifest. Installed apps open `login.php`
in a standalone window; existing sessions still redirect to the appropriate
dashboard. Attendance, payroll, login, and other actions require internet access.
There is no service worker or offline cache.

## Files

- `manifest.webmanifest`: app name, launch URL, scope, colors, and icons.
- `pwa_head.php`: shared manifest, favicon, Apple touch icon, and mobile metadata.
  Include this inside the `<head>` of any new application page in the project root.
- `assets/images/icons/`: 192px and 512px app icons, a 512px maskable icon,
  and a 180px Apple touch icon, all derived from `assets/images/logo.png`.
  The maskable icon includes extra padding to protect the logo from cropping.

Manifest and asset URLs are relative so the PWA can be hosted at the domain
root or under a subdirectory. The launch URL deliberately excludes employee
IDs and other page-specific query parameters.

## Hosting and installation

1. Serve the app over HTTPS in production. HTTP on `localhost` is suitable for
   local testing; a plain HTTP LAN address or custom local domain is not.
2. Ensure the manifest and icons are publicly accessible without a login.
   Apache's `.htaccess` declares the `.webmanifest` MIME type. For Nginx,
   ensure its MIME types include `application/manifest+json webmanifest;`.
3. Open the app in Chrome or Edge and use the browser's install option.
   On iPhone/iPad, use Safari's Share menu and **Add to Home Screen**.
   Installation UI and availability depend on the browser and platform.

## Verification

- In browser developer tools, inspect **Application > Manifest**. Confirm the
  app name, start URL, scope, and icons load without errors.
- Install the app and launch it. Confirm login and the dashboard open within
  the installed app and that the existing camera/GPS attendance flow works.
- Disconnect the network. Server actions should remain unavailable; no offline
  attendance submissions or background synchronization are provided.
- After changing branding, regenerate the icons and reinstall the app if the
  operating system continues to show an older cached icon.
