import { cn } from '@/utils/cn'

/**
 * Base card shell used throughout the dashboard.
 */
export default function Card({ className, children, ...props }) {
  return (
    <div
      className={cn(
        'rounded-2xl border border-surface-border bg-surface-card p-6 animate-fade-up',
        className,
      )}
      {...props}
    >
      {children}
    </div>
  )
}

export function CardHeader({ title, badge, action }) {
  return (
    <div className="mb-5 flex items-center justify-between">
      <h3 className="font-display text-[15px] font-bold text-ink">{title}</h3>
      <div className="flex items-center gap-2">
        {badge && (
          <span className="rounded-full bg-brand-faint px-2.5 py-1 text-[11px] font-semibold text-brand">
            {badge}
          </span>
        )}
        {action}
      </div>
    </div>
  )
}
