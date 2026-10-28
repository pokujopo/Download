export default function SuccessBanner() {
  return (
    <div
      className="mt-5 flex items-center gap-4 rounded-xl border border-status-success/25
                 bg-status-success-bg px-5 py-4 animate-fade-up"
    >
      <span className="text-3xl">🎉</span>
      <div>
        <p className="font-display text-[15px] font-bold text-status-success">
          Post published successfully!
        </p>
        <p className="mt-0.5 text-sm text-ink-muted">
          Your content is now live on the selected platforms.
        </p>
      </div>
    </div>
  )
}
