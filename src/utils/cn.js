/**
 * Lightweight className joiner — merges truthy strings.
 * Usage: cn('base', condition && 'extra', 'always')
 */
export function cn(...classes) {
  return classes.filter(Boolean).join(' ')
}
