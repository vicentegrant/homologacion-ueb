'use client'

import { useState } from 'react'
import { ArrowRight, ClipboardCheck } from 'lucide-react'
import { api, Paginated, SolicitudEstudiante } from '@/lib/api'
import { estadoLabels, formatDate } from '@/lib/format'
import { href, navigate } from '@/lib/router'
import { tramiteLabel } from '@/lib/requirements'
import { Alert, Empty, Loading, PageHeader, Pager, Panel, StatusPill, useAsync } from '@/components/app/ui'

export function StudentSolicitudes() {
  const [estado, setEstado] = useState('')
  const [page, setPage] = useState(1)
  const list = useAsync(() => api<Paginated<SolicitudEstudiante>>('/student/solicitudes', { query: { estado, page, per_page: 15 } }), [estado, page])

  return (
    <>
      <PageHeader icon={ClipboardCheck} kicker="MIS TRÁMITES" title="Mis solicitudes" subtitle="Consulta el avance de las solicitudes registradas por tu coordinador. Entrega los documentos presencialmente." />
      <Panel title="Historial de solicitudes" actions={
        <select className="compact" value={estado} onChange={(e) => { setEstado(e.target.value); setPage(1) }} aria-label="Filtrar por estado">
          <option value="">Todos los estados</option>
          {Object.entries(estadoLabels).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
        </select>
      }>
        <Alert error={list.error} onRetry={list.reload} />
        {list.loading && !list.data ? <Loading /> : list.data?.data.length ? (
          <div className="table-wrap"><table className="data-table">
            <thead><tr><th>#</th><th>Trámite</th><th>Carrera</th><th>Procedencia</th><th>Creada</th><th>Estado</th><th /></tr></thead>
            <tbody>{list.data.data.map((s) => (
              <tr key={s.id} onClick={() => navigate(`solicitudes/${s.id}`)} className="clickable">
                <td>{s.id}</td><td>{s.tramite ? tramiteLabel(s.tramite) : '—'}</td><td>{s.carrera?.nombre ?? '—'}</td><td>{s.procedencia_estudios}</td><td>{formatDate(s.created_at)}</td><td><StatusPill estado={s.estado_actual} /></td>
                <td><a className="row-arrow" href={href(`solicitudes/${s.id}`)} aria-label={`Abrir solicitud ${s.id}`}><ArrowRight size={16} /></a></td>
              </tr>
            ))}</tbody>
          </table></div>
        ) : <Empty>No hay solicitudes{estado ? ' con ese estado' : ''}.</Empty>}
        <Pager meta={list.data?.meta} onPage={setPage} />
      </Panel>
    </>
  )
}
