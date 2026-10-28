/**
 * components/Topbar.jsx
 *
 * Shows the logged-in user's initials and a real logout button.
 */

import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useAuth } from '@/context/AuthContext'
import Spinner from '@/components/ui/Spinner'

function getInitials(name = '') {
  return name.slice(0, 2).toUpperCase() || 'ZP'
}

export default function Topbar({ onMenuClick }) {
  const { user, logout }      = useAuth()
  const navigate               = useNavigate()
  const [loggingOut, setOut]   = useState(false)

  const handleLogout = async () => {
    setOut(true)
    try {
      await logout()
      navigate('/login', { replace: true })
    } finally {
      setOut(false)
    }
  }

  return (
    <header
      className="fixed left-0 right-0 top-0 z-[90] flex h-16 items-center gap-4 border-b
                 border-surface-border bg-surface-bg/80 px-5 backdrop-blur-xl lg:left-[240px] lg:px-8"
    >
      {/* Mobile hamburger */}
      <button
        onClick={onMenuClick}
        className="flex items-center justify-center rounded-lg p-1 text-xl text-ink
                   transition-colors hover:text-brand lg:hidden"
        aria-label="Open sidebar"
      >
        ☰
      </button>

      {/* Search */}
      <div
        className="flex flex-1 items-center gap-2.5 rounded-xl border border-surface-border
                   bg-surface-card px-3.5 py-2 transition-colors focus-within:border-brand/40
                   max-w-sm"
      >
        <span className="text-base text-ink-muted">⌕</span>
        <input
          type="text"
          placeholder="Search posts, platforms…"
          className="w-full bg-transparent font-body text-sm text-ink placeholder-ink-muted outline-none"
        />
      </div>

      {/* Right actions */}
      <div className="ml-auto flex items-center gap-2.5">
        {/* Logged-in user display */}
        {user && (
          <span className="hidden text-sm font-medium text-ink-muted sm:block">
            {user.username}
          </span>
        )}

        {/* Avatar */}
        <div
          className="flex h-9 w-9 cursor-default items-center justify-center rounded-xl
                     bg-gradient-to-br from-brand to-brand-dark font-display text-sm
                     font-bold text-white"
          title={user?.email}
        >
          {getInitials(user?.username)}
        </div>

        {/* Logout */}
        <button
          onClick={handleLogout}
          disabled={loggingOut}
          title="Sign out"
          className="flex h-9 w-9 items-center justify-center rounded-xl border border-surface-border
                     bg-surface-card text-base text-ink-muted transition-all
                     hover:border-status-failed/50 hover:text-status-failed disabled:opacity-50"
        >
          {loggingOut ? <Spinner className="h-3.5 w-3.5" /> : '⏻'}
        </button>
      </div>
    </header>
  )
}
