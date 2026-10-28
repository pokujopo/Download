/**
 * components/charts/PlatformReach.jsx
 *
 * Derives per-platform success rates from real posts.
 */

import Card, { CardHeader } from '@/components/ui/Card'

const PLATFORM_META = {
  YouTube:  { color: '#FF0000', icon: '▶', bg: 'rgba(255,0,0,0.12)'      },
  Facebook: { color: '#1877F2', icon: 'f', bg: 'rgba(24,119,242,0.12)'   },
  X:        { color: '#E7E9EA', icon: '𝕏', bg: 'rgba(231,233,234,0.10)'  },
}

function buildReachData(posts = []) {
  const counts = { YouTube: { total: 0, success: 0 }, Facebook: { total: 0, success: 0 }, X: { total: 0, success: 0 } }

  posts.forEach((post) => {
    ;(post.platforms || []).forEach((p) => {
      if (counts[p]) {
        counts[p].total   += 1
        if (post.status === 'success') counts[p].success += 1
      }
    })
  })

  return Object.entries(PLATFORM_META).map(([name, meta]) => ({
    name,
    ...meta,
    pct: counts[name].total > 0
      ? Math.round((counts[name].success / counts[name].total) * 100)
      : 0,
    total: counts[name].total,
  }))
}

export default function PlatformReach({ posts = [] }) {
  const data = buildReachData(posts)

  return (
    <Card>
      <CardHeader title="Platform Reach" badge="Success rate" />

      <div className="flex flex-col gap-4">
        {data.map((p) => (
          <div key={p.name} className="flex items-center gap-3">
            <div
              className="flex h-8 w-8 flex-shrink-0 items-center justify-center
                         rounded-[9px] text-[13px] font-black"
              style={{ background: p.bg, color: p.color }}
            >
              {p.icon}
            </div>
            <div className="flex-1">
              <div className="mb-1 flex items-center justify-between">
                <p className="text-[13px] font-semibold text-ink">{p.name}</p>
                <span className="text-[11px] text-ink-muted">{p.total} posts</span>
              </div>
              <div className="h-1 overflow-hidden rounded-full bg-white/[0.07]">
                <div
                  className="h-full rounded-full transition-all duration-1000"
                  style={{ width: `${p.pct}%`, background: p.color }}
                />
              </div>
            </div>
            <span
              className="w-10 flex-shrink-0 text-right text-[12px] font-bold"
              style={{ color: p.color }}
            >
              {p.pct}%
            </span>
          </div>
        ))}
      </div>
    </Card>
  )
}
