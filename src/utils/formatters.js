export function formatCount(n) {
  if (n >= 1_000_000) return `${(n / 1_000_000).toFixed(1)}M`
  if (n >= 1_000)     return `${(n / 1_000).toFixed(1)}K`
  return String(n)
}

export function formatDate(iso) {
  if (!iso) return ''
  return new Date(iso).toLocaleDateString('en-US', {
    month: 'short', day: 'numeric', year: 'numeric',
  })
}

export function todayISO() {
  return new Date().toISOString().slice(0, 10)
}

export function clamp(value, min, max) {
  return Math.min(Math.max(value, min), max)
}

export function isVideoFile(file) {
  return file?.type?.startsWith('video/')
}

export function isImageFile(file) {
  return file?.type?.startsWith('image/')
}

export function formatFileSize(bytes) {
  if (!bytes) return '0 B'
  if (bytes < 1024)        return `${bytes} B`
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`
}
