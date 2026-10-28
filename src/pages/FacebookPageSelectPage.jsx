
import { useEffect, useState } from 'react'
import { useNavigate, useSearchParams } from 'react-router-dom'
import Spinner from '@/components/ui/Spinner'
import { tokenStorage } from '@/services/authService'

const API_BASE =
  import.meta.env.VITE_API_URL || 'http://localhost:8000/api'

export default function FacebookPageSelectPage() {
  const navigate = useNavigate()
  const [searchParams] = useSearchParams()

  const connection = searchParams.get('connection')

  const [pages, setPages] = useState([])
  const [loading, setLoading] = useState(true)
  const [selecting, setSelecting] = useState(null)
  const [error, setError] = useState(null)

  useEffect(() => {
    if (!connection) {
      setError('Facebook connection is missing.')
      setLoading(false)
      return
    }

    loadPages()
  }, [connection])

  async function loadPages() {
    const token = tokenStorage.getAccess()

    if (!token) {
      setError('Authentication required.')
      setLoading(false)
      return
    }

    try {
      const response = await fetch(
        `${API_BASE}/social/facebook/options?connection=${encodeURIComponent(
          connection
        )}`,
        {
          method: 'GET',
          headers: {
            Authorization: `Bearer ${token}`,
            Accept: 'application/json',
          },
        }
      )

      const data = await response.json().catch(() => null)

      if (!response.ok) {
        throw new Error(
          data?.message ||
            'Failed to load Facebook Pages.'
        )
      }

      setPages(
        Array.isArray(data?.pages)
          ? data.pages
          : []
      )
    } catch (err) {
      console.error(
        'Failed to load Facebook Pages:',
        err
      )

      setError(
        err.message ||
          'Failed to load Facebook Pages.'
      )
    } finally {
      setLoading(false)
    }
  }

  async function selectPage(pageId) {
    const token = tokenStorage.getAccess()

    if (!token) {
      setError('Authentication required.')
      return
    }

    setSelecting(pageId)
    setError(null)

    try {
      const response = await fetch(
        `${API_BASE}/social/facebook/select`,
        {
          method: 'POST',
          headers: {
            Authorization: `Bearer ${token}`,
            Accept: 'application/json',
            'Content-Type': 'application/json',
          },
          body: JSON.stringify({
            connection,
            page_id: pageId,
          }),
        }
      )

      const data = await response.json().catch(() => null)

      if (!response.ok) {
        throw new Error(
          data?.message ||
            'Failed to connect Facebook Page.'
        )
      }

      navigate('/settings/social-accounts', {
        replace: true,
        state: {
          facebookConnected: true,
          account: data?.social_account ?? null,
        },
      })
    } catch (err) {
      console.error(
        'Failed to connect Facebook Page:',
        err
      )

      setError(
        err.message ||
          'Failed to connect Facebook Page.'
      )

      setSelecting(null)
    }
  }

  return (
    <div className="mx-auto w-full max-w-2xl">
      <div className="rounded-2xl border border-surface-border bg-surface-card p-6">

        <div className="mb-6">
          <h1 className="font-display text-2xl font-bold text-ink">
            Select Facebook Page
          </h1>

          <p className="mt-2 text-sm leading-relaxed text-ink-muted">
            Choose the Facebook Page you want to connect.
          </p>
        </div>

        {error && (
          <div
            className="mb-5 rounded-xl border border-status-failed/20
                       bg-status-failed-bg px-4 py-3
                       text-sm text-status-failed"
          >
            {error}
          </div>
        )}

        {loading && (
          <div className="flex items-center justify-center py-10">
            <div className="flex items-center gap-3 text-sm text-ink-muted">
              <Spinner />
              Loading Facebook Pages…
            </div>
          </div>
        )}

        {!loading && !error && pages.length === 0 && (
          <div
            className="rounded-xl border border-surface-border
                       px-5 py-8 text-center"
          >
            <p className="font-semibold text-ink">
              No Facebook Pages available
            </p>

            <p className="mt-2 text-sm text-ink-muted">
              This Facebook account does not have an available
              Page to connect.
            </p>

            <button
              type="button"
              onClick={() =>
                navigate('/settings/social-accounts', {
                  replace: true,
                })
              }
              className="mt-5 rounded-xl bg-brand px-5 py-3
                         text-sm font-bold text-white
                         transition hover:opacity-90"
            >
              Back to Accounts
            </button>
          </div>
        )}

        {!loading && pages.length > 0 && (
          <div className="space-y-3">
            {pages.map((page) => {
              const isSelecting =
                selecting === String(page.id)

              const hasInstagram =
                Boolean(
                  page.instagram_business_account
                )

              return (
                <button
                  key={page.id}
                  type="button"
                  onClick={() =>
                    selectPage(String(page.id))
                  }
                  disabled={Boolean(selecting)}
                  className="
                    flex w-full items-center justify-between
                    rounded-xl border border-surface-border
                    p-4 text-left
                    transition-all duration-200
                    hover:border-brand/40
                    hover:bg-white/[0.03]
                    disabled:cursor-not-allowed
                    disabled:opacity-60
                  "
                >
                  <div className="min-w-0">
                    <p className="truncate font-display text-base font-bold text-ink">
                      {page.name}
                    </p>

                    <p className="mt-1 text-xs text-ink-muted">
                      Page ID: {page.id}
                    </p>

                    {hasInstagram && (
                      <p className="mt-2 text-xs font-semibold text-pink-500">
                        Instagram account available
                      </p>
                    )}
                  </div>

                  <div className="ml-4 flex flex-shrink-0 items-center">
                    {isSelecting ? (
                      <div className="flex items-center gap-2 text-sm font-semibold text-brand">
                        <Spinner />
                        Connecting…
                      </div>
                    ) : (
                      <span className="text-sm font-bold text-brand">
                        Connect
                      </span>
                    )}
                  </div>
                </button>
              )
            })}
          </div>
        )}

      </div>
    </div>
  )
}