/**
 * components/Sidebar.jsx
 *
 * Shows real user info from AuthContext.
 */

import { NavLink } from 'react-router-dom'
import { useAuth } from '@/context/AuthContext'
import { cn } from '@/utils/cn'

const NAV_ITEMS = [
  { to: '/dashboard', icon: '⊞', label: 'Dashboard'          },
  { to: '/create',    icon: '✦', label: 'Create Post'        },
  { to: '/accounts',  icon: '⬡', label: 'Connected Accounts' },
]

function getInitials(name = '') {
  return name.slice(0, 2).toUpperCase() || 'ZP'
}

export default function Sidebar({ open, onClose }) {
  const { user } = useAuth()

  return (
    <aside
      className={cn(
        'fixed top-0 left-0 z-[100] flex h-full w-[240px] flex-col',
        'bg-surface-card2 border-r border-surface-border',
        'transition-transform duration-300 ease-smooth',
        open ? 'translate-x-0' : '-translate-x-full lg:translate-x-0',
      )}
    >
      {/* Logo */}
      <div className="flex items-center gap-3 border-b border-surface-border px-6 py-7">
        <div
          className="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-xl
                     bg-gradient-to-br from-brand to-brand-light font-display text-lg
                     font-black text-white shadow-brand"
        >
          Z
        </div>
        <span className="font-display text-lg font-extrabold tracking-tight text-ink">
          Zynk<span className="text-brand">Post</span>
        </span>
      </div>

      {/* Nav */}
      <nav className="flex flex-1 flex-col gap-1 px-3 py-5">
        <p className="mb-2 px-3 text-[10px] font-semibold uppercase tracking-[1.5px] text-ink-muted">
          Main Menu
        </p>
        {NAV_ITEMS.map(({ to, icon, label }) => (
          <NavLink
            key={to}
            to={to}
            onClick={onClose}
            className={({ isActive }) =>
              cn(
                'nav-item relative flex items-center gap-3 rounded-xl border px-3.5 py-[11px]',
                'text-sm font-medium transition-all duration-200',
                isActive
                  ? 'nav-active-bar border-brand/20 bg-brand-faint text-brand'
                  : 'border-transparent text-ink-muted hover:bg-surface-hover hover:text-ink',
              )
            }
          >
            <span className="flex w-5 items-center justify-center text-[18px]">{icon}</span>
            {label}
          </NavLink>
        ))}
      </nav>

      {/* User footer */}
      <div className="border-t border-surface-border p-3">
        <div className="flex items-center gap-3 rounded-xl bg-white/[0.04] px-3 py-2.5">
          <div
            className="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full
                       bg-gradient-to-br from-brand to-brand-dark font-display text-xs
                       font-bold text-white"
          >
            {getInitials(user?.username)}
          </div>
          <div className="min-w-0 flex-1">
            <p className="truncate text-[13px] font-semibold text-ink">
              {user?.username || 'User'}
            </p>
            <p className="truncate text-[11px] text-ink-muted">{user?.email}</p>
          </div>
        </div>
      </div>
    </aside>
  )
}
