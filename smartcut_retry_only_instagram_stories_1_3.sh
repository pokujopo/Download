#!/usr/bin/env bash
set -Eeuo pipefail

APP_DIR="$(pwd)"
PHP_BIN="/www/server/php/84/bin/php"

if [[ "$APP_DIR" != "/www/wwwroot/smartcut-backend" || ! -f "$APP_DIR/artisan" ]]; then
  echo "ERROR: Run from /www/wwwroot/smartcut-backend. No database rows changed." >&2
  exit 1
fi
if [[ ! -x "$PHP_BIN" ]]; then
  echo "ERROR: PHP 8.4 binary not found. No database rows changed." >&2
  exit 1
fi

echo "===== Fresh Meta preflight + guarded retry of Instagram Story rows #1 and #3 only ====="
echo "No tokens will be printed. Facebook Stories #2/#4 and feed/Reel accounts will not be touched."

TINKER_CODE=$(cat <<'PHP'
$version = (string) config('services.meta.graph_api_version', 'v26.0');
$targetId = trim((string) config('services.meta.instagram_story_account_id', ''));
$pageSocialId = (int) config('services.meta.instagram_story_page_social_account_id', 0);
$storiesEnabled = (bool) config('services.meta.stories_enabled', false);
$instagramAccount = \App\Models\SocialAccount::query()->find(2);
$pageAccount = $pageSocialId > 0 ? \App\Models\SocialAccount::query()->find($pageSocialId) : null;

$checks = [
    'stories_enabled' => $storiesEnabled,
    'target_instagram_matches' => $targetId === '17841466707772066',
    'page_social_account_id_matches' => $pageSocialId === 5,
    'instagram_account_active' => (bool) ($instagramAccount && (string) $instagramAccount->getRawOriginal('provider') === 'instagram' && (string) $instagramAccount->getRawOriginal('status') === 'active'),
    'page_account_active' => (bool) ($pageAccount && (string) $pageAccount->getRawOriginal('provider') === 'facebook' && (string) $pageAccount->getRawOriginal('status') === 'active'),
    'account_owners_match' => (bool) ($instagramAccount && $pageAccount && (int) $instagramAccount->user_id === (int) $pageAccount->user_id),
    'page_id_matches' => (bool) ($pageAccount && (string) $pageAccount->provider_account_id === '1463266160192660'),
];

$ok = $storiesEnabled
    && preg_match('/^v\d+\.\d+$/', $version) === 1
    && $targetId === '17841466707772066'
    && $pageSocialId === 5
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
        ->get($base . '/1463266160192660', [
            'fields' => 'id,instagram_business_account',
            'access_token' => (string) $pageAccount->access_token,
        ]);
    $pageBody = $pageResponse->json();
    $pageMatches = $pageResponse->successful()
        && is_array($pageBody)
        && !isset($pageBody['error'])
        && (string) ($pageBody['id'] ?? '') === '1463266160192660'
        && (string) ($pageBody['instagram_business_account']['id'] ?? '') === $targetId;
    $checks['page_token_accepted_and_link_matches'] = $pageMatches;
    $checks['page_http_status'] = $pageResponse->status();

    if ($pageMatches) {
        $igResponse = \Illuminate\Support\Facades\Http::acceptJson()->connectTimeout(10)->timeout(20)
            ->get($base . '/' . rawurlencode($targetId), [
                'fields' => 'id,username',
                'access_token' => (string) $pageAccount->access_token,
            ]);
        $igBody = $igResponse->json();
        $igMatches = $igResponse->successful()
            && is_array($igBody)
            && !isset($igBody['error'])
            && (string) ($igBody['id'] ?? '') === $targetId;
        $checks['page_token_can_access_target_instagram'] = $igMatches;
        $checks['instagram_http_status'] = $igResponse->status();
        $ok = $igMatches;
        if (!$igMatches) {
            $error = is_array($igBody) ? ($igBody['error'] ?? []) : [];
            $checks['instagram_meta_error_code'] = is_array($error) ? ($error['code'] ?? null) : null;
            $checks['instagram_meta_error_message'] = is_array($error) ? ($error['message'] ?? null) : null;
        }
    } else {
        $ok = false;
        $error = is_array($pageBody) ? ($pageBody['error'] ?? []) : [];
        $checks['meta_error_code'] = is_array($error) ? ($error['code'] ?? null) : null;
        $checks['meta_error_message'] = is_array($error) ? ($error['message'] ?? null) : null;
    }
} else {
    $checks['account_and_config_preflight_passed'] = false;
    $ok = false;
}
dump(['instagram_story_preflight' => $checks]);

// These exact two rows failed before any Meta publish request, while the queue worker
// was running with old configuration. Never touch Facebook Story rows #2/#4.
$expected = [1 => 4, 3 => 6];
$rows = \Illuminate\Support\Facades\DB::table('content_story_destinations')
    ->whereIn('id', [1, 3])->orderBy('id')
    ->get(['id','source_destination_id','social_account_id','provider','status','attempts','provider_reference','external_media_id','error_code','error_message','published_at']);
$safe = $ok && $rows->count() === 2;
foreach ($rows as $row) {
    $safe = $safe
        && (int) $row->source_destination_id === ($expected[(int) $row->id] ?? -1)
        && (int) $row->social_account_id === 2
        && $row->provider === 'instagram'
        && $row->status === 'failed'
        && $row->error_code === 'META_STORY_PUBLISH_FAILED'
        && str_contains((string) $row->error_message, 'Instagram Story target/Page account configuration is missing.')
        && $row->provider_reference === null
        && $row->external_media_id === null
        && $row->published_at === null;
}
if (!$safe) {
    dump(['retry' => 'STOPPED; no Story rows changed and no jobs dispatched', 'matched_rows' => $rows->count()]);
    echo "SMARTCUT_INSTAGRAM_STORY_RETRY=STOPPED\n";
    return;
}

\Illuminate\Support\Facades\DB::transaction(function () use ($expected) {
    $locked = \Illuminate\Support\Facades\DB::table('content_story_destinations')
        ->whereIn('id', [1, 3])->orderBy('id')->lockForUpdate()->get();
    if ($locked->count() !== 2) {
        throw new \RuntimeException('Guard failed: expected exactly two Instagram Story rows.');
    }
    foreach ($locked as $row) {
        if ((int) $row->source_destination_id !== ($expected[(int) $row->id] ?? -1)
            || (int) $row->social_account_id !== 2
            || $row->provider !== 'instagram'
            || $row->status !== 'failed'
            || $row->error_code !== 'META_STORY_PUBLISH_FAILED'
            || !str_contains((string) $row->error_message, 'Instagram Story target/Page account configuration is missing.')
            || $row->provider_reference !== null
            || $row->external_media_id !== null
            || $row->published_at !== null) {
            throw new \RuntimeException('Guard failed on Story #' . $row->id . '; no retry dispatched.');
        }
    }
    foreach ([1, 3] as $id) {
        $changed = \Illuminate\Support\Facades\DB::table('content_story_destinations')
            ->where('id', $id)
            ->where('status', 'failed')
            ->whereNull('provider_reference')
            ->whereNull('external_media_id')
            ->whereNull('published_at')
            ->where('error_message', 'like', '%Instagram Story target/Page account configuration is missing.%')
            ->update([
                'status' => 'queued',
                'attempts' => \Illuminate\Support\Facades\DB::raw('attempts + 1'),
                'error_code' => null,
                'error_message' => null,
                'provider_response' => null,
                'processing_started_at' => null,
                'updated_at' => now('UTC')->format('Y-m-d H:i:s'),
            ]);
        if ($changed !== 1) {
            throw new \RuntimeException('Guard failed while claiming Story #' . $id . '; transaction rolled back.');
        }
    }
});

foreach ([1, 3] as $id) {
    \App\Jobs\PublishSmartAutocutStory::dispatch($id);
    echo "Queued Instagram Story #{$id} only.\n";
}
dump(['retry' => 'Only Instagram Story rows #1 and #3 queued; Facebook Story rows untouched.']);
echo "SMARTCUT_INSTAGRAM_STORY_RETRY=QUEUED\n";
PHP
)

set +e
TINKER_OUTPUT=$("$PHP_BIN" artisan tinker --execute="$TINKER_CODE" 2>&1)
STATUS=$?
set -e
printf '%s\n' "$TINKER_OUTPUT"
if [[ $STATUS -ne 0 ]]; then
  echo "ERROR: Tinker exited nonzero. Inspect output; do not rerun blindly." >&2
  exit "$STATUS"
fi
if ! printf '%s\n' "$TINKER_OUTPUT" | grep -q 'SMARTCUT_INSTAGRAM_STORY_RETRY=QUEUED'; then
  echo "STOP: Preflight or row guards did not pass. No retry should have been dispatched." >&2
  exit 2
fi

echo
echo "Retry queued. Inspect rows #1 and #3 once; do not run this script again."
