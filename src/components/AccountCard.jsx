import Spinner from '@/components/ui/Spinner'
import { PLATFORMS } from '@/data/platforms'
import { cn } from '@/utils/cn'

/**
 * @param {{
 *   account: object,
 *   connecting: boolean,
 *   onToggle: () => void,
 * }} props
 */
export default function AccountCard({
  account,
  connecting,
  onToggle,
}) {
  const meta = PLATFORMS[account.platform]

  const platformBg =
    account.platform === 'YouTube'
      ? 'rgba(255,0,0,0.12)'
      : account.platform === 'Facebook'
        ? 'rgba(24,119,242,0.12)'
        : 'rgba(231,233,234,0.08)'

  const isConnected = account.connected

  return (
    <div
      className={cn(
        'flex flex-col gap-4 rounded-2xl border bg-surface-card p-6 animate-fade-up',
        'transition-all duration-200 hover:-translate-y-0.5',
        isConnected
          ? 'border-status-success/20'
          : 'border-surface-border',
      )}
    >
      {/* Header */}
      <div className="flex items-center gap-3.5">

        <div
          className="flex h-12 w-12 flex-shrink-0 items-center justify-center
                     rounded-[14px] text-xl font-black"
          style={{
            background: platformBg,
            color: meta?.color,
          }}
        >
          {meta?.icon}
        </div>

        <div>

          <p className="font-display text-lg font-bold text-ink">
            {account.platform}
          </p>

          <div className="flex items-center gap-1.5 text-[12px] font-semibold">

            <span
              className={cn(
                'h-2 w-2 rounded-full',
                isConnected
                  ? 'bg-status-success shadow-[0_0_6px_#22C55E]'
                  : 'bg-ink-faint',
              )}
            />

            <span
              className={
                isConnected
                  ? 'text-status-success'
                  : 'text-ink-muted'
              }
            >
              {isConnected
                ? account.username || account.display_name || 'Connected'
                : 'Not connected'}
            </span>

          </div>
        </div>
      </div>


      {/* Description */}
      <p className="text-sm leading-relaxed text-ink-muted">
        {account.description}
      </p>


      {/* Connected account information */}
      {isConnected && (
        <div className="rounded-xl bg-white/[0.03] px-4 py-3">

          <div className="flex items-center justify-between">

            <span className="text-[11px] font-medium text-ink-muted">
              Status
            </span>

            <span
              className={cn(
                'text-[12px] font-semibold',
                account.status === 'active'
                  ? 'text-status-success'
                  : 'text-status-failed',
              )}
            >
              {account.status || 'connected'}
            </span>

          </div>

          {account.id && (
            <div className="mt-2 flex items-center justify-between">

              <span className="text-[11px] font-medium text-ink-muted">
                Account ID
              </span>

              <span className="text-[11px] font-medium text-ink">
                #{account.id}
              </span>

            </div>
          )}

        </div>
      )}


      {/* Action */}
      <button
        type="button"
        onClick={onToggle}
        disabled={connecting || isConnected}
        className={cn(
          'flex w-full items-center justify-center gap-2 rounded-xl border py-3',
          'font-display text-[14px] font-bold transition-all duration-200',
          'disabled:cursor-not-allowed disabled:opacity-60',
          isConnected
            ? 'border-surface-border bg-white/[0.03] text-ink-muted'
            : 'border-brand/25 bg-brand-faint text-brand hover:bg-brand hover:text-white',
        )}
      >
        {connecting ? (
          <>
            <Spinner />
            Connecting to {account.platform}…
          </>
        ) : isConnected ? (
          '✓ Connected'
        ) : (
          `⚡ Connect ${account.platform}`
        )}
      </button>

    </div>
  )
}