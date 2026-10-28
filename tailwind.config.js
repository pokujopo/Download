/** @type {import('tailwindcss').Config} */
export default {
  content: ['./index.html', './src/**/*.{js,jsx}'],
  theme: {
    extend: {
      colors: {
        brand: {
          DEFAULT: '#FF6600',
          light:   '#FF8533',
          dark:    '#CC4400',
          glow:    'rgba(255,102,0,0.25)',
          faint:   'rgba(255,102,0,0.08)',
          soft:    'rgba(255,102,0,0.15)',
        },
        surface: {
          bg:      '#0B132B',
          card:    '#1C2541',
          card2:   '#162035',
          hover:   'rgba(255,255,255,0.05)',
          border:  'rgba(255,255,255,0.07)',
          border2: 'rgba(255,255,255,0.12)',
        },
        ink: {
          DEFAULT: '#F0F4FF',
          muted:   '#7B8CB3',
          faint:   '#4A5A82',
        },
        status: {
          success:        '#22C55E',
          'success-bg':   'rgba(34,197,94,0.12)',
          failed:         '#EF4444',
          'failed-bg':    'rgba(239,68,68,0.12)',
          pending:        '#F59E0B',
          'pending-bg':   'rgba(245,158,11,0.12)',
        },
        yt:  { DEFAULT: '#FF0000', bg: 'rgba(255,0,0,0.12)'  },
        fb:  { DEFAULT: '#1877F2', bg: 'rgba(24,119,242,0.12)' },
        tw:  { DEFAULT: '#E7E9EA', bg: 'rgba(231,233,234,0.08)' },
      },
      fontFamily: {
        display: ['Syne', 'sans-serif'],
        body:    ['DM Sans', 'sans-serif'],
      },
      borderRadius: {
        '2xl': '18px',
        '3xl': '24px',
      },
      boxShadow: {
        brand:  '0 8px 24px rgba(255,102,0,0.30)',
        'brand-lg': '0 12px 40px rgba(255,102,0,0.40)',
        card:   '0 2px 16px rgba(0,0,0,0.30)',
        glow:   '0 0 12px rgba(255,102,0,0.50)',
      },
      animation: {
        'fade-up':   'fadeUp 0.4s ease both',
        'spin-fast': 'spin 0.7s linear infinite',
        shimmer:     'shimmer 1.5s infinite',
        pulse:       'pulse 2s cubic-bezier(0.4,0,0.6,1) infinite',
      },
      keyframes: {
        fadeUp: {
          from: { opacity: '0', transform: 'translateY(16px)' },
          to:   { opacity: '1', transform: 'translateY(0)' },
        },
        shimmer: {
          to: { backgroundPosition: '-200% 0' },
        },
      },
      transitionTimingFunction: {
        smooth: 'cubic-bezier(0.4, 0, 0.2, 1)',
      },
    },
  },
  plugins: [],
}
