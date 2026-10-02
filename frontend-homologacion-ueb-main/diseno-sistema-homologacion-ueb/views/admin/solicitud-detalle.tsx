'use client'

import { Download } from 'lucide-react'
import { api, download } from '@/lib/api'
import { conclusionLabels, docEstadoLabels, formatDate } from '@/lib/format'
import { Alert, Empty, KeyValue, Loading, PageHeader, Panel, StatusPill, Timeline, useAction, useAsync } from '@/components/app/ui'
import { ResolucionForm } from '@/views/coordinator/solicitud-detalle'

type Detalle = {
  id: number
  procedencia_estudios: string
  carrera?: { nombre: string } | null
  estudiante?: { nombres_completos: string; cedula: string; email: string }
  coordinador?: { nombres_completos: string; email: string } | null
  tramite?: { tipo_tramite: { nombre: string }; tipo_proceso: { nombre: string } }
  estado_actual?: { nombre: string } | null
  documentos?: { id: number; documento_requerido?: { nombre_documento: string }; estado?: { nombre: string }; presentado: boolean; observaciones?: { id: number; observacion: string }[] }[]
  historial_estados?: { id: number; estado: { nombre: string }; observacion: string | null; usuario_responsable: { nombres_completos: string } | null; created_at: string }[]
  resultado?: { conclusion_general: string; total_creditos_reconocidos: number } | null
  resolucion?: { numero_resolucion: string; fecha_aprobacion: string; download_url: string } | null
  created_at: string
}

export function AdminSolicitudDetalle({ id }: { id: number }) {
  const detail = useAsync(() => api<{ data: Detalle }>(`/admin/solicitudes/${id}`).then((r) => r.data), [id])
  const action = useAction()

  if (detail.loading && !detail.data) return <Loading />
  if (!detail.data) return <><PageHeader title={`Solicitud #${id}`} back="solicitudes" /><Alert error={detail.error} onRetry={detail.reload} /></>
  const s = detail.data
  const estado = s.estado_actual?.nombre ?? ''

  return (
    <>
      <PageHeader back="solicitudes" kicker={`SOLICITUD #${s.id}`} title={s.estudiante?.nombres_completos ?? `Solicitud #${s.id}`} subtitle={s.tramite ? `${s.tramite.tipo_tramite.nombre} · ${s.tramite.tipo_proceso.nombre}` : undefined} actions={<StatusPill estado={estado} />} />
      <Alert error={action.error} />
      <div className="grid-2">
        <Panel title="Expediente">
          <KeyValue items={[['Estudiante', s.estudiante?.nombres_completos], ['Cédula', s.estudiante?.cedula], ['Carrera', s.carrera?.nombre], ['Coordinador', s.coordinador?.nombres_completos], ['Procedencia', s.procedencia_estudios], ['Creada', formatDate(s.created_at, true)]]} />
        </Panel>
        <Panel title="Resultado">
          {s.resultado ? <KeyValue items={[['Conclusión', conclusionLabels[s.resultado.conclusion_general] ?? s.resultado.conclusion_general], ['Créditos reconocidos', s.resultado.total_creditos_reconocidos]]} /> : <Empty>Sin resultado académico todavía.</Empty>}
          {s.resolucion && <div className="resolution-box"><p>Resolución <strong>{s.resolucion.numero_resolucion}</strong> del {formatDate(s.resolucion.fecha_aprobacion)}</p><button className="btn btn-outline" onClick={() => action.run(() => download(s.resolucion!.download_url, `resolucion-${s.id}.pdf`))}><Download size={15} /> Descargar</button></div>}
        </Panel>
      </div>
      {estado === 'en_consejo' && !s.resolucion && <ResolucionForm endpoint={`/admin/solicitudes/${s.id}/resolucion`} onDone={detail.reload} />}
      <Panel title="Documentos">
        {s.documentos?.length ? (
          <div className="table-wrap"><table className="data-table">
            <thead><tr><th>Requisito</th><th>Archivo</th><th>Estado</th><th>Última observación</th></tr></thead>
            <tbody>{s.documentos.map((d) => <tr key={d.id}><td>{d.documento_requerido?.nombre_documento}</td><td>{d.presentado ? 'Cargado' : 'Pendiente'}</td><td>{docEstadoLabels[d.estado?.nombre ?? ''] ?? d.estado?.nombre}</td><td>{d.observaciones?.at(-1)?.observacion ?? '—'}</td></tr>)}</tbody>
          </table></div>
        ) : <Empty>Sin documentos.</Empty>}
      </Panel>
      <Panel title="Historial de estados">
        <Timeline items={(s.historial_estados ?? []).slice().reverse().map((h) => ({ id: h.id, estado: h.estado.nombre, observacion: h.observacion, fecha: formatDate(h.created_at, true), actor: h.usuario_responsable?.nombres_completos }))} />
      </Panel>
    </>
  )
}
