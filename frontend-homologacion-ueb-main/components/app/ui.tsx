'use client'

import { ReactNode, useCallback, useEffect, useRef, useState } from 'react'
import { ArrowLeft, ChevronLeft, ChevronRight } from 'lucide-react'
import { ApiError } from '@/lib/api'
import { estadoClass, estadoLabel } from '@/lib/format'
import { href } from '@/lib/router'

// ---------------------------------------------------------------------------
// Carga de datos
// ---------------------------------------------------------------------------

export function useAsync<T>(loader: () => Promise<T>, deps: unknown[]) {
  const [data, setData] = useState<T | null>(null)
  const [error, setError] = useState<ApiError | Error | null>(null)
  const [loading, setLoading] = useState(true)
  const loaderRef = useRef(loader)
  loaderRef.current = loader

  const reload = useCallback(async () => {
    setLoading(true)
    setError(null)
    try {
      setData(await loaderRef.current())
    } catch (err) {
      setError(err as Error)
    } finally {
      setLoading(false)
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, deps)

  useEffect(() => { reload() }, [reload])
  return { data, error, loading, reload, setData }
}

/** Ejecuta una acción de escritura mostrando su error y ocupado. */
export function useAction() {
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState<ApiError | Error | null>(null)
  const [message, setMessage] = useState('')

  async function run<T>(action: () => Promise<T>, success?: string): Promise<T | undefined> {
    setBusy(true)
    setError(null)
    setMessage('')
    try {
      const result = await action()
      if (success) setMessage(success)
      return result
    } catch (err) {
      setError(err as Error)
      return undefined
    } finally {
      setBusy(false)
    }
  }

  return { busy, error, message, run, setError, setMessage }
}

export function fieldError(error: unknown, field: string): string | undefined {
  if (error instanceof ApiError) return error.errors[field]?.[0]
  return undefined
}

// ---------------------------------------------------------------------------
// Presentación
// ---------------------------------------------------------------------------

export function PageHeader({ kicker, title, subtitle, back, actions }: { kicker?: string; title: string; subtitle?: string; back?: string; actions?: ReactNode }) {
  return (
    <div className="page-head">
      <div>
        {back && <a className="back-link" href={href(back)}><ArrowLeft size={14} /> Volver</a>}
        {kicker && <p className="section-kicker">{kicker}</p>}
        <h2>{title}</h2>
        {subtitle && <p className="page-subtitle">{subtitle}</p>}
      </div>
      {actions && <div className="page-actions">{actions}</div>}
    </div>
  )
}

export function Panel({ title, actions, children, className = '' }: { title?: string; actions?: ReactNode; children: ReactNode; className?: string }) {
  return (
    <section className={`panel ${className}`}>
      {(title || actions) && <header className="panel-head">{title && <h3>{title}</h3>}{actions && <div className="panel-actions">{actions}</div>}</header>}
      <div className="panel-body">{children}</div>
    </section>
  )
}

export function Alert({ error, message, onRetry }: { error?: Error | null; message?: string; onRetry?: () => void }) {
  if (error) {
    const fields = error instanceof ApiError ? Object.values(error.errors).flat() : []
    return (
      <div className="api-alert" role="alert">
        <span>{error.message}{fields.length > 0 && <small>{fields.join(' ')}</small>}</span>
        {onRetry && <button onClick={onRetry}>Reintentar</button>}
      </div>
    )
  }
  if (message) return <div className="api-success" role="status">{message}</div>
  return null
}

export function Loading({ text = 'Cargando...' }: { text?: string }) {
  return <div className="empty-row">{text}</div>
}

export function Empty({ children }: { children: ReactNode }) {
  return <div className="empty-row">{children}</div>
}

export function StatusPill({ estado }: { estado?: string | null }) {
  return <span className={`status ${estadoClass(estado)}`}><i />{estadoLabel(estado)}</span>
}

export function Pager({ meta, onPage }: { meta?: { current_page: number; last_page: number; total: number }; onPage: (page: number) => void }) {
  if (!meta || meta.last_page <= 1) return meta ? <div className="pager"><span>{meta.total} registro(s)</span></div> : null
  return (
    <div className="pager">
      <span>{meta.total} registro(s) · página {meta.current_page} de {meta.last_page}</span>
      <div>
        <button className="btn btn-ghost" disabled={meta.current_page <= 1} onClick={() => onPage(meta.current_page - 1)} aria-label="Página anterior"><ChevronLeft size={15} /></button>
        <button className="btn btn-ghost" disabled={meta.current_page >= meta.last_page} onClick={() => onPage(meta.current_page + 1)} aria-label="Página siguiente"><ChevronRight size={15} /></button>
      </div>
    </div>
  )
}

export function Field({ label, error, children, hint }: { label: string; error?: string; hint?: string; children: ReactNode }) {
  return (
    <label className="field">
      <span>{label}</span>
      {children}
      {hint && !error && <small className="field-hint">{hint}</small>}
      {error && <small className="field-error">{error}</small>}
    </label>
  )
}

export function KeyValue({ items }: { items: [string, ReactNode][] }) {
  return (
    <dl className="kv">
      {items.map(([key, value]) => <div key={key}><dt>{key}</dt><dd>{value ?? '—'}</dd></div>)}
    </dl>
  )
}

export function Timeline({ items }: { items: { id: number; estado: string; observacion?: string | null; fecha?: string | null; actor?: string | null }[] }) {
  if (!items.length) return <Empty>Sin movimientos registrados.</Empty>
  return (
    <ol className="timeline">
      {items.map((item) => (
        <li key={item.id}>
          <StatusPill estado={item.estado} />
          <div>
            <small>{item.fecha}{item.actor ? ` · ${item.actor}` : ''}</small>
            {item.observacion && <p>{item.observacion}</p>}
          </div>
        </li>
      ))}
    </ol>
  )
}
