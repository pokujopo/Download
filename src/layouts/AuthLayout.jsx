/**
 * layouts/AuthLayout.jsx
 *
 * Wraps login and register pages with a centered card layout.
 * Redirects already-authenticated users to the dashboard.
 */

import { Navigate, Outlet } from 'react-router-dom'
import { useAuth } from '@/context/AuthContext'

export default function AuthLayout() {
  const { isAuthenticated, loading } = useAuth()

  if (!loading && isAuthenticated) {
    return <Navigate to="/dashboard" replace />
  }

  return (
    <div className="flex min-h-screen items-center justify-center bg-surface-bg px-4 py-12">
      {/* Background glow */}
      <div className="pointer-events-none fixed inset-0 overflow-hidden">
        <div className="absolute left-1/2 top-1/3 h-[500px] w-[500px] -translate-x-1/2 -translate-y-1/2 rounded-full bg-brand/10 blur-[120px]" />
      </div>

      <div className="relative w-full max-w-md">
        {/* Logo */}
        <div className="mb-8 flex items-center justify-center gap-3">
          <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-brand to-brand-dark font-display text-xl font-black text-white shadow-brand">
            Z
          </div>
          <span className="font-display text-2xl font-extrabold tracking-tight text-ink">
            Zynk<span className="text-brand">Post</span>
          </span>
        </div>

        {/* Page content */}
        <Outlet />
      </div>
    </div>
  )
}
