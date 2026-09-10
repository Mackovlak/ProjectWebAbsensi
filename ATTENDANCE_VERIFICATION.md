# Attendance verification

`verify_attendance_face.php` compares the submitted 128-number descriptor
with enrollment templates stored in the database. The attendance page no
longer embeds those templates. The score is the best Euclidean-distance
match: `max(0, min(100, (1 - distance) * 100))`. Acceptance requires at least
63% before rounding, matching the previous browser threshold (the old
attendance endpoint accepted 62%). The displayed percentage is a similarity
score, not a calibrated probability of identity.

1. After the camera starts, the page requests a CSRF-protected challenge.
2. After the existing browser liveness check, it sends the challenge,
   descriptor, and captured JPEG to the verifier.
3. A successful server match produces a random approval token. Both challenge
   and approval expire after 120 seconds and can be consumed only once.
4. `proses_absen.php` accepts only the server approval for physical attendance;
   posted `face_confidence`, `face_descriptor`, and `face_verified` cannot
   approve attendance. It saves/logs the score held in the server session.

Tokens are held in the PHP session and bound to employee ID, server date,
clock-in/out direction, current attendance row, and enrollment fingerprint.
The approval also binds the SHA-256 hash of the captured JPEG. Switching
employees, replacing the photo, resetting enrollment, changing the attendance
row, expiration, or replay causes rejection. PHP's session lock must remain
held until token consumption is persisted; do not add `session_write_close()`
before these checks. Multi-server installations need a shared, locking PHP
session handler or sticky sessions (without either, requests will fail closed).

Overtime submission performs a fresh camera verification after the user
finishes the reason/attachment form. The earlier clock-out approval is already
consumed. Cancelled, failed, or expired requests require fresh verification;
there is no fallback to a browser-provided confidence value.

No new database migration or service is required. Migration 007 still controls
photo persistence/review; matching and photo-hash binding work before 007 too.
Deploy the PHP and attendance-page changes together. Already-open attendance
pages must be reloaded. Older malformed enrollment data is rejected and needs
an administrator-approved re-registration, not a lower validation threshold.

## Remaining boundary

Descriptor extraction and liveness detection still run in the browser. This
change prevents confidence-only tampering and removes public template copying,
but it does not prove a descriptor was extracted from the uploaded photo or
that either came from a live camera. An attacker who already possesses a valid
descriptor can obtain a new challenge and submit it again. Nonces prevent
request/token replay, not reuse of biometric samples. Stronger spoof resistance
requires server-side image inference and independently verified liveness.
Templates exposed by older versions cannot be made secret retroactively.

## Checks

Run `php tests/attendance_face_test.php` and
`node tests/attendance_capture_frontend.cjs`. Integration testing should cover
forged confidence with no token, malformed/mismatching descriptors, CSRF,
expired/reused/wrong-session tokens, another employee/direction, enrollment
reset, altered photos, valid clock-in/out, and the overtime follow-up flow.
