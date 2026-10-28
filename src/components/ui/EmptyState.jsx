export default function EmptyState({ icon = '📭', title, subtitle, action }) {
  return (
    <div className="flex flex-col items-center justify-center gap-3 py-16 text-center">
      <span className="text-5xl opacity-30">{icon}</span>
      <p className="font-display text-lg font-bold text-ink-muted">{title}</p>
      {subtitle && <p className="max-w-xs text-sm text-ink-muted/70">{subtitle}</p>}
      {action && <div className="mt-2">{action}</div>}
    </div>
  )
}
