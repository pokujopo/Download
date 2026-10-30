#!/usr/bin/env bash
set -Eeuo pipefail

APP_DIR="$(pwd)"
PHP_BIN="/www/server/php/84/bin/php"
JOB="app/Jobs/PublishSmartAutocutStory.php"
CONFIG="config/services.php"

if [[ ! -f "$APP_DIR/artisan" || ! -f "$APP_DIR/composer.json" ]]; then
  echo "ERROR: Run this script from /www/wwwroot/smartcut-backend." >&2
  exit 1
fi
if [[ ! -x "$PHP_BIN" || ! -f "$JOB" || ! -f "$CONFIG" || ! -f .env ]]; then
  echo "ERROR: Required Laravel files/PHP binary are missing; no changes made." >&2
  exit 1
fi

STAMP="$(date +%Y%m%d-%H%M%S)"
BACKUP="storage/app/story-instagram-page-binding-${STAMP}"
mkdir -p "$BACKUP"
cp -a "$JOB" "$BACKUP/PublishSmartAutocutStory.php"
cp -a "$CONFIG" "$BACKUP/services.php"
cp -a .env "$BACKUP/.env"
echo "Backup created: $BACKUP"

python3 <<'PYCODE'
from pathlib import Path

job_path = Path("app/Jobs/PublishSmartAutocutStory.php")
s = job_path.read_text()

# Ensure Eloquent SocialAccount is imported so encrypted casts are respected.
if "use App\\Models\\SocialAccount;" not in s:
    ns = "namespace App\\Jobs;\n"
    if ns not in s:
        raise SystemExit("ERROR: Could not find Story job namespace; refusing uncertain patch.")
    s = s.replace(ns, ns + "\nuse App\\Models\\SocialAccount;", 1)

old_branch = """            if ($story->provider === 'instagram') {
                $this->publishInstagramStory($story, $version);
            } else {
                $this->publishFacebookStory($story, $version);
            }"""
new_branch = """            if ($story->provider === 'instagram') {
                // Story publishing uses Facebook Login credentials from the Page linked
                // to the target professional Instagram account. This is Story-only;
                // the existing Instagram feed-publishing account/token stays untouched.
                $this->resolveInstagramStoryCredentials($story, $socialAccount, $version);
                $this->publishInstagramStory($story, $version);
            } else {
                $this->publishFacebookStory($story, $version);
            }"""
if new_branch not in s:
    if s.count(old_branch) != 1:
        raise SystemExit("ERROR: Could not locate the exact provider dispatch branch; refusing uncertain patch.")
    s = s.replace(old_branch, new_branch, 1)
else:
    print("Instagram Story resolver call already present.")

method = r'''
    /**
     * Resolve credentials specifically for Instagram Stories via Facebook Login.
     * This deliberately does not rewrite the SocialAccount row used by feed/Reel jobs.
     */
    private function resolveInstagramStoryCredentials(object $story, SocialAccount $instagramAccount, string $version): void
    {
        $targetInstagramId = trim((string) config('services.meta.instagram_story_account_id', ''));
        $pageAccountId = (int) config('services.meta.instagram_story_page_social_account_id', 0);

        if ($targetInstagramId === '' || $pageAccountId <= 0) {
            throw new \RuntimeException('Instagram Story target/Page account configuration is missing.');
        }
        if ((string) $instagramAccount->getRawOriginal('provider') !== 'instagram') {
            throw new \RuntimeException('The source social account for this Instagram Story is not an Instagram account.');
        }
        if ((string) $instagramAccount->getRawOriginal('status') !== 'active') {
            throw new \RuntimeException('The source Instagram social account is not active.');
        }

        $pageAccount = SocialAccount::query()->find($pageAccountId);
        if (! $pageAccount
            || (string) $pageAccount->getRawOriginal('provider') !== 'facebook'
            || (string) $pageAccount->getRawOriginal('status') !== 'active'
            || (int) $pageAccount->user_id !== (int) $instagramAccount->user_id) {
            throw new \RuntimeException('Configured Facebook Page account is missing, inactive, or belongs to a different user.');
        }

        $pageId = trim((string) $pageAccount->provider_account_id);
        $pageToken = (string) $pageAccount->access_token; // Eloquent decrypts encrypted cast.
        if ($pageId === '' || $pageToken === '') {
            throw new \RuntimeException('Configured Facebook Page ID/token is missing.');
        }

        $base = "https://graph.facebook.com/{$version}";
        $pageResponse = Http::acceptJson()->connectTimeout(10)->timeout(20)
            ->get("{$base}/" . rawurlencode($pageId), [
                'fields' => 'id,instagram_business_account',
                'access_token' => $pageToken,
            ]);
        $pageBody = $pageResponse->json();
        if (! $pageResponse->successful() || ! is_array($pageBody) || isset($pageBody['error'])) {
            $error = is_array($pageBody) ? ($pageBody['error'] ?? []) : [];
            $code = is_array($error) ? ($error['code'] ?? 'n/a') : 'n/a';
            $message = is_array($error) ? ($error['message'] ?? '') : '';
            throw new \RuntimeException('Could not verify the configured Facebook Page to Instagram link (HTTP '
                . $pageResponse->status() . ', Meta code ' . $code . '): ' . mb_substr((string) $message, 0, 500));
        }

        $returnedPageId = (string) ($pageBody['id'] ?? '');
        $linkedInstagramId = (string) ($pageBody['instagram_business_account']['id'] ?? '');
        if ($returnedPageId !== $pageId || $linkedInstagramId !== $targetInstagramId) {
            throw new \RuntimeException('Configured Facebook Page is not linked to the exact Instagram Story account ID. No Story was published.');
        }

        // Confirm this Page token can access the exact target Instagram professional account.
        $igResponse = Http::acceptJson()->connectTimeout(10)->timeout(20)
            ->get("{$base}/" . rawurlencode($targetInstagramId), [
                'fields' => 'id,username',
                'access_token' => $pageToken,
            ]);
        $igBody = $igResponse->json();
        if (! $igResponse->successful() || ! is_array($igBody) || isset($igBody['error'])
            || (string) ($igBody['id'] ?? '') !== $targetInstagramId) {
            $error = is_array($igBody) ? ($igBody['error'] ?? []) : [];
            $code = is_array($error) ? ($error['code'] ?? 'n/a') : 'n/a';
            $message = is_array($error) ? ($error['message'] ?? '') : '';
            throw new \RuntimeException('Facebook Page token cannot access the target Instagram account (HTTP '
                . $igResponse->status() . ', Meta code ' . $code . '): ' . mb_substr((string) $message, 0, 500));
        }

        // Override only this Story job's credentials/target; do not mutate social_accounts.
        $story->provider_account_id = $targetInstagramId;
        $story->access_token = $pageToken;
    }
'''
if 'private function resolveInstagramStoryCredentials(' not in s:
    marker = '    private function publishInstagramStory(object $story, string $version): void\n'
    if s.count(marker) != 1:
        raise SystemExit("ERROR: Could not find Instagram Story publisher method marker; refusing uncertain method insertion.")
    s = s.replace(marker, method + '\n' + marker, 1)
    print("Added Story-only Facebook Page credential resolver.")
else:
    print("Story-only Facebook Page credential resolver already present.")
job_path.write_text(s)

config_path = Path("config/services.php")
cfg = config_path.read_text()
meta_start = cfg.find("'meta' => [")
if meta_start < 0:
    raise SystemExit("ERROR: config/services.php does not contain services.meta array; refusing to insert in an uncertain location.")
meta_end = cfg.find('],', meta_start)
if meta_end < 0:
    raise SystemExit("ERROR: Could not find end of services.meta array.")
meta_block = cfg[meta_start:meta_end+2]
needed = {
    "'instagram_story_account_id'": "        'instagram_story_account_id' => env('SMARTCUT_INSTAGRAM_STORY_ACCOUNT_ID', ''),",
    "'instagram_story_page_social_account_id'": "        'instagram_story_page_social_account_id' => (int) env('SMARTCUT_INSTAGRAM_STORY_PAGE_SOCIAL_ACCOUNT_ID', 0),",
}
missing = [line for key, line in needed.items() if key not in meta_block]
if missing:
    close = meta_block.rfind(']')
    before = meta_block[:close].rstrip()
    if before and not before.endswith(','):
        before += ','
    updated_block = before + '\n' + '\n'.join(missing) + '\n    ],'
    cfg = cfg[:meta_start] + updated_block + cfg[meta_end+2:]
    config_path.write_text(cfg)
    print("Added Instagram Story target/Page account configuration.")
else:
    print("Instagram Story target/Page account configuration already present.")
PYCODE

# Store IDs/config only; never read, print, or rewrite access tokens here.
python3 <<'PYENV'
from pathlib import Path

p = Path('.env')
lines = p.read_text().splitlines()
values = {
    'SMARTCUT_INSTAGRAM_STORY_ACCOUNT_ID': '17841466707772066',
    'SMARTCUT_INSTAGRAM_STORY_PAGE_SOCIAL_ACCOUNT_ID': '5',
}
seen = set()
updated = []
for line in lines:
    if '=' in line and not line.lstrip().startswith('#'):
        key = line.split('=', 1)[0].strip()
        if key in values:
            updated.append(f'{key}={values[key]}')
            seen.add(key)
            continue
    updated.append(line)
for key, value in values.items():
    if key not in seen:
        updated.append(f'{key}={value}')
p.write_text('\n'.join(updated) + '\n')
print('Set Instagram Story target ID and Page social-account ID. No tokens were printed or modified.')
PYENV

echo "===== PHP syntax checks ====="
"$PHP_BIN" -l "$JOB"
"$PHP_BIN" -l "$CONFIG"

echo "===== Refresh Laravel configuration ====="
"$PHP_BIN" artisan config:clear
"$PHP_BIN" artisan config:cache

echo "===== Read-only Meta preflight and guarded retry of only Story rows #1-#4 ====="
TINKER_CODE=$(cat <<'PHP'
$version = (string) config('services.meta.graph_api_version', 'v26.0');
$targetId = (string) config('services.meta.instagram_story_account_id', '');
$pageSocialId = (int) config('services.meta.instagram_story_page_social_account_id', 0);
$instagramAccount = \App\Models\SocialAccount::query()->find(2);
$pageAccount = \App\Models\SocialAccount::query()->find($pageSocialId);
$checks = [
    'stories_enabled' => (bool) config('services.meta.stories_enabled', false),
    'target_instagram_id' => $targetId,
    'page_social_account_id' => $pageSocialId,
    'instagram_account_found' => (bool) $instagramAccount,
    'page_account_found' => (bool) $pageAccount,
    'owner_ids_match' => (bool) ($instagramAccount && $pageAccount && (int) $instagramAccount->user_id === (int) $pageAccount->user_id),
    'page_id' => $pageAccount ? (string) $pageAccount->provider_account_id : null,
];
$ok = (bool) config('services.meta.stories_enabled', false)
    && preg_match('/^v\d+\.\d+$/', $version) === 1
    && $targetId === '17841466707772066'
    && $instagramAccount
    && (string) $instagramAccount->getRawOriginal('provider') === 'instagram'
    && (string) $instagramAccount->getRawOriginal('status') === 'active'
    && $pageAccount
    && (string) $pageAccount->getRawOriginal('provider') === 'facebook'
    && (string) $pageAccount->getRawOriginal('status') === 'active'
    && (int) $instagramAccount->user_id === (int) $pageAccount->user_id
    && (string) $pageAccount->provider_account_id === '1463266160192660'
    && (string) $pageAccount->access_token !== '';
if ($ok) {
    $base = "https://graph.facebook.com/{$version}";
    $pageResponse = \Illuminate\Support\Facades\Http::acceptJson()->connectTimeout(10)->timeout(20)
        ->get($base . '/' . rawurlencode((string) $pageAccount->provider_account_id), [
            'fields' => 'id,instagram_business_account',
            'access_token' => (string) $pageAccount->access_token,
        ]);
    $pageBody = $pageResponse->json();
    $pageMatch = $pageResponse->successful() && is_array($pageBody)
        && !isset($pageBody['error'])
        && (string) ($pageBody['id'] ?? '') === (string) $pageAccount->provider_account_id
        && (string) ($pageBody['instagram_business_account']['id'] ?? '') === $targetId;
    $checks['page_token_accepted'] = $pageResponse->successful() && is_array($pageBody) && !isset($pageBody['error']);
    $checks['page_link_matches_target_instagram'] = $pageMatch;
    $checks['page_http_status'] = $pageResponse->status();
    if (!$pageMatch) {
        $checks['meta_error_code'] = is_array($pageBody) ? ($pageBody['error']['code'] ?? null) : null;
        $checks['meta_error_message'] = is_array($pageBody) ? ($pageBody['error']['message'] ?? null) : null;
    }
    if ($pageMatch) {
        $igResponse = \Illuminate\Support\Facades\Http::acceptJson()->connectTimeout(10)->timeout(20)
            ->get($base . '/' . rawurlencode($targetId), [
                'fields' => 'id,username',
                'access_token' => (string) $pageAccount->access_token,
            ]);
        $igBody = $igResponse->json();
        $checks['target_instagram_token_access'] = $igResponse->successful() && is_array($igBody)
            && !isset($igBody['error']) && (string) ($igBody['id'] ?? '') === $targetId;
        $checks['instagram_http_status'] = $igResponse->status();
        if (!$checks['target_instagram_token_access']) {
            $checks['instagram_meta_error_code'] = is_array($igBody) ? ($igBody['error']['code'] ?? null) : null;
            $checks['instagram_meta_error_message'] = is_array($igBody) ? ($igBody['error']['message'] ?? null) : null;
        }
        $ok = $checks['target_instagram_token_access'];
    } else {
        $ok = false;
    }
} else {
    $checks['configuration_and_account_checks_passed'] = false;
    $ok = false;
}
dump(['instagram_story_preflight' => $checks]);

// Never reset or dispatch unless preflight succeeded and the four prior failures
// still have exactly the expected source destinations, provider, and no remote ID.
$expected = [1 => [4, 'instagram', 2], 2 => [5, 'facebook', 5], 3 => [6, 'instagram', 2], 4 => [7, 'facebook', 5]];
$rows = \Illuminate\Support\Facades\DB::table('content_story_destinations')
    ->whereIn('id', [1, 2, 3, 4])->orderBy('id')
    ->get(['id','source_destination_id','social_account_id','provider','status','attempts','provider_reference','external_media_id','error_code','error_message','published_at']);
$safe = $ok && $rows->count() === 4;
foreach ($rows as $row) {
    [$sourceDestinationId, $provider, $socialAccountId] = $expected[(int) $row->id] ?? [null, null, null];
    $safe = $safe
        && (int) $row->source_destination_id === $sourceDestinationId
        && $row->provider === $provider
        && (int) $row->social_account_id === $socialAccountId
        && $row->status === 'failed'
        && $row->error_code === 'META_STORY_PUBLISH_FAILED'
        && str_contains((string) $row->error_message, 'Cannot parse access token')
        && $row->provider_reference === null
        && $row->external_media_id === null
        && $row->published_at === null;
}
if (!$safe) {
    dump(['retry' => 'STOPPED; no Story rows changed and no jobs dispatched', 'expected_failed_rows' => $rows->count()]);
    echo "SMARTCUT_STORY_RETRY_RESULT=STOPPED\n";
} else {
    foreach ($rows as $row) {
        $claimed = \Illuminate\Support\Facades\DB::table('content_story_destinations')
            ->where('id', $row->id)
            ->where('status', 'failed')
            ->whereNull('provider_reference')
            ->whereNull('external_media_id')
            ->whereNull('published_at')
            ->update([
                'status' => 'queued',
                'attempts' => \Illuminate\Support\Facades\DB::raw('attempts + 1'),
                'error_code' => null,
                'error_message' => null,
                'provider_response' => null,
                'processing_started_at' => null,
                'updated_at' => now('UTC')->format('Y-m-d H:i:s'),
            ]);
        if ($claimed !== 1) {
            throw new \RuntimeException('Guard stopped while claiming Story #' . $row->id . '; inspect row status before retrying.');
        }
        try {
            \App\Jobs\PublishSmartAutocutStory::dispatch((int) $row->id);
            echo 'Queued safe retry for Story #' . $row->id . ' (' . $row->provider . ').' . "\n";
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\DB::table('content_story_destinations')->where('id', $row->id)->update([
                'status' => 'pending',
                'error_code' => 'QUEUE_DISPATCH_FAILED',
                'error_message' => mb_substr($e->getMessage(), 0, 1000),
                'updated_at' => now('UTC')->format('Y-m-d H:i:s'),
            ]);
            throw $e;
        }
    }
    dump(['retry' => 'Queued exactly the four known token failures only; no other Stories were touched.']);
    echo "SMARTCUT_STORY_RETRY_RESULT=QUEUED\n";
}
PHP
)

TINKER_OUTPUT=$("$PHP_BIN" artisan tinker --execute="$TINKER_CODE" 2>&1) || {
  printf '%s\n' "$TINKER_OUTPUT"
  echo "ERROR: Preflight/retry command returned failure. Inspect output; do not rerun blindly." >&2
  exit 1
}
printf '%s\n' "$TINKER_OUTPUT"
if ! printf '%s\n' "$TINKER_OUTPUT" | grep -q 'SMARTCUT_STORY_RETRY_RESULT=QUEUED'; then
  echo "STOP: Meta/account preflight failed or Story rows no longer matched the safe-retry guard. No retry was queued." >&2
  exit 2
fi

echo "===== Syntax/config completed; only Story rows #1-#4 were queued ====="
echo "Feed/Reel publishing SocialAccount rows were not modified."
echo "Backup: $BACKUP"
