'use client'

import { useEffect, useState } from 'react'

/** Enrutador por hash: #/solicitudes/12 → ['solicitudes', '12'] */
function parse(): string[] {
  if (typeof window === 'undefined') return []
  return window.location.hash.replace(/^#\/?/, '').split('?')[0].split('/').filter(Boolean)
}

export function useHashRoute() {
  const [segments, setSegments] = useState<string[]>([])
  useEffect(() => {
    const update = () => {
      setSegments(parse())
      window.scrollTo({ top: 0 })
    }
    update()
    window.addEventListener('hashchange', update)
    return () => window.removeEventListener('hashchange', update)
  }, [])
  return segments
}

export function navigate(path: string) {
  window.location.hash = path.startsWith('/') ? path : `/${path}`
}

export function href(path: string) {
  return `#${path.startsWith('/') ? path : `/${path}`}`
}
