
/**
 * services/postService.js
 *
 * Laravel Once Post Anywhere content API.
 */

import apiClient from './apiClient'

const CONTENTS_BASE = '/api/contents'

/**
 * Fetch authenticated user's contents.
 */
export async function getContents(params = {}) {
  const { data } = await apiClient.get(
    CONTENTS_BASE,
    { params },
  )

  return data?.data ?? []
}

/**
 * Backward-compatible alias.
 */
export const getPosts = getContents

/**
 * Fetch a single content.
 */
export async function getContent(id) {
  const { data } = await apiClient.get(
    `${CONTENTS_BASE}/${id}`,
  )

  return data?.data ?? data?.content
}

/**
 * Create content.
 *
 * Laravel expects:
 *
 * caption
 * media[0][type]
 * media[0][file]
 * destinations[0][social_account_id]
 * destinations[0][scheduled_at]
 */
export async function createContent(
  {
    file,
    caption,
    destinations,
    scheduledAt = null,
  },
  onUploadProgress,
) {
  const formData = new FormData()

  formData.append(
    'caption',
    caption ?? '',
  )

  const fileType =
    file?.type?.startsWith('video/')
      ? 'video'
      : 'image'

  formData.append(
    'media[0][type]',
    fileType,
  )

  formData.append(
    'media[0][file]',
    file,
  )

  destinations.forEach(
    (destination, index) => {
      formData.append(
        `destinations[${index}][social_account_id]`,
        String(destination.social_account_id),
      )

      if (scheduledAt) {
        formData.append(
          `destinations[${index}][scheduled_at]`,
          scheduledAt,
        )
      }
    },
  )

  const { data } = await apiClient.post(
    CONTENTS_BASE,
    formData,
    {
      onUploadProgress: (event) => {
        if (!event.total) {
          return
        }

        const percent = Math.round(
          (event.loaded / event.total) * 100,
        )

        onUploadProgress?.(percent)
      },
    },
  )

  return data?.content ?? data?.data
}

/**
 * Update content.
 */
export async function updateContent(
  id,
  payload,
) {
  const { data } = await apiClient.patch(
    `${CONTENTS_BASE}/${id}`,
    payload,
  )

  return data?.content ?? data?.data
}

/**
 * Delete content.
 */
export async function deleteContent(id) {
  await apiClient.delete(
    `${CONTENTS_BASE}/${id}`,
  )
}