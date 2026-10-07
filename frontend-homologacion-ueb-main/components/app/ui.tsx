'use client'

import { Children, isValidElement, ReactNode, useCallback, useEffect, useRef, useState } from 'react'
import { ArrowLeft, ChevronLeft, ChevronRight, CircleAlert, CircleCheck, CircleX, Clock3, SearchCheck, Inbox, LoaderCircle, RotateCcw, type LucideIcon } from 'lucide-react'
import { ApiError, invalidateApiCache } from '@/lib/api'
import { estadoClass, estadoLabel } from '@/lib/format'
import { href } from '@/lib/router'

// ---------------------------------------------------------------------------
// Carga de datos
// ---------------------------------------------------------------------------

export function useAsync<T>(loader: () => Promise<T>, deps: unknown[]) {
  const [data, setData] = useState<T | null>(null)
  const [error, setError] = useState<ApiError | Error | null>(null)
  const [pending, setPending] = useState(true)
  const loaderRef = useRef(loader)
  const sequence = useRef(0)
  const mounted = useRef(false)
  loaderRef.current = loader
  const load = useCallback(async () => {
    const request = ++sequence.current
    setPending(true)
    setError(null)
    try {
      const result = await loaderRef.current()
      if (mounted.current && request === sequence.current) setData(result)
    } catch (err) {
      if (mounted.current && request === sequence.current) setError(err as Error)
    } finally {
      if (mounted.current && request === sequence.current) setPending(false)
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, deps)
  const reload = useCallback(async () => { invalidateApiCache(); await load() }, [load])
  useEffect(() => {
    mounted.current = true
    void load()
    return () => { mounted.current = false; sequence.current++ }
  }, [load])
  // Mantener el contenido visible durante actualizaciones en segundo plano.
  return { data, error, loading: pending && data === null, refreshing: pending && data !== null, reload, setData }
}

export function useDebouncedValue<T>(value: T, delay = 250) {
  const [debounced, setDebounced] = useState(value)
  useEffect(() => {
    const timer = setTimeout(() => setDebounced(value), delay)
    return () => clearTimeout(timer)
  }, [value, delay])
  return debounced
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

export function PageHeader({ kicker, title, subtitle, back, actions, icon: Icon }: { kicker?: string; title: string; subtitle?: string; back?: string; actions?: ReactNode; icon?: LucideIcon }) {
  return (
    <div className="page-head">
      <div>
        {back && <a className="back-link" href={href(back)}><ArrowLeft size={14} /> Volver</a>}
        {kicker && <p className="section-kicker">{kicker}</p>}
        <h2 className="page-title">{Icon && <span className="page-title-icon"><Icon size={21} aria-hidden="true" /></span>}{title}</h2>
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

export function Alert({ error, message, onRetry, inlineFields = false }: { error?: Error | null; message?: string; onRetry?: () => void; inlineFields?: boolean }) {
  if (error) {
    const fields = error instanceof ApiError ? [...new Set(Object.values(error.errors).flat())] : []
    if (inlineFields && fields.length) return null
    return (
      <div className="api-alert" role="alert">
        <CircleAlert className="feedback-icon" size={19} aria-hidden="true" />
        <div><strong>{fields.length ? 'Revisa los siguientes datos para continuar:' : error.message}</strong>{fields.length > 0 && <ul>{fields.map(text => <li key={text}>{text}</li>)}</ul>}</div>
        {onRetry && <button onClick={onRetry}><RotateCcw size={14} aria-hidden="true" />Reintentar</button>}
      </div>
    )
  }
  if (message) return <div className="api-success" role="status"><CircleCheck className="feedback-icon" size={19} aria-hidden="true" /><span>{message}</span></div>
  return null
}

export function Loading({ text = 'Cargando...' }: { text?: string }) {
  return <div className="empty-row visual-empty" role="status"><LoaderCircle className="loading-icon" size={24} aria-hidden="true" /><span>{text}</span></div>
}

export function Empty({ children }: { children: ReactNode }) {
  return <div className="empty-row visual-empty"><span className="empty-icon"><Inbox size={24} aria-hidden="true" /></span><div>{children}</div></div>
}

export function StatusPill({ estado }: { estado?: string | null }) {
  const tone = estadoClass(estado)
  const Icon = tone === 'approved' ? CircleCheck : tone === 'rejected' ? CircleX : estado === 'observado' ? CircleAlert : estado === 'en_revision' || estado === 'en_proceso' ? SearchCheck : Clock3
  return <span className={`status ${tone}`}><Icon size={15} aria-hidden="true" />{estadoLabel(estado)}</span>
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

export function Field({ label, error, children, hint, required }: { label: string; error?: string; hint?: string; children: ReactNode; required?: boolean }) {
  const mandatory = required ?? Children.toArray(children).some(child => isValidElement<{ required?: boolean }>(child) && child.props.required)
  return (
    <label className="field">
      <span>{label}{mandatory && <><span className="required-mark" aria-hidden="true"> *</span><span className="sr-only"> (obligatorio)</span></>}</span>
      {children}
      {hint && !error && <small className="field-hint">{hint}</small>}
      {error && <small className="field-error" role="alert">{error}</small>}
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
