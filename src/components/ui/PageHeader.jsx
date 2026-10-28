export default function PageHeader({ title, subtitle }) {
  return (
    <div className="mb-7">
      <h1 className="font-display text-2xl font-bold leading-tight tracking-tight text-ink lg:text-[28px]">
        {title}
      </h1>
      {subtitle && (
        <p className="mt-1 text-sm text-ink-muted">{subtitle}</p>
      )}
    </div>
  )
}
