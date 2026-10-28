/**
 * components/ui/AlertBanner.jsx
 *
 * Inline alert bar for success / error / info feedback.
 */

import { cn } from '@/utils/cn'

const STYLES = {
  success: 'border-status-success/25 bg-status-success-bg text-status-success',
  error:   'border-status-failed/25  bg-status-failed-bg  text-status-failed',
  info:    'border-blue-500/25        bg-blue-500/10        text-blue-300',
  warning: 'border-status-pending/25 bg-status-pending-bg  text-status-pending',
}

const ICONS = {
  success: '✓',
  error:   '✕',
  info:    'ℹ',
  warning: '⚠',
}

export default function AlertBanner({ type = 'info', message, className, onDismiss }) {
  if (!message) return null

  return (
    <div
      role="alert"
      className={cn(
        'flex items-start gap-3 rounded-xl border px-4 py-3.5 text-sm font-medium',
        STYLES[type] ?? STYLES.info,
        className,
      )}
    >
      <span className="flex-shrink-0 font-bold">{ICONS[type]}</span>
      <span className="flex-1 leading-relaxed">{message}</span>
      {onDismiss && (
        <button
          onClick={onDismiss}
          className="ml-auto flex-shrink-0 opacity-60 hover:opacity-100 transition-opacity"
          aria-label="Dismiss"
        >
          ✕
        </button>
      )}
    </div>
  )
}
