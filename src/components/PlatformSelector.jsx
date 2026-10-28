import { PLATFORMS, PLATFORM_KEYS } from '@/data/platforms'
import { cn } from '@/utils/cn'

/**
 * @param {{
 *   selected: Record<string, boolean>,
 *   onToggle: (platform: string) => void,
 * }} props
 */
export default function PlatformSelector({ selected, onToggle }) {
  return (
    <div className="flex flex-col gap-2.5">
      {PLATFORM_KEYS.map((key) => {
        const p         = PLATFORMS[key]
        const isChecked = selected[key] ?? false

        return (
          <label
            key={key}
            className={cn(
              'flex cursor-pointer items-center gap-3.5 rounded-[14px] border p-4',
              'transition-all duration-200',
              isChecked
                ? 'border-brand/40 bg-brand-faint'
                : 'border-surface-border bg-white/[0.03] hover:border-surface-border2',
            )}
            onClick={() => onToggle(key)}
          >
            {/* Platform icon */}
            <div
              className="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-[10px]
                         text-base font-black"
              style={{ background: p.bgClass.replace('bg-', ''), color: p.color,
                       backgroundColor: key === 'YouTube' ? 'rgba(255,0,0,0.12)'
                                      : key === 'Facebook' ? 'rgba(24,119,242,0.12)'
                                      : 'rgba(231,233,234,0.08)' }}
            >
              {p.icon}
            </div>

            {/* Labels */}
            <div className="flex-1">
              <p className="text-[14px] font-semibold text-ink">{p.label}</p>
              <p className="text-[12px] text-ink-muted">{p.sublabel}</p>
            </div>

            {/* Custom checkbox */}
            <div
              className={cn(
                'flex h-5 w-5 flex-shrink-0 items-center justify-center rounded-md',
                'border-2 transition-all duration-200',
                isChecked
                  ? 'border-brand bg-brand text-white'
                  : 'border-surface-border2',
              )}
            >
              {isChecked && <span className="text-[11px] font-bold">✓</span>}
            </div>
          </label>
        )
      })}
    </div>
  )
}
