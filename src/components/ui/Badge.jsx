import { cn } from '@/utils/cn'

const STATUS_STYLES = {
  success: 'bg-status-success-bg text-status-success',
  failed:  'bg-status-failed-bg  text-status-failed',
  pending: 'bg-status-pending-bg text-status-pending',
}

const STATUS_ICONS = {
  success: '✓',
  failed:  '✕',
  pending: '⏳',
}

export function StatusBadge({ status }) {
  return (
    <span
      className={cn(
        'inline-flex flex-shrink-0 items-center gap-1 rounded-full px-2.5 py-1',
        'text-[11px] font-bold capitalize',
        STATUS_STYLES[status] ?? STATUS_STYLES.pending,
      )}
    >
      {STATUS_ICONS[status]} {status}
    </span>
  )
}

export function PlatformChip({ platform }) {
  const COLOR_MAP = {
    YouTube:  { bg: 'rgba(255,0,0,0.12)',        color: '#FF0000' },
    Facebook: { bg: 'rgba(24,119,242,0.12)',      color: '#1877F2' },
    X:        { bg: 'rgba(231,233,234,0.10)',     color: '#E7E9EA' },
  }
  const style = COLOR_MAP[platform] ?? { bg: 'rgba(255,255,255,0.08)', color: '#7B8CB3' }

  return (
    <span
      className="inline-block flex-shrink-0 rounded-full px-2 py-0.5 text-[10px] font-bold"
      style={{ background: style.bg, color: style.color }}
    >
      {platform}
    </span>
  )
}
