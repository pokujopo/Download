/**
 * context/AuthContext.jsx
 *
 * Global authentication state.  Wraps the entire app so any component
 * can call useAuth() to read the current user or trigger auth actions.
 */

import { createContext, useContext, useState, useEffect, useCallback } from 'react'
import { login as apiLogin, register as apiRegister, logout as apiLogout, getMe } from '@/services/authService'
import { tokenStorage } from '@/services/authService'

const AuthContext = createContext(null)

export function AuthProvider({ children }) {
  const [user,        setUser]        = useState(() => tokenStorage.getUser())
  const [loading,     setLoading]     = useState(true)   // bootstrapping
  const [authError,   setAuthError]   = useState(null)

  // ── Bootstrap: verify stored token is still valid ──────────────────────
  useEffect(() => {
    const bootstrap = async () => {
      if (!tokenStorage.isAuthenticated()) {
        setLoading(false)
        return
      }
      try {
        const freshUser = await getMe()
        setUser(freshUser)
        // Refresh stored user object in case fields changed
        localStorage.setItem('user', JSON.stringify(freshUser))
      } catch {
        // Token expired or invalid — clear everything
        tokenStorage.clear()
        setUser(null)
      } finally {
        setLoading(false)
      }
    }
    bootstrap()
  }, [])

  // ── Actions ────────────────────────────────────────────────────────────
  const login = useCallback(async (credentials) => {
    setAuthError(null)
    const result = await apiLogin(credentials)
    setUser(result.user)
    return result
  }, [])

  const register = useCallback(async (payload) => {
    setAuthError(null)
    const result = await apiRegister(payload)
    setUser(result.user)
    return result
  }, [])

  const logout = useCallback(async () => {
    await apiLogout()
    setUser(null)
  }, [])

  const value = {
    user,
    loading,
    authError,
    setAuthError,
    isAuthenticated: !!user,
    login,
    register,
    logout,
  }

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>
}

export function useAuth() {
  const ctx = useContext(AuthContext)
  if (!ctx) throw new Error('useAuth must be used inside <AuthProvider>')
  return ctx
}
