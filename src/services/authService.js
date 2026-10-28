
import apiClient from './apiClient'

const AUTH_BASE = '/api/auth'

export const tokenStorage = {
  save(token, user) {
    localStorage.setItem('access_token', token)
    localStorage.setItem('user', JSON.stringify(user))
  },

  clear() {
    localStorage.removeItem('access_token')
    localStorage.removeItem('user')
  },

  getAccess() {
    return localStorage.getItem('access_token')
  },

  getUser() {
    try {
      return JSON.parse(
        localStorage.getItem('user')
      )
    } catch {
      return null
    }
  },

  isAuthenticated() {
    return !!localStorage.getItem('access_token')
  },
}

export async function register(payload) {
  const { data } = await apiClient.post(
    `${AUTH_BASE}/register`,
    payload
  )

  tokenStorage.save(
    data.token,
    data.user
  )

  return {
    user: data.user,
    token: data.token,
  }
}

export async function login(credentials) {
  const { data } = await apiClient.post(
    `${AUTH_BASE}/login`,
    credentials
  )

  tokenStorage.save(
    data.token,
    data.user
  )

  return {
    user: data.user,
    token: data.token,
  }
}

export async function refresh() {
  const { data } = await apiClient.post(
    `${AUTH_BASE}/refresh`
  )

  tokenStorage.save(
    data.token,
    data.user
  )

  return {
    user: data.user,
    token: data.token,
  }
}

export async function getMe() {
  const { data } = await apiClient.get(
    `${AUTH_BASE}/me`
  )

  if (data.user) {
    localStorage.setItem(
      'user',
      JSON.stringify(data.user)
    )
  }

  return data.user || data
}

export async function logout() {
  try {
    await apiClient.post(
      `${AUTH_BASE}/logout`
    )
  } finally {
    tokenStorage.clear()
  }
}