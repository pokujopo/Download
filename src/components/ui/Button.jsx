import { cn } from '@/utils/cn'

/**
 * @param {{ variant?: 'primary' | 'secondary' | 'danger', size?: 'sm' | 'md' | 'lg' } & React.ButtonHTMLAttributes<HTMLButtonElement>} props
 */
export default function Button({
  variant = 'primary',
  size    = 'md',
  className,
  children,
  ...props
}) {
  const base = 'inline-flex items-center justify-center gap-2 rounded-xl font-display font-bold transition-all duration-200 disabled:cursor-not-allowed disabled:opacity-50'

  const variants = {
    primary:   'bg-gradient-to-br from-brand to-brand-dark text-white shadow-brand hover:shadow-brand-lg hover:-translate-y-0.5 active:translate-y-0',
    secondary: 'bg-white/[0.06] border border-surface-border text-ink hover:bg-white/10 hover:border-surface-border2',
    danger:    'bg-status-failed-bg border border-status-failed/20 text-status-failed hover:bg-status-failed/20',
  }

  const sizes = {
    sm: 'px-4 py-2 text-sm',
    md: 'px-5 py-3 text-sm',
    lg: 'w-full px-5 py-4 text-base',
  }

  return (
    <button
      className={cn(base, variants[variant], sizes[size], className)}
      {...props}
    >
      {children}
    </button>
  )
}
