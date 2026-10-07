'use client'

import { Download, Check, FileText, CircleAlert, Clock3 } from 'lucide-react'
import { api, download } from '@/lib/api'
import { tramiteLabel } from '@/lib/requirements'
import { conclusionLabels, docEstadoLabels, formatDate } from '@/lib/format'
import { Alert, Empty, KeyValue, Loading, PageHeader, Panel, StatusPill, Timeline, useAction, useAsync } from '@/components/app/ui'

type Documento = {
  requisito?: { id: number; nombre: string; descripcion?: string | null }
  id: number
  estado?: string
  obligatorio: boolean
  recibido_at?: string | null
  validez: boolean
  presentado: boolean
  download_url: string | null
  observaciones?: { id: number; observacion: string; created_at: string }[]
}

type Detalle = {
  id: number
  carrera_origen?: string | null
  procedencia_estudios: string
  carrera?: { id: number; nombre: string } | null
  coordinador?: { id: number; nombres_completos: string } | null
  tramite?: { tipo_tramite: string; tipo_proceso: string }
  estado_actual: string | null
  puede_editar: boolean
  puede_editar_procedencia?: boolean
  puede_enviar: boolean
  documentos?: Documento[]
  historial_estados?: { id: number; estado: string; observacion: string | null; created_at: string }[]
  resultado?: { conclusion_general: string; total_creditos_reconocidos: number; informe_download_url?: string | null } | null
  resolucion?: { numero_resolucion: string; fecha_aprobacion: string; download_url: string } | null
  created_at: string
}


export function StudentSolicitudDetalle({ id }: { id: number }) {
  const detail = useAsync(() => api<{ data: Detalle }>(`/student/solicitudes/${id}`).then((r) => r.data), [id])
  const action = useAction()

  if (detail.loading && !detail.data) return <Loading />
  if (!detail.data) return <><PageHeader icon={FileText} title={`Solicitud #${id}`} back="solicitudes" /><Alert error={detail.error} onRetry={detail.reload} /></>
  const s = detail.data
  const docs = s.documentos ?? []
  const required = docs.filter(d=>d.obligatorio)
  const validated = required.filter(d=>d.estado==='aprobado' && d.validez).length
  const progress = required.length ? Math.floor(100*validated/required.length) : 0

  return (
    <>
      <PageHeader icon={FileText} back="solicitudes" kicker={`SOLICITUD #${s.id}`} title={s.tramite ? tramiteLabel(s.tramite) : `Solicitud #${s.id}`}
        actions={<>
          <StatusPill estado={s.estado_actual} />
        </>} />
      <Alert error={action.error} message={action.message} />

      <div className="grid-2">
        <Panel title="Datos de la solicitud">
          <KeyValue items={[
            ['Universidad de origen', s.procedencia_estudios],
            ['Carrera de origen', s.carrera_origen ?? 'Sin dato histórico'],
            ['Carrera de destino', s.carrera?.nombre],
            ['Coordinador', s.coordinador?.nombres_completos],
            ['Procedencia', s.procedencia_estudios],
            ['Creada', formatDate(s.created_at, true)],
          ]} />
        </Panel>
        <Panel title="Resultado">
          {s.resultado ? <KeyValue items={[['Conclusión', conclusionLabels[s.resultado.conclusion_general] ?? s.resultado.conclusion_general], ['Créditos reconocidos', s.resultado.total_creditos_reconocidos]]} /> : <Empty>El análisis académico aún no tiene resultado.</Empty>}
          {s.resultado?.informe_download_url && <button className="btn btn-outline" onClick={() => action.run(() => download(s.resultado!.informe_download_url!, `informe-academico-${s.id}.pdf`))}><Download size={15} /> Descargar informe académico</button>}
          {s.resolucion && (
            <div className="resolution-box">
              <p>Resolución <strong>{s.resolucion.numero_resolucion}</strong> del {formatDate(s.resolucion.fecha_aprobacion)}</p>
              <button className="btn btn-primary" onClick={() => action.run(() => download(s.resolucion!.download_url, `resolucion-${s.id}.pdf`))}><Download size={15} /> Descargar resolución</button>
            </div>
          )}
        </Panel>
      </div>

      <Panel className="student-checklist" title="Mi checklist documental" actions={<span className="muted">Entrega y revisión presencial</span>}>
        <div className="checklist-summary"><div className="checklist-score">{progress}%</div><div><strong>{validated} de {required.length} requisitos obligatorios validados</strong><p>Entrega tus documentos al coordinador. Aquí verás sus validaciones y los motivos de cualquier observación. Completar la documentación no equivale a aprobar la homologación.</p><progress aria-label="Progreso de documentos validados" value={progress} max={100}/></div></div>
        {docs.length === 0 ? <Empty>Esta solicitud no tiene requisitos configurados.</Empty> : (
          <div className="doc-list">{docs.map((d, index) => (
            <article key={d.id} className={`doc-item document-${d.estado ?? 'pendiente'}`}><span className="document-step">{index+1}</span>
              <div className="doc-main">
                <strong>{d.requisito?.nombre ?? `Documento #${d.id}`}</strong>
                {d.requisito?.descripcion && <p>{d.requisito.descripcion}</p>}<small className="muted">{d.obligatorio?'Obligatorio':'Complementario'}{d.recibido_at ? ` · Recibido el ${formatDate(d.recibido_at)}` : ' · Pendiente de entrega presencial'}</small>
                {d.observaciones?.length ? <div className="doc-observation"><strong>Qué debes corregir</strong><p>{d.observaciones[d.observaciones.length - 1].observacion}</p></div> : null}
              </div>
              <span className={`status ${d.estado === 'aprobado' ? 'approved' : d.estado === 'observado' ? '' : 'received'}`}>{d.estado === 'aprobado' ? <span className="validated-check"><Check size={13} strokeWidth={3} aria-hidden="true" /></span> : d.estado === 'observado' ? <CircleAlert size={15} aria-hidden="true" /> : <Clock3 size={15} aria-hidden="true" />}{docEstadoLabels[d.estado ?? ''] ?? d.estado}</span>
              <div className="doc-actions">

              </div>
            </article>
          ))}</div>
        )}
      </Panel>

      <Panel className="request-tracking" title="Seguimiento de la solicitud" actions={<span className="tracking-label"><Clock3 size={15} aria-hidden="true" />Historial del proceso</span>}>
        <Timeline items={(s.historial_estados ?? []).slice().reverse().map((h) => ({ id: h.id, estado: h.estado, observacion: h.observacion, fecha: formatDate(h.created_at, true) }))} />
      </Panel>
    </>
  )
}
