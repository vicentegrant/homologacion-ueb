'use client'

import { useState } from 'react'
import { api, Paginated } from '@/lib/api'
import { formatDate } from '@/lib/format'
import { navigate } from '@/lib/router'
import { Alert, Empty, Loading, PageHeader, Pager, Panel, useAction, useAsync } from '@/components/app/ui'

type Notificacion = { id: string; data: { solicitud_id: number; evento: string; mensaje: string; url: string }; read_at: string | null; created_at: string }

export function StudentNotificaciones() {
  const [soloPendientes, setSoloPendientes] = useState(false)
  const [page, setPage] = useState(1)
  const list = useAsync(() => api<Paginated<Notificacion> & { sin_leer: number }>('/student/notificaciones', { query: { page, sin_leer: soloPendientes ? 1 : undefined } }), [page, soloPendientes])
  const action = useAction()

  async function abrir(n: Notificacion) {
    if (!n.read_at) await action.run(() => api(`/student/notificaciones/${n.id}/leer`, { method: 'PATCH' }))
    navigate(`solicitudes/${n.data.solicitud_id}`)
  }

  async function marcar(n: Notificacion) {
    await action.run(() => api(`/student/notificaciones/${n.id}/leer`, { method: 'PATCH' }))
    list.reload()
  }

  return (
    <>
      <PageHeader kicker="AVISOS" title="Notificaciones" subtitle={list.data ? `${list.data.sin_leer} sin leer` : undefined} />
      <Panel title="Bandeja" actions={<label className="check-inline"><input type="checkbox" checked={soloPendientes} onChange={(e) => { setSoloPendientes(e.target.checked); setPage(1) }} /> Solo sin leer</label>}>
        <Alert error={list.error ?? action.error} onRetry={list.reload} />
        {list.loading && !list.data ? <Loading /> : list.data?.data.length ? (
          <ul className="notice-list">{list.data.data.map((n) => (
            <li key={n.id} className={n.read_at ? '' : 'unread'}>
              <button className="notice-main" onClick={() => abrir(n)}><strong>{n.data.mensaje}</strong><small>Solicitud #{n.data.solicitud_id} · {formatDate(n.created_at, true)}</small></button>
              {!n.read_at && <button className="btn btn-ghost" onClick={() => marcar(n)}>Marcar leída</button>}
            </li>
          ))}</ul>
        ) : <Empty>No tienes notificaciones.</Empty>}
        <Pager meta={list.data?.meta} onPage={setPage} />
      </Panel>
    </>
  )
}
