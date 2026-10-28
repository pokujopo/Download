/**
 * components/RecentPosts.jsx
 *
 * Renders real API posts. Normalises both camelCase (mock) and snake_case
 * (Django API) field names so nothing breaks.
 */

import { useNavigate } from 'react-router-dom'
import Card, { CardHeader }          from '@/components/ui/Card'
import { StatusBadge, PlatformChip } from '@/components/ui/Badge'
import EmptyState                    from '@/components/ui/EmptyState'
import Button                        from '@/components/ui/Button'
import { formatDate, formatCount }   from '@/utils/formatters'

export default function RecentPosts({ posts = [] }) {
  const navigate = useNavigate()

  return (
    <Card>
      <CardHeader title="Recent Posts" badge={`${posts.length} total`} />

      {posts.length === 0 ? (
        <EmptyState
          icon="📭"
          title="No posts yet"
          subtitle="Create your first post and it'll appear here."
          action={
            <Button size="sm" onClick={() => navigate('/create')}>
              ✦ Create Post
            </Button>
          }
        />
      ) : (
        <div className="flex flex-col gap-2.5">
          {posts.map((post, i) => (
            <PostRow key={post.id} post={post} index={i} />
          ))}
        </div>
      )}
    </Card>
  )
}

function PostRow({ post, index }) {
  // Support both API (snake_case) and local (camelCase) shapes
  const date      = post.created_at || post.date
  const fileType  = post.file_type  || post.type || 'image'
  const views     = post.views      || 0
  const platforms = post.platforms  || []

  return (
    <div
      className="flex items-center gap-3.5 rounded-[14px] border border-surface-border
                 bg-white/[0.03] px-4 py-3.5 transition-all duration-200
                 hover:border-brand/20 hover:bg-white/[0.05] animate-fade-up"
      style={{ animationDelay: `${index * 50}ms` }}
    >
      {/* Thumb */}
      <div
        className="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-xl
                   bg-gradient-to-br from-brand/20 to-brand-dark/10 text-2xl"
      >
        {fileType === 'video' ? '🎬' : '🖼️'}
      </div>

      {/* Info */}
      <div className="min-w-0 flex-1">
        <p className="truncate text-[13px] font-semibold text-ink">{post.caption}</p>
        <div className="mt-1 flex flex-wrap items-center gap-1.5">
          {date && (
            <span className="text-[11px] text-ink-faint">{formatDate(date)}</span>
          )}
          {platforms.map((p) => (
            <PlatformChip key={p} platform={p} />
          ))}
        </div>
      </div>

      {/* Views */}
      {views > 0 && (
        <span className="hidden flex-shrink-0 text-xs text-ink-muted sm:block">
          👁 {formatCount(views)}
        </span>
      )}

      <StatusBadge status={post.status} />
    </div>
  )
}
