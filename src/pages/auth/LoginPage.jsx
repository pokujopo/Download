/**
 * pages/auth/LoginPage.jsx
 */

import { useState } from 'react'
import { Link, useNavigate, useLocation } from 'react-router-dom'
import { useAuth } from '@/context/AuthContext'
import Button from '@/components/ui/Button'
import AlertBanner from '@/components/ui/AlertBanner'

export default function LoginPage() {
  const { login } = useAuth()
  const navigate  = useNavigate()
  const location  = useLocation()

  const redirectTo = location.state?.from?.pathname || '/dashboard'

  const [form,    setForm]    = useState({ email: '', password: '' })
  const [loading, setLoading] = useState(false)
  const [error,   setError]   = useState(null)

  const handleChange = (e) => {
    setForm((prev) => ({ ...prev, [e.target.name]: e.target.value }))
    setError(null)
  }

  const handleSubmit = async (e) => {
    e.preventDefault()
    if (!form.email || !form.password) {
      setError('Please enter your email and password.')
      return
    }
    setLoading(true)
    setError(null)
    try {
      await login({ email: form.email, password: form.password })
      navigate(redirectTo, { replace: true })
    } catch (err) {
      const msg = err.response?.data?.message
            || err.response?.data?.detail
            || 'Invalid credentials. Please try again.'
      setError(msg)
    } finally {
      setLoading(false)
    }
  }

  return (
    <div className="rounded-2xl border border-surface-border bg-surface-card p-8 shadow-card">
      <div className="mb-6 text-center">
        <h1 className="font-display text-2xl font-bold text-ink">Welcome back</h1>
        <p className="mt-1.5 text-sm text-ink-muted">Sign in to your ZynkPost account</p>
      </div>

      {error && <AlertBanner type="error" message={error} className="mb-5" />}

      <form onSubmit={handleSubmit} noValidate className="flex flex-col gap-4">
        <div>
          <label className="mb-1.5 block text-[11px] font-semibold uppercase tracking-widest text-ink-muted">
            Email
          </label>
          <input
            type="email"
            name="email"
            value={form.email}
            onChange={handleChange}
            placeholder="you@example.com"
            autoComplete="email"
            required
            className="w-full rounded-xl border border-surface-border bg-white/[0.04]
                       px-4 py-3 font-body text-sm text-ink placeholder-ink-faint
                       outline-none transition-colors focus:border-brand/50"
          />
        </div>

        <div>
          <label className="mb-1.5 block text-[11px] font-semibold uppercase tracking-widest text-ink-muted">
            Password
          </label>
          <input
            type="password"
            name="password"
            value={form.password}
            onChange={handleChange}
            placeholder="••••••••"
            autoComplete="current-password"
            required
            className="w-full rounded-xl border border-surface-border bg-white/[0.04]
                       px-4 py-3 font-body text-sm text-ink placeholder-ink-faint
                       outline-none transition-colors focus:border-brand/50"
          />
        </div>

        <Button
          type="submit"
          variant="primary"
          size="lg"
          disabled={loading}
          className="mt-1"
        >
          {loading ? '⏳ Signing in…' : '→ Sign In'}
        </Button>
      </form>

      <p className="mt-6 text-center text-sm text-ink-muted">
        Don't have an account?{' '}
        <Link to="/register" className="font-semibold text-brand hover:text-brand-light transition-colors">
          Create one free
        </Link>
      </p>
    </div>
  )
}
