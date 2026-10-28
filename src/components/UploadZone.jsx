import { useRef, useState } from 'react'
import { cn } from '@/utils/cn'
import { isVideoFile, isImageFile, formatFileSize } from '@/utils/formatters'

const ACCEPTED_TYPES = 'video/*,image/*'
const ACCEPT_LABELS  = ['MP4', 'MOV', 'AVI', 'JPG', 'PNG', 'GIF', 'WEBP']

/**
 * @param {{ onFile: (file: File) => void, error?: string | null }} props
 */
export default function UploadZone({ onFile, error }) {
  const inputRef = useRef(null)
  const [dragging, setDragging] = useState(false)

  const handleFile = (file) => {
    if (!file) return
    if (!isVideoFile(file) && !isImageFile(file)) return
    onFile(file)
  }

  const onDrop = (e) => {
    e.preventDefault()
    setDragging(false)
    handleFile(e.dataTransfer.files?.[0])
  }

  return (
    <div
      role="button"
      tabIndex={0}
      aria-label="Upload file"
      onClick={() => inputRef.current?.click()}
      onKeyDown={(e) => e.key === 'Enter' && inputRef.current?.click()}
      onDragOver={(e) => { e.preventDefault(); setDragging(true)  }}
      onDragLeave={() => setDragging(false)}
      onDrop={onDrop}
      className={cn(
        'relative flex min-h-[220px] cursor-pointer flex-col items-center justify-center gap-3',
        'rounded-2xl border-2 border-dashed text-center transition-all duration-200',
        dragging
          ? 'scale-[1.01] border-brand bg-brand-faint'
          : error
          ? 'border-status-failed/40 bg-status-failed/[0.04]'
          : 'border-brand/30 bg-brand/[0.03] hover:border-brand/60 hover:bg-brand/[0.05]',
      )}
    >
      <input
        ref={inputRef}
        type="file"
        accept={ACCEPTED_TYPES}
        className="sr-only"
        onChange={(e) => handleFile(e.target.files?.[0])}
      />

      <span className="text-5xl">{dragging ? '📥' : '⬆'}</span>

      <div>
        <p className="font-display text-lg font-bold text-ink">
          {dragging ? 'Drop it!' : 'Drop your file here'}
        </p>
        <p className="mt-1 text-sm text-ink-muted">or click to browse from your device</p>
      </div>

      <div className="flex flex-wrap justify-center gap-1.5">
        {ACCEPT_LABELS.map((ext) => (
          <span
            key={ext}
            className="rounded-full bg-white/[0.07] px-2.5 py-0.5 text-[11px] font-medium text-ink-muted"
          >
            {ext}
          </span>
        ))}
      </div>

      {error && (
        <p className="text-xs font-semibold text-status-failed">{error}</p>
      )}
    </div>
  )
}

/** Shows the preview of the selected file with a remove button. */
export function FilePreview({ file, previewUrl, fileType, onRemove }) {
  return (
    <div className="relative overflow-hidden rounded-2xl bg-black">
      {fileType === 'video' ? (
        <video
          src={previewUrl}
          controls
          className="max-h-[300px] w-full object-contain"
        />
      ) : (
        <img
          src={previewUrl}
          alt="Preview"
          className="max-h-[300px] w-full object-contain"
        />
      )}

      {/* File info strip */}
      <div className="absolute bottom-0 left-0 right-0 flex items-center justify-between
                      bg-black/70 px-3 py-2 backdrop-blur-sm">
        <div className="flex items-center gap-2 text-xs text-white/80">
          <span>{fileType === 'video' ? '🎬' : '🖼️'}</span>
          <span className="max-w-[180px] truncate">{file?.name}</span>
          <span className="text-white/50">·</span>
          <span>{formatFileSize(file?.size ?? 0)}</span>
        </div>
        <button
          type="button"
          onClick={onRemove}
          className="flex h-7 w-7 items-center justify-center rounded-full bg-white/20
                     text-sm text-white backdrop-blur-sm transition-colors
                     hover:bg-status-failed"
          aria-label="Remove file"
        >
          ✕
        </button>
      </div>
    </div>
  )
}
