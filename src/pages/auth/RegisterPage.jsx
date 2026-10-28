/**
 * pages/auth/RegisterPage.jsx
 */

import { useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { useAuth } from '@/context/AuthContext'
import Button from '@/components/ui/Button'
import AlertBanner from '@/components/ui/AlertBanner'

export default function RegisterPage() {
  const { register } = useAuth()
  const navigate     = useNavigate()

  const [form, setForm] = useState({
    name: '', email: '', password: '', password_confirmation: '',
  })
  const [loading, setLoading] = useState(false)
  const [error,   setError]   = useState(null)

  const handleChange = (e) => {
    setForm((prev) => ({ ...prev, [e.target.name]: e.target.value }))
    setError(null)
  }

  const handleSubmit = async (e) => {
    e.preventDefault()
    if (form.password !== form.password_confirmation) {
      setError('Passwords do not match.')
      return
    }
    setLoading(true)
    setError(null)
    try {
      await register(form)
      navigate('/dashboard', { replace: true })
    } catch (err) {
      // Surface the first field error or the top-level message
      const data = err.response?.data
      const msg  = data?.message
            || data?.errors?.email?.[0]
            || data?.errors?.name?.[0]
            || data?.errors?.password?.[0]
            || 'Registration failed. Please try again.'
      setError(msg)
    } finally {
      setLoading(false)
    }
  }

  return (
    <div className="rounded-2xl border border-surface-border bg-surface-card p-8 shadow-card">
      <div className="mb-6 text-center">
        <h1 className="font-display text-2xl font-bold text-ink">Create your account</h1>
        <p className="mt-1.5 text-sm text-ink-muted">Start publishing everywhere — free forever</p>
      </div>

      {error && <AlertBanner type="error" message={error} className="mb-5" />}

      <form onSubmit={handleSubmit} noValidate className="flex flex-col gap-4">
        <div>
          <label className="mb-1.5 block text-[11px] font-semibold uppercase tracking-widest text-ink-muted">
            name
          </label>
          <input
            type="text"
            name="name"
            value={form.name}
            onChange={handleChange}
            placeholder="yourhandle"
            autoComplete="name"
            required
            className="w-full rounded-xl border border-surface-border bg-white/[0.04]
                       px-4 py-3 font-body text-sm text-ink placeholder-ink-faint
                       outline-none transition-colors focus:border-brand/50"
          />
        </div>

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
            placeholder="Min 8 chars, 1 uppercase, 1 number"
            autoComplete="new-password"
            required
            className="w-full rounded-xl border border-surface-border bg-white/[0.04]
                       px-4 py-3 font-body text-sm text-ink placeholder-ink-faint
                       outline-none transition-colors focus:border-brand/50"
          />
        </div>

        <div>
          <label className="mb-1.5 block text-[11px] font-semibold uppercase tracking-widest text-ink-muted">
            Confirm Password
          </label>
          <input
            type="password"
            name="password_confirmation"
            value={form.password_confirmation}
            onChange={handleChange}
            placeholder="••••••••"
            autoComplete="new-password"
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
          {loading ? '⏳ Creating account…' : '✦ Create Account'}
        </Button>
      </form>

      <p className="mt-6 text-center text-sm text-ink-muted">
        Already have an account?{' '}
        <Link to="/login" className="font-semibold text-brand hover:text-brand-light transition-colors">
          Sign in
        </Link>
      </p>
    </div>
  )
}
