/**
 * components/charts/ActivityChart.jsx
 *
 * Derives weekly activity from REAL posts array.
 * Falls back to zeroes if no posts yet.
 */

import Card, { CardHeader } from '@/components/ui/Card'

const DAYS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']

function buildWeeklyData(posts = []) {
  // Initialise all 7 days to zero
  const map = Object.fromEntries(DAYS.map((d) => [d, { success: 0, failed: 0 }]))

  const now       = new Date()
  const weekAgo   = new Date(now)
  weekAgo.setDate(now.getDate() - 6)

  posts.forEach((post) => {
    const d = new Date(post.created_at || post.date)
    if (d >= weekAgo && d <= now) {
      const day = DAYS[d.getDay()]
      if (post.status === 'success') map[day].success += 1
      else if (post.status === 'failed') map[day].failed += 1
    }
  })

  // Return Mon→Sun order for display
  const ordered = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun']
  return ordered.map((day) => ({ day, ...map[day] }))
}

export default function ActivityChart({ posts = [] }) {
  const data = buildWeeklyData(posts)
  const max  = Math.max(...data.map((d) => d.success + d.failed), 1)

  return (
    <Card>
      <CardHeader title="Posting Activity" badge="This Week" />

      <div className="flex h-40 items-end gap-2.5">
        {data.map((d, i) => {
          const successPct = (d.success / max) * 100
          const failedPct  = (d.failed  / max) * 100

          return (
            <div key={i} className="group flex flex-1 flex-col items-center gap-1.5">
              {/* Tooltip */}
              <div className="pointer-events-none mb-1 hidden rounded-lg border border-surface-border
                              bg-surface-card px-2.5 py-1.5 text-center text-[11px]
                              group-hover:block shadow-card">
                <p className="font-semibold text-ink">{d.success + d.failed} posts</p>
                {d.success > 0 && <p className="text-status-success">{d.success} ok</p>}
                {d.failed  > 0 && <p className="text-status-failed">{d.failed} failed</p>}
              </div>

              {/* Bars */}
              <div className="flex w-full flex-1 items-end gap-0.5">
                <div
                  className="flex-1 rounded-t-md bg-gradient-to-t from-brand to-brand-light
                             transition-all duration-500"
                  style={{ height: `${successPct}%`, minHeight: successPct > 0 ? '4px' : '0' }}
                />
                <div
                  className="flex-1 rounded-t-md bg-status-failed/60 transition-all duration-500"
                  style={{ height: `${failedPct}%`, minHeight: failedPct > 0 ? '4px' : '0' }}
                />
              </div>

              <span className="text-[11px] text-ink-muted">{d.day}</span>
            </div>
          )
        })}
      </div>

      <div className="mt-4 flex gap-5">
        <div className="flex items-center gap-2 text-xs text-ink-muted">
          <span className="h-2 w-2 rounded-full bg-brand" /> Successful
        </div>
        <div className="flex items-center gap-2 text-xs text-ink-muted">
          <span className="h-2 w-2 rounded-full bg-status-failed/60" /> Failed
        </div>
      </div>
    </Card>
  )
}
