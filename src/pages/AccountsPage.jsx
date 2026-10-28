import { useAccounts } from '@/hooks/useAccounts'
import PageHeader from '@/components/ui/PageHeader'
import InfoBanner from '@/components/ui/InfoBanner'
import AccountCard from '@/components/AccountCard'
import { PLATFORM_KEYS } from '@/data/platforms'

export default function AccountsPage() {
  const {
    accounts,
    loading,
    connecting,
    connectedCount,
    error,
    toggle,
    reload,
  } = useAccounts()

  return (
    <div>

      <PageHeader
        title="Connected Accounts"
        subtitle={
          `${connectedCount} of ${PLATFORM_KEYS.length} platforms connected`
        }
      />

      <InfoBanner>
        Connect your social media accounts to enable automatic
        publishing when you create a post.
      </InfoBanner>


      {/* Error */}
      {error && (
        <div
          className="mb-5 rounded-xl border border-status-failed/20
                     bg-status-failed-bg px-4 py-3 text-sm
                     text-status-failed"
        >
          {error}
        </div>
      )}


      {/* Refresh */}
      <div className="mb-5 flex justify-end">

        <button
          type="button"
          onClick={reload}
          disabled={loading}
          className="text-sm font-semibold text-brand
                     transition hover:opacity-80
                     disabled:opacity-50"
        >
          {loading ? 'Loading…' : '↻ Refresh'}
        </button>

      </div>


      {/* Accounts */}
      <div className="grid grid-cols-1 gap-5 sm:grid-cols-2 xl:grid-cols-3">

        {PLATFORM_KEYS.map((key, index) => (

          <div
            key={key}
            style={{
              animationDelay: `${index * 80}ms`,
            }}
          >
            <AccountCard
              account={accounts[key]}
              connecting={connecting === key}
              onToggle={() => toggle(key)}
            />
          </div>

        ))}

      </div>

    </div>
  )
}