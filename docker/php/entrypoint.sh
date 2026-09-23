#!/bin/sh
set -e

# config.php and security_functions.php both unconditionally
# `require assets/vendor/autoload.php` (for vlucas/phpdotenv, loading .env
# into $_ENV) - if that directory doesn't exist, every single page in the
# app fatal-errors. assets/vendor/ is gitignored, and the whole repo is
# bind-mounted over the image at runtime (see docker-compose.yml), so a
# `composer install` baked into the image at build time would just be
# shadowed by the host's checkout. Run it here instead, against the live
# bind mount, once per container start - cheap no-op if already installed
# and up to date with assets/composer.lock.
composer install \
    --working-dir=/var/www/html/assets \
    --no-interaction \
    --no-progress

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
