#!/bin/sh
set -e

# The app writes uploaded photos/signatures under assets/uploads/. The
# project directory is bind-mounted from the host, so ownership won't match
# the container's www-data user — loosen permissions on the upload
# directories only (never recurse into files: this repo has pre-existing
# tracked images under assets/uploads/absensi/, and a recursive chmod flips
# their mode bits, which git then reports as modified with no content
# change). Directory rwx is all www-data needs to create new files there.
mkdir -p /var/www/html/assets/uploads/absensi
find /var/www/html/assets/uploads -type d -exec chmod 777 {} +

exec "$@"
