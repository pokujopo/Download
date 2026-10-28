export default function InfoBanner({ children }) {
  return (
    <div
      className="mb-6 flex items-center gap-3 rounded-xl border border-blue-500/20
                 bg-blue-500/[0.08] px-4 py-3.5 text-sm text-blue-300"
    >
      <span className="flex-shrink-0 text-lg">ℹ</span>
      {children}
    </div>
  )
}
