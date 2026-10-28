/**
 * components/ProgressBar.jsx
 *
 * Simpler real-progress version — shows a single labelled bar (no fake steps).
 */

import { cn } from '@/utils/cn'

export default function ProgressBar({ progress = 0, label = 'Uploading…', className }) {
  return (
    <div className={cn('mt-5 rounded-xl border border-surface-border bg-surface-card2 p-4', className)}>
      <div className="mb-2.5 flex items-center justify-between text-[13px] font-semibold">
        <span className="text-ink">{label}</span>
        <span className="text-brand">{progress}%</span>
      </div>

      <div className="relative h-2 overflow-hidden rounded-full bg-white/[0.07]">
        <div
          className="progress-tip absolute left-0 top-0 h-full rounded-full
                     bg-gradient-to-r from-brand to-brand-light transition-all duration-300"
          style={{ width: `${progress}%` }}
        />
      </div>

      {progress < 100 ? (
        <p className="mt-2 text-[11px] text-ink-muted">
          Please don't close this tab while uploading…
        </p>
      ) : (
        <p className="mt-2 text-[11px] text-status-success font-semibold">
          ✓ Upload complete — finalising…
        </p>
      )}
    </div>
  )
}
