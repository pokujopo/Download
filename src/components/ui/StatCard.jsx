import { cn } from '@/utils/cn'

const VARIANT_MAP = {
  orange: {
    iconBg:   'bg-brand-soft text-brand',
    glowBg:   'bg-brand',
    trendUp:   'text-status-success',
    trendDown: 'text-status-failed',
  },
  green: {
    iconBg:   'bg-status-success-bg text-status-success',
    glowBg:   'bg-status-success',
    trendUp:   'text-status-success',
    trendDown: 'text-status-failed',
  },
  red: {
    iconBg:   'bg-status-failed-bg text-status-failed',
    glowBg:   'bg-status-failed',
    trendUp:   'text-status-success',
    trendDown: 'text-status-failed',
  },
  blue: {
    iconBg:   'bg-blue-500/10 text-blue-400',
    glowBg:   'bg-blue-500',
    trendUp:   'text-status-success',
    trendDown: 'text-status-failed',
  },
}

/**
 * @param {{
 *   icon: string,
 *   value: string | number,
 *   label: string,
 *   trend?: { dir: 'up' | 'down', label: string },
 *   variant?: 'orange' | 'green' | 'red' | 'blue',
 *   className?: string,
 * }} props
 */
export default function StatCard({
  icon,
  value,
  label,
  trend,
  variant = 'orange',
  className,
}) {
  const v = VARIANT_MAP[variant] ?? VARIANT_MAP.orange

  return (
    <div
      className={cn(
        'relative overflow-hidden rounded-2xl border border-surface-border bg-surface-card p-6',
        'transition-all duration-200 hover:-translate-y-0.5 hover:border-brand/30',
        'animate-fade-up',
        className,
      )}
    >
      {/* Ambient glow orb */}
      <div
        className={cn(
          'pointer-events-none absolute -right-8 -top-8 h-28 w-28 rounded-full opacity-[0.07]',
          v.glowBg,
        )}
      />

      {/* Icon */}
      <div className={cn('mb-3.5 flex h-10 w-10 items-center justify-center rounded-xl text-xl', v.iconBg)}>
        {icon}
      </div>

      {/* Value */}
      <p className="font-display text-3xl font-extrabold leading-none tracking-tight text-ink">
        {value}
      </p>

      {/* Label */}
      <p className="mt-1.5 text-[13px] font-medium text-ink-muted">{label}</p>

      {/* Trend */}
      {trend && (
        <p className={cn('mt-2.5 flex items-center gap-1 text-xs font-semibold', v[`trend${trend.dir === 'up' ? 'Up' : 'Down'}`])}>
          {trend.dir === 'up' ? '↑' : '↓'} {trend.label}
        </p>
      )}
    </div>
  )
}
