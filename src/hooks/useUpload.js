
import { useState, useCallback, useRef } from 'react'
import { isVideoFile, isImageFile } from '@/utils/formatters'
import { createContent } from '@/services/postService'
import { PLATFORM_KEYS } from '@/data/platforms'

const buildInitialPlatforms = () =>
  Object.fromEntries(
    PLATFORM_KEYS.map((key) => [key, false]),
  )

const INITIAL_STATE = {
  file: null,
  previewUrl: null,
  fileType: null,
  caption: '',
  platforms: buildInitialPlatforms(),
  uploading: false,
  progress: 0,
  success: false,
  error: null,
  createdPost: null,
}

export function useUpload(accounts = {}, onSuccess) {
  const [state, setState] = useState(INITIAL_STATE)
  const prevPreviewUrl = useRef(null)

  const patch = useCallback(
    (partial) =>
      setState((prev) => ({
        ...prev,
        ...partial,
      })),
    [],
  )

  // ── File ──────────────────────────────────────────────────────────────

  const setFile = useCallback(
    (file) => {
      if (!file) return

      if (
        !isVideoFile(file) &&
        !isImageFile(file)
      ) {
        patch({
          error:
            'Only video and image files are supported.',
        })

        return
      }

      if (prevPreviewUrl.current) {
        URL.revokeObjectURL(
          prevPreviewUrl.current,
        )
      }

      const url = URL.createObjectURL(file)

      prevPreviewUrl.current = url

      patch({
        file,
        previewUrl: url,
        fileType: isVideoFile(file)
          ? 'video'
          : 'image',
        success: false,
        error: null,
        progress: 0,
        createdPost: null,
      })
    },
    [patch],
  )

  const clearFile = useCallback(() => {
    if (prevPreviewUrl.current) {
      URL.revokeObjectURL(
        prevPreviewUrl.current,
      )
    }

    prevPreviewUrl.current = null

    patch({
      file: null,
      previewUrl: null,
      fileType: null,
    })
  }, [patch])

  // ── Caption ──────────────────────────────────────────────────────────

  const setCaption = useCallback(
    (caption) => patch({ caption }),
    [patch],
  )

  // ── Platforms ────────────────────────────────────────────────────────

  const togglePlatform = useCallback(
    (platform) => {
      setState((prev) => ({
        ...prev,
        platforms: {
          ...prev.platforms,
          [platform]:
            !prev.platforms[platform],
        },
      }))
    },
    [],
  )

  const selectedPlatforms = Object.entries(
    state.platforms,
  )
    .filter(([, selected]) => selected)
    .map(([platform]) => platform)

  /*
   * Convert frontend platform names into
   * Laravel social_account IDs.
   */
  const destinations = selectedPlatforms
    .map((platform) => {
      const account = accounts[platform]

      if (!account?.id || !account.connected) {
        return null
      }

      return {
        social_account_id: account.id,
      }
    })
    .filter(Boolean)

  const hasInvalidDestination =
    selectedPlatforms.length !==
    destinations.length

  const isValid =
    !!state.file &&
    state.caption.trim().length > 0 &&
    selectedPlatforms.length > 0 &&
    !hasInvalidDestination

  // ── Submit ───────────────────────────────────────────────────────────

  const submit = useCallback(async () => {
    if (!isValid || state.uploading) {
      return
    }

    patch({
      uploading: true,
      success: false,
      error: null,
      progress: 0,
    })

    try {
      const content = await createContent(
        {
          file: state.file,
          caption: state.caption.trim(),
          destinations,
        },
        (percent) => {
          patch({
            progress: percent,
          })
        },
      )

      patch({
        uploading: false,
        success: true,
        progress: 100,
        createdPost: content,
      })

      onSuccess?.(content)

      setTimeout(() => {
        if (prevPreviewUrl.current) {
          URL.revokeObjectURL(
            prevPreviewUrl.current,
          )
        }

        prevPreviewUrl.current = null

        setState({
          ...INITIAL_STATE,
          platforms:
            buildInitialPlatforms(),
        })
      }, 3000)
    } catch (err) {
      const message =
        err.response?.data?.message ||
        err.response?.data?.errors?.media?.[0] ||
        err.response?.data?.errors?.caption?.[0] ||
        err.response?.data?.errors?.destinations?.[0] ||
        'Failed to create post. Please try again.'

      patch({
        uploading: false,
        error: message,
      })
    }
  }, [
    isValid,
    state.uploading,
    state.file,
    state.caption,
    destinations,
    patch,
    onSuccess,
  ])

  const reset = useCallback(() => {
    if (prevPreviewUrl.current) {
      URL.revokeObjectURL(
        prevPreviewUrl.current,
      )
    }

    prevPreviewUrl.current = null

    setState({
      ...INITIAL_STATE,
      platforms:
        buildInitialPlatforms(),
    })
  }, [])

  return {
    ...state,
    selectedPlatforms,
    destinations,
    hasInvalidDestination,
    isValid,

    setFile,
    clearFile,
    setCaption,
    togglePlatform,
    submit,
    reset,
  }
}