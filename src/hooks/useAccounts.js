
import { useCallback, useEffect, useMemo, useState } from 'react'
import apiClient from '@/services/apiClient'
import { PLATFORMS, PLATFORM_KEYS } from '@/data/platforms'

function normalizeProvider(provider) {
  return String(provider || '').toLowerCase()
}

function providerMatches(provider, platform) {
  return (
    normalizeProvider(provider) ===
    String(platform).toLowerCase()
  )
}

function createEmptyAccounts() {
  return PLATFORM_KEYS.reduce((result, platform) => {
    const meta = PLATFORMS[platform]

    result[platform] = {
      id: null,
      platform,
      description: meta?.description || '',
      connected: false,
      username: null,
      posts: 0,
      followers: null,
      status: null,
    }

    return result
  }, {})
}

function mapAccounts(apiAccounts) {
  const accounts = createEmptyAccounts()

  for (const apiAccount of apiAccounts) {
    const platform = PLATFORM_KEYS.find((key) =>
      providerMatches(
        apiAccount.provider,
        key
      )
    )

    if (!platform) {
      continue
    }

    const meta = PLATFORMS[platform]

    accounts[platform] = {
      id: apiAccount.id ?? null,
      platform,
      description: meta?.description || '',
      connected: true,
      username:
        apiAccount.username ||
        apiAccount.display_name ||
        null,
      posts: apiAccount.posts ?? 0,
      followers:
        apiAccount.followers ?? null,
      status:
        apiAccount.status ?? null,
    }
  }

  return accounts
}

export function useAccounts() {
  const [accounts, setAccounts] = useState(
    createEmptyAccounts
  )

  const [loading, setLoading] = useState(true)
  const [connecting, setConnecting] = useState(null)
  const [error, setError] = useState(null)

  const loadAccounts = useCallback(
    async () => {
      setError(null)

      try {
        const { data } =
          await apiClient.get(
            '/api/social-accounts'
          )

        setAccounts(
          mapAccounts(
            Array.isArray(data)
              ? data
              : []
          )
        )
      } catch (err) {
        console.error(
          'Failed to load social accounts:',
          err
        )

        setError(
          err.response?.data?.message ||
          err.message ||
          'Failed to load social accounts.'
        )
      } finally {
        setLoading(false)
      }
    },
    []
  )

  useEffect(() => {
    loadAccounts()
  }, [loadAccounts])

  const connect = useCallback(
    async (platform) => {
      setConnecting(platform)
      setError(null)

      try {
        let endpoint

        switch (platform) {
          case 'Facebook':
            endpoint =
              '/api/social/facebook/connect'
            break

          case 'Instagram':
            endpoint =
              '/api/social/instagram/connect'
            break

          default:
            throw new Error(
              `${platform} OAuth is not available yet.`
            )
        }

        const { data } =
          await apiClient.get(endpoint)

        if (!data?.url) {
          throw new Error(
            `${platform} OAuth URL was not returned.`
          )
        }

        /*
         * Start the real browser OAuth flow.
         */
        window.location.assign(
          data.url
        )
      } catch (err) {
        console.error(
          `Failed to connect ${platform}:`,
          err
        )

        setError(
          err.response?.data?.message ||
          err.message ||
          `Failed to connect ${platform}.`
        )

        setConnecting(null)
      }
    },
    []
  )

  const toggle = useCallback(
    (platform) => {
      const account =
        accounts[platform]

      if (!account) {
        return
      }

      if (account.connected) {
        /*
         * Disconnect endpoint is intentionally
         * not implemented yet.
         */
        setError(
          `${platform} disconnect is not available yet.`
        )

        return
      }

      connect(platform)
    },
    [accounts, connect]
  )

  const connectedCount =
    useMemo(
      () =>
        Object.values(accounts).filter(
          (account) =>
            account.connected
        ).length,
      [accounts]
    )

  return {
    accounts,
    loading,
    connecting,
    connectedCount,
    error,
    toggle,
    reload: loadAccounts,
  }
}