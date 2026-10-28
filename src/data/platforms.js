
export const PLATFORMS = {
  YouTube: {
    color: '#FF0000',
    bgClass: 'bg-yt-bg',
    textColor: 'text-yt',
    icon: '▶',
    label: 'YouTube',
    sublabel: 'Upload as video or Shorts',
  },

  Facebook: {
    color: '#1877F2',
    bgClass: 'bg-fb-bg',
    textColor: 'text-fb',
    icon: 'f',
    label: 'Facebook',
    sublabel: 'Post to your page feed',
  },

  Instagram: {
    color: '#E1306C',
    bgClass: 'bg-ig-bg',
    textColor: 'text-ig',
    icon: '◎',
    label: 'Instagram',
    sublabel: 'Post photos, videos or Reels',
  },

  X: {
    color: '#E7E9EA',
    bgClass: 'bg-tw-bg',
    textColor: 'text-tw',
    icon: '𝕏',
    label: 'X (Twitter)',
    sublabel: 'Post to your timeline',
  },
}

export const PLATFORM_KEYS = Object.keys(PLATFORMS)

export const UPLOAD_STEPS = [
  'Validating file',
  'Uploading to CDN',
]

export const PLATFORM_PUBLISH_STEP = (platform) =>
  `Publishing to ${platform}`

export const FINALIZE_STEP = 'Finalizing'
