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
if [ -z "$DEPLOY_REEXEC" ]; then export DEPLOY_REEXEC=1; exec "$0"; fi

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

# One-off DB migrations — idempotent; drop these lines after they've run.
PHP_BIN=$(command -v php || command -v ea-php83 || command -v ea-php82 || echo /usr/bin/php)
$PHP_BIN migrate-cat.php || true

echo "deployed $(git rev-parse --short HEAD) -> $DEPLOYPATH"
