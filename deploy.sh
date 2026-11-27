#!/bin/sh
# Pull the repo and deploy app files into public_html. Mirrors the tasks
# in .cpanel.yml (used by cPanel's "Pull or Deploy") — keep them in sync.
# Runs from deploy-hook.php on each GitHub push to master; also safe to
# run manually over SSH/cPanel Terminal.
cd "$(dirname "$0")" || exit 1

# Serialize concurrent deploys (hook queues one per push).
exec 9>/home/nipangec/.deploy-hook.lock
flock 9 || exit 1

git fetch origin && git reset --hard origin/master || exit 1

# reset --hard may have replaced this script itself; re-exec so the rest of
# the deploy (copy, rm, migrations) always runs the version just pulled.
if [ -z "$DEPLOY_REEXEC" ]; then export DEPLOY_REEXEC=1; exec /bin/sh "$0"; fi

DEPLOYPATH=/home/nipangec/public_html/
cp -f *.php $DEPLOYPATH
cp -f manifest.webmanifest sw.js offline.html google*.html $DEPLOYPATH
mkdir -p $DEPLOYPATH/includes $DEPLOYPATH/api $DEPLOYPATH/admin $DEPLOYPATH/assets $DEPLOYPATH/assets/icons $DEPLOYPATH/uploads
cp -rf includes/. $DEPLOYPATH/includes/
cp -rf api/. $DEPLOYPATH/api/
cp -rf admin/. $DEPLOYPATH/admin/
cp -rf assets/. $DEPLOYPATH/assets/
cp -f uploads/.htaccess $DEPLOYPATH/uploads/

# Removed pages/features — cp only adds, so deleted repo files need
# explicit removal or they stay live in the docroot.
rm -f $DEPLOYPATH/institution.php $DEPLOYPATH/institutions.php \
      $DEPLOYPATH/seed-institutions.php $DEPLOYPATH/migrate-institutions.php \
      $DEPLOYPATH/api/institutions.php

# One-off DB migrations — idempotent; drop these lines (and the status file)
# after they've run. Status lands in the docroot for a quick remote check.
STATUS=$DEPLOYPATH/deploy-status.txt
{
    echo "deploy $(git rev-parse --short HEAD) $(date -Iseconds) reexec=$DEPLOY_REEXEC"
    PHP_BIN=""
    for p in php ea-php84 ea-php83 ea-php82 ea-php81 ea-php80 php84 php83 php82 php81 \
             /usr/local/bin/php /usr/bin/php /opt/cpanel/ea-php84/root/usr/bin/php \
             /opt/cpanel/ea-php83/root/usr/bin/php /opt/cpanel/ea-php82/root/usr/bin/php \
             /opt/cpanel/ea-php81/root/usr/bin/php /opt/cpanel/ea-php80/root/usr/bin/php; do
        command -v "$p" >/dev/null 2>&1 && PHP_BIN=$(command -v "$p") && break
    done
    echo "php_bin=$PHP_BIN"
    if [ -n "$PHP_BIN" ]; then
        "$PHP_BIN" -v | head -1
        NIP_DEPLOY=1 "$PHP_BIN" -d display_errors=1 -d error_reporting=E_ALL migrate-cat.php
    fi
    echo
} > $STATUS 2>&1 || true

echo "deployed $(git rev-parse --short HEAD) -> $DEPLOYPATH"
