import { cn } from '@/utils/cn'

export default function Spinner({ className }) {
  return (
    <span
      className={cn(
        'inline-block h-4 w-4 flex-shrink-0 rounded-full',
        'border-2 border-brand/30 border-t-brand',
        'animate-spin-fast',
        className,
      )}
    />
  )
}
