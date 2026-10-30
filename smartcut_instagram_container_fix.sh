#!/usr/bin/env bash
set -euo pipefail

cd /www/wwwroot/smartcut-backend

STAMP="$(date +%Y%m%d-%H%M%S)"
BACKUP_DIR="storage/app/instagram-container-fix-${STAMP}"
mkdir -p "$BACKUP_DIR"
cp app/Models/ContentDestination.php "$BACKUP_DIR/ContentDestination.php"
cp app/Infrastructure/Social/Instagram/InstagramApiClient.php "$BACKUP_DIR/InstagramApiClient.php"
echo "Backup created: $BACKUP_DIR"

python3 <<'PY'
from pathlib import Path

model_path = Path("app/Models/ContentDestination.php")
s = model_path.read_text()
start_marker = "protected $fillable = ["
if start_marker not in s:
    raise SystemExit("ERROR: ContentDestination $fillable block was not found; no model changes made.")
start = s.index(start_marker)
end = s.index("];", start) + 2
block = s[start:end]
if "'provider_reference'" not in block:
    anchor = "        'scheduled_at',"
    if anchor not in block:
        raise SystemExit("ERROR: expected scheduled_at entry was not found inside $fillable.")
    block = block.replace(anchor, anchor + "\n        'provider_reference',", 1)
    s = s[:start] + block + s[end:]
    model_path.write_text(s)
    print("Patched ContentDestination fillable: provider_reference")
else:
    print("ContentDestination already allows provider_reference")

client_path = Path("app/Infrastructure/Social/Instagram/InstagramApiClient.php")
s = client_path.read_text()
old = "'fields' => 'status_code',"
new = "'fields' => 'status_code,status',"
if old in s:
    s = s.replace(old, new, 1)
    client_path.write_text(s)
    print("Patched Instagram container status details")
elif new in s:
    print("Instagram container status details already enabled")
else:
    raise SystemExit("ERROR: expected Instagram status fields line was not found.")
PY

/www/server/php/84/bin/php -l app/Models/ContentDestination.php
/www/server/php/84/bin/php -l app/Infrastructure/Social/Instagram/InstagramApiClient.php

/www/server/php/84/bin/php artisan queue:restart

echo
echo "Patch applied. Queue restart requested."
echo "Next: inspect the current Instagram container status before retrying any publish job."
