
import axios from 'axios'

const BASE_URL =
  import.meta.env.VITE_API_BASE_URL ||
  'https://1080-196-249-92-243.ngrok-free.app'

const apiClient = axios.create({
  baseURL: BASE_URL,
  headers: {
    'Content-Type': 'application/json',
    Accept: 'application/json',
  },
  timeout: 30_000,
  withCredentials: true,
})

apiClient.interceptors.request.use(
  (config) => {
    const token =
      localStorage.getItem('access_token')

    if (token) {
      config.headers = config.headers || {}

      config.headers.Authorization =
        `Bearer ${token}`
    }

    if (config.data instanceof FormData) {
      delete config.headers['Content-Type']
      delete config.headers['content-type']
    }

    return config
  },
  (error) => Promise.reject(error),
)

let isRefreshing = false
let refreshQueue = []

function processQueue(error, token = null) {
  refreshQueue.forEach(
    ({ resolve, reject }) => {
      if (error) {
        reject(error)
      } else {
        resolve(token)
      }
    },
  )

  refreshQueue = []
}

function clearAuthAndRedirect() {
  localStorage.removeItem('access_token')
  localStorage.removeItem('user')

  if (window.location.pathname !== '/login') {
    window.location.href = '/login'
  }
}

apiClient.interceptors.response.use(
  (response) => response,

  async (error) => {
    const original = error.config

    if (!original) {
      return Promise.reject(error)
    }

    const is401 =
      error.response?.status === 401

    const isRefreshRequest =
      original.url?.includes('/api/auth/refresh')

    if (
      !is401 ||
      original._retry ||
      isRefreshRequest
    ) {
      return Promise.reject(error)
    }

    const accessToken =
      localStorage.getItem('access_token')

    if (!accessToken) {
      clearAuthAndRedirect()
      return Promise.reject(error)
    }

    if (isRefreshing) {
      return new Promise(
        (resolve, reject) => {
          refreshQueue.push({
            resolve,
            reject,
          })
        },
      ).then((newToken) => {
        original.headers =
          original.headers || {}

        original.headers.Authorization =
          `Bearer ${newToken}`

        return apiClient(original)
      })
    }

    original._retry = true
    isRefreshing = true

    try {
      const { data } = await axios.post(
        `${BASE_URL}/api/auth/refresh`,
        {},
        {
          withCredentials: true,
          headers: {
            Accept: 'application/json',
          },
        },
      )

      const newAccessToken =
        data?.token

      if (!newAccessToken) {
        throw new Error(
          'Refresh response did not contain an access token.',
        )
      }

      localStorage.setItem(
        'access_token',
        newAccessToken,
      )

      if (data?.user) {
        localStorage.setItem(
          'user',
          JSON.stringify(data.user),
        )
      }

      processQueue(
        null,
        newAccessToken,
      )

      original.headers =
        original.headers || {}

      original.headers.Authorization =
        `Bearer ${newAccessToken}`

      return apiClient(original)
    } catch (refreshError) {
      processQueue(
        refreshError,
        null,
      )

      clearAuthAndRedirect()

      return Promise.reject(
        refreshError,
      )
    } finally {
      isRefreshing = false
    }
  },
)

export default apiClient