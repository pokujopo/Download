/**
 * components/ProtectedRoute.jsx
 *
 * Wraps all app routes.  If the user is not authenticated it redirects
 * to /login, preserving the attempted path so we can redirect back after login.
 */

import { Navigate, Outlet, useLocation } from 'react-router-dom'
import { useAuth } from '@/context/AuthContext'
import Spinner from '@/components/ui/Spinner'

export default function ProtectedRoute() {
  const { isAuthenticated, loading } = useAuth()
  const location = useLocation()

  // Show full-screen spinner while the auth bootstrap is running
  if (loading) {
    return (
      <div className="flex min-h-screen items-center justify-center bg-surface-bg">
        <div className="flex flex-col items-center gap-4">
          <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-gradient-to-br from-brand to-brand-dark font-display text-2xl font-black text-white shadow-brand">
            Z
          </div>
          <Spinner className="h-6 w-6" />
          <p className="text-sm text-ink-muted">Loading ZynkPost…</p>
        </div>
      </div>
    )
  }

  if (!isAuthenticated) {
    return <Navigate to="/login" state={{ from: location }} replace />
  }

  return <Outlet />
}
