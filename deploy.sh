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

DEPLOYPATH=/home/nipangec/public_html/
cp -f *.php $DEPLOYPATH
cp -f manifest.webmanifest sw.js offline.html google*.html $DEPLOYPATH
mkdir -p $DEPLOYPATH/includes $DEPLOYPATH/api $DEPLOYPATH/admin $DEPLOYPATH/assets $DEPLOYPATH/assets/icons $DEPLOYPATH/uploads
cp -rf includes/. $DEPLOYPATH/includes/
cp -rf api/. $DEPLOYPATH/api/
cp -rf admin/. $DEPLOYPATH/admin/
cp -rf assets/. $DEPLOYPATH/assets/
cp -f uploads/.htaccess $DEPLOYPATH/uploads/

echo "deployed $(git rev-parse --short HEAD) -> $DEPLOYPATH"
