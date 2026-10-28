/**
 * pages/DashboardPage.jsx
 *
 * Reads real posts + stats from the outlet context (MainLayout → usePosts).
 * Shows loading skeleton and inline error state.
 */

import { useOutletContext } from 'react-router-dom'
import { useAuth }       from '@/context/AuthContext'
import PageHeader        from '@/components/ui/PageHeader'
import StatCard          from '@/components/ui/StatCard'
import ActivityChart     from '@/components/charts/ActivityChart'
import PlatformReach     from '@/components/charts/PlatformReach'
import RecentPosts       from '@/components/RecentPosts'
import AlertBanner       from '@/components/ui/AlertBanner'
import Spinner           from '@/components/ui/Spinner'

export default function DashboardPage() {
  const { user }                         = useAuth()
  const { posts, loading, error, stats } = useOutletContext()

  const greeting = user?.username ? `Welcome back, ${user.username}` : 'Welcome back'

  return (
    <div>
      <PageHeader
        title="Dashboard"
        subtitle={`${greeting} — here's your content overview`}
      />

      {error && (
        <AlertBanner type="error" message={error} className="mb-6" />
      )}

      {/* Stats */}
      <div className="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
        <StatCard
          icon="📊"
          value={loading ? '—' : stats.total}
          label="Total Posts"
          variant="orange"
          className="stagger-1"
        />
        <StatCard
          icon="✅"
          value={loading ? '—' : stats.success}
          label="Successful Posts"
          variant="green"
          className="stagger-2"
        />
        <StatCard
          icon="❌"
          value={loading ? '—' : stats.failed}
          label="Failed Posts"
          variant="red"
          className="stagger-3"
        />
        <StatCard
          icon="🔗"
          value="3"
          label="Connected Accounts"
          variant="blue"
          className="stagger-4"
        />
      </div>

      {/* Charts */}
      <div className="mb-6 grid grid-cols-1 gap-5 xl:grid-cols-[1fr_320px]">
        <ActivityChart posts={posts} />
        <PlatformReach posts={posts} />
      </div>

      {/* Recent posts */}
      {loading ? (
        <div className="flex items-center justify-center py-20">
          <div className="flex flex-col items-center gap-3">
            <Spinner className="h-7 w-7" />
            <p className="text-sm text-ink-muted">Loading your posts…</p>
          </div>
        </div>
      ) : (
        <RecentPosts posts={posts} />
      )}
    </div>
  )
}
