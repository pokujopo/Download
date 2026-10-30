#!/usr/bin/env bash
set -Eeuo pipefail

APP_DIR="$(pwd)"
PHP_BIN="/www/server/php/84/bin/php"
TARGET="app/Jobs/PublishSmartAutocutStory.php"

if [[ ! -f "$APP_DIR/artisan" || ! -f "$APP_DIR/composer.json" ]]; then
  echo "ERROR: Run this script from /www/wwwroot/smartcut-backend." >&2
  exit 1
fi
if [[ ! -x "$PHP_BIN" || ! -f "$TARGET" ]]; then
  echo "ERROR: PHP 8.4 or Story publisher file was not found. No code changes made." >&2
  exit 1
fi

STAMP="$(date +%Y%m%d-%H%M%S)"
BACKUP="${TARGET}.backup-encrypted-token-${STAMP}"
cp -a "$TARGET" "$BACKUP"
echo "Backup created: $BACKUP"

python3 <<'PY'
from pathlib import Path

p = Path("app/Jobs/PublishSmartAutocutStory.php")
s = p.read_text()

imp = "use App\\Models\\SocialAccount;\n"
if imp not in s:
    marker = "namespace App\\Jobs;\n\n"
    if marker not in s:
        raise SystemExit("ERROR: Could not find the expected namespace marker.")
    s = s.replace(marker, marker + imp, 1)

old = '''        $story = DB::table('content_story_destinations as s')
            ->join('social_accounts as a', 'a.id', '=', 's.social_account_id')
            ->where('s.id', $this->storyId)
            ->first([
                's.id', 's.provider', 's.media_url', 's.provider_reference',
                'a.provider_account_id', 'a.access_token', 'a.status as account_status',
            ]);

        if (! $story) {
            $this->failStory('SOCIAL_ACCOUNT_NOT_FOUND', 'The connected social account for this Story no longer exists.');
            return;
        }
        if ($story->account_status !== 'active') {'''

new = '''        // Read Story metadata with the query builder, but load social credentials
        // through Eloquent so SocialAccount's encrypted casts decrypt the token.
        $story = DB::table('content_story_destinations as s')
            ->where('s.id', $this->storyId)
            ->first([
                's.id', 's.provider', 's.media_url', 's.provider_reference',
                's.social_account_id',
            ]);

        if (! $story) {
            $this->failStory('STORY_NOT_FOUND', 'The Story destination no longer exists.');
            return;
        }

        $socialAccount = SocialAccount::query()->find($story->social_account_id);
        if (! $socialAccount) {
            $this->failStory('SOCIAL_ACCOUNT_NOT_FOUND', 'The connected social account for this Story no longer exists.');
            return;
        }

        // access_token is cast as "encrypted" on SocialAccount. DB::table()
        // reads ciphertext, which Meta rejects with OAuth error 190.
        $story->provider_account_id = $socialAccount->provider_account_id;
        $story->access_token = $socialAccount->access_token;
        $story->account_status = $socialAccount->getRawOriginal('status');

        if ($story->account_status !== 'active') {'''

if new in s:
    print("Encrypted-token fix already exists.")
elif s.count(old) == 1:
    p.write_text(s.replace(old, new, 1))
    print("Patched publisher to read decrypted access_token from SocialAccount.")
else:
    raise SystemExit("ERROR: Expected token query did not match exactly once; refusing an uncertain patch.")
PY

echo "===== PHP syntax check ====="
"$PHP_BIN" -l "$TARGET"
echo "===== Graceful queue worker restart signal ====="
"$PHP_BIN" artisan queue:restart

cat <<'NOTICE'
===== Verify Meta accepts the decrypted credentials, then retry only the four prior failures =====
This operation will NOT print the access tokens.
If either token check fails, it will NOT reset or dispatch any Story.
If both pass, it resets only Story rows #1-#4 when they still match the known
Meta OAuth error 190, have no provider reference/media ID, and are still failed.
Then it runs the existing idempotent Story dispatcher to queue those retries.
NOTICE

TINKER_CODE=$(cat <<'PHP'
$checks = [];
$apiVersion = (string) config("services.meta.graph_api_version", "v26.0");
foreach ([[2, "instagram", "id,username"], [5, "facebook", "id,name"]] as [$accountId, $provider, $fields]) {
    $account = \App\Models\SocialAccount::query()->find($accountId);
    $accountProvider = $account?->provider;
    if ($accountProvider instanceof \BackedEnum) { $accountProvider = $accountProvider->value; }
    if (!$account || (string) $accountProvider !== $provider || !$account->access_token || !$account->provider_account_id) {
        $checks[] = ["provider" => $provider, "account_id" => $accountId, "token_accepted" => false, "reason" => "Account, provider, token or provider ID missing"];
        continue;
    }
    $url = "https://graph.facebook.com/{$apiVersion}/" . rawurlencode((string) $account->provider_account_id);
    try {
        $response = \Illuminate\Support\Facades\Http::acceptJson()->connectTimeout(10)->timeout(20)->get($url, [
            "fields" => $fields,
            "access_token" => (string) $account->access_token,
        ]);
        $body = $response->json();
        $body = is_array($body) ? $body : [];
        $accepted = $response->successful() && !isset($body["error"]) && (string) ($body["id"] ?? "") === (string) $account->provider_account_id;
        $checks[] = [
            "provider" => $provider,
            "account_id" => $accountId,
            "http_status" => $response->status(),
            "returned_id_matches" => (string) ($body["id"] ?? "") === (string) $account->provider_account_id,
            "token_accepted" => $accepted,
            "meta_error_code" => $body["error"]["code"] ?? null,
            "meta_error_message" => $body["error"]["message"] ?? null,
        ];
    } catch (\Throwable $e) {
        $checks[] = ["provider" => $provider, "account_id" => $accountId, "token_accepted" => false, "reason" => get_class($e)];
    }
}
dump(["meta_credential_checks" => $checks]);
$allValid = count($checks) === 2;
foreach ($checks as $check) { $allValid = $allValid && (($check["token_accepted"] ?? false) === true); }
if (!$allValid) {
    dump(["retry" => "STOPPED; no Story rows were reset and no jobs dispatched"]);
} else {
    $ids = [1, 2, 3, 4];
    $rows = \Illuminate\Support\Facades\DB::table("content_story_destinations")
        ->whereIn("id", $ids)
        ->orderBy("id")
        ->get(["id", "source_destination_id", "provider", "status", "provider_reference", "external_media_id", "error_code", "error_message", "published_at"]);
    $safe = $rows->count() === 4;
    foreach ($rows as $row) {
        $safe = $safe
            && in_array((int) $row->source_destination_id, [4, 5, 6, 7], true)
            && $row->status === "failed"
            && $row->error_code === "META_STORY_PUBLISH_FAILED"
            && str_contains((string) $row->error_message, "Cannot parse access token")
            && $row->provider_reference === null
            && $row->external_media_id === null
            && $row->published_at === null;
    }
    if (!$safe) {
        dump(["retry" => "STOPPED; Story rows no longer match the four safe-to-retry OAuth failures", "rows" => $rows]);
    } else {
        \Illuminate\Support\Facades\DB::transaction(function () use ($ids) {
            $updated = \Illuminate\Support\Facades\DB::table("content_story_destinations")
                ->whereIn("id", $ids)
                ->where("status", "failed")
                ->where("error_code", "META_STORY_PUBLISH_FAILED")
                ->whereNull("provider_reference")
                ->whereNull("external_media_id")
                ->whereNull("published_at")
                ->update([
                    "status" => "pending",
                    "attempts" => 0,
                    "error_code" => null,
                    "error_message" => null,
                    "provider_response" => null,
                    "processing_started_at" => null,
                    "updated_at" => now("UTC")->format("Y-m-d H:i:s"),
                ]);
            if ($updated !== 4) { throw new \RuntimeException("Safe retry guard expected 4 rows but reset {$updated}; stop and inspect database."); }
        });
        echo "Both Meta credentials are accepted. Reset exactly Story rows #1-#4.\n";
        $exitCode = \Illuminate\Support\Facades\Artisan::call("smart-autocut:dispatch-stories");
        echo \Illuminate\Support\Facades\Artisan::output();
        if ($exitCode !== 0) { throw new \RuntimeException("Story dispatcher returned exit code {$exitCode}."); }
        dump(["retry" => "Dispatcher finished. Inspect content_story_destinations and queue-worker logs for each result."]);
    }
}
PHP
)

"$PHP_BIN" artisan tinker --execute="$TINKER_CODE"
