
/**
 * pages/CreatePostPage.jsx
 *
 * Ze Post — create and publish content.
 */

import { useOutletContext } from 'react-router-dom'
import { useAccounts } from '@/hooks/useAccounts'
import { useUpload } from '@/hooks/useUpload'

import PageHeader from '@/components/ui/PageHeader'
import InfoBanner from '@/components/ui/InfoBanner'
import Card, { CardHeader } from '@/components/ui/Card'
import Button from '@/components/ui/Button'
import AlertBanner from '@/components/ui/AlertBanner'

import UploadZone, {
  FilePreview,
} from '@/components/UploadZone'

import PlatformSelector from '@/components/PlatformSelector'
import ProgressBar from '@/components/ProgressBar'
import SuccessBanner from '@/components/SuccessBanner'

export default function CreatePostPage() {
  const { addPost } = useOutletContext()

  const {
    accounts,
    loading: accountsLoading,
    error: accountsError,
  } = useAccounts()

  const upload = useUpload(
    accounts,
    (content) => {
      addPost?.(content)
    },
  )

  return (
    <div>
      <PageHeader
        title="Ze Post"
        subtitle="Create once — publish everywhere"
      />

      <InfoBanner>
        Add your media, write your caption, choose where to publish,
        then publish your post from one place.
      </InfoBanner>

      {accountsError && (
        <AlertBanner
          type="error"
          message={accountsError}
          className="mb-5"
        />
      )}

      <div className="grid grid-cols-1 gap-5 xl:grid-cols-[1fr_360px]">

        {/* ─────────────────────────────────────────────────────────
            LEFT — CONTENT
        ───────────────────────────────────────────────────────── */}
        <div className="flex flex-col gap-5">

          <Card>
            <CardHeader
              title="Create Content"
              badge={upload.fileType || undefined}
            />

            {upload.previewUrl ? (
              <FilePreview
                file={upload.file}
                previewUrl={upload.previewUrl}
                fileType={upload.fileType}
                onRemove={upload.clearFile}
              />
            ) : (
              <UploadZone
                onFile={upload.setFile}
                error={
                  upload.error &&
                  !accountsError
                    ? upload.error
                    : null
                }
              />
            )}

            {/* Caption */}
            <div className="mt-5">
              <div className="mb-2 flex items-center justify-between">
                <label
                  htmlFor="post-caption"
                  className="text-[11px] font-semibold uppercase tracking-widest text-ink-muted"
                >
                  Caption
                </label>

                <span className="text-[11px] text-ink-muted">
                  {upload.caption.length}/2200
                </span>
              </div>

              <textarea
                id="post-caption"
                value={upload.caption}
                onChange={(e) =>
                  upload.setCaption(e.target.value)
                }
                maxLength={2200}
                rows={6}
                placeholder="Write your caption…"
                className="w-full resize-y rounded-xl border border-surface-border
                           bg-white/[0.04] px-4 py-3.5 font-body text-sm
                           leading-relaxed text-ink outline-none
                           placeholder:text-ink-faint
                           transition-colors focus:border-brand/50"
              />
            </div>

            {upload.uploading && (
              <ProgressBar
                progress={upload.progress}
                label="Uploading post…"
              />
            )}

            {upload.error && (
              <AlertBanner
                type="error"
                message={upload.error}
                className="mt-4"
                onDismiss={upload.reset}
              />
            )}

            {upload.success && <SuccessBanner />}
          </Card>

        </div>

        {/* ─────────────────────────────────────────────────────────
            RIGHT — DESTINATIONS + ACTION
        ───────────────────────────────────────────────────────── */}
        <div className="flex flex-col gap-5">

          <Card>
            <CardHeader
              title="Publish To"
              badge={
                upload.selectedPlatforms.length
                  ? `${upload.selectedPlatforms.length} selected`
                  : undefined
              }
            />

            {accountsLoading ? (
              <div className="rounded-xl border border-surface-border bg-white/[0.03] p-4 text-sm text-ink-muted">
                Loading connected accounts…
              </div>
            ) : (
              <PlatformSelector
                selected={upload.platforms}
                onToggle={upload.togglePlatform}
              />
            )}

            {upload.hasInvalidDestination && (
              <AlertBanner
                type="warning"
                message="One or more selected platforms are not connected. Connect the account before publishing."
                className="mt-4"
              />
            )}
          </Card>

          <Card>
            <CardHeader title="Ready to Publish?" />

            <div className="mb-5 flex flex-col gap-2.5">
              <ChecklistItem
                done={!!upload.file}
                label="Media selected"
              />

              <ChecklistItem
                done={upload.caption.trim().length > 0}
                label="Caption written"
              />

              <ChecklistItem
                done={
                  upload.selectedPlatforms.length > 0 &&
                  !upload.hasInvalidDestination
                }
                label="Connected destination selected"
              />
            </div>

            <Button
              variant="primary"
              size="lg"
              disabled={
                !upload.isValid ||
                upload.uploading ||
                accountsLoading
              }
              onClick={upload.submit}
            >
              {upload.uploading
                ? `⏳ Publishing… ${upload.progress}%`
                : '🚀 Publish Now'}
            </Button>
          </Card>

        </div>
      </div>
    </div>
  )
}

function ChecklistItem({ done, label }) {
  return (
    <div className="flex items-center gap-2.5">
      <span
        className={[
          'flex h-4 w-4 flex-shrink-0 items-center',
          'justify-center rounded-full text-[10px] font-bold',
          done
            ? 'bg-status-success-bg text-status-success'
            : 'bg-white/[0.06] text-ink-faint',
        ].join(' ')}
      >
        {done ? '✓' : '○'}
      </span>

      <span
        className={
          done
            ? 'text-[13px] text-ink'
            : 'text-[13px] text-ink-muted'
        }
      >
        {label}
      </span>
    </div>
  )
}