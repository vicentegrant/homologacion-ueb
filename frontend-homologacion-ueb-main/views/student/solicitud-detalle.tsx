'use client'

import { useState } from 'react'
import { Download, Check } from 'lucide-react'
import { api, download } from '@/lib/api'
import { conclusionLabels, docEstadoLabels, formatDate } from '@/lib/format'
import { Alert, Empty, KeyValue, Loading, PageHeader, Panel, StatusPill, Timeline, useAction, useAsync } from '@/components/app/ui'

type Documento = {
  id: number
  requisito?: { id: number; nombre: string; descripcion: string | null }
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
  procedencia_estudios: string
  carrera?: { id: number; nombre: string } | null
  coordinador?: { id: number; nombres_completos: string } | null
  tramite?: { tipo_tramite: string; tipo_proceso: string }
  estado_actual: string | null
  puede_editar: boolean
  puede_enviar: boolean
  documentos?: Documento[]
  historial_estados?: { id: number; estado: string; observacion: string | null; created_at: string }[]
  resultado?: { conclusion_general: string; total_creditos_reconocidos: number } | null
  resolucion?: { numero_resolucion: string; fecha_aprobacion: string; download_url: string } | null
  created_at: string
}


export function StudentSolicitudDetalle({ id }: { id: number }) {
  const detail = useAsync(() => api<{ data: Detalle }>(`/student/solicitudes/${id}`).then((r) => r.data), [id])
  const action = useAction()
  const [procedencia, setProcedencia] = useState<string | null>(null)

  if (detail.loading && !detail.data) return <Loading />
  if (!detail.data) return <><PageHeader title={`Solicitud #${id}`} back="solicitudes" /><Alert error={detail.error} onRetry={detail.reload} /></>
  const s = detail.data
  const docs = s.documentos ?? []
  const required = docs.filter(d=>d.obligatorio)
  const validated = required.filter(d=>d.estado==='aprobado' && d.validez).length
  const progress = required.length ? Math.floor(100*validated/required.length) : 0

  async function guardarProcedencia() {
    const ok = await action.run(() => api(`/student/solicitudes/${id}`, { method: 'PATCH', body: { procedencia_estudios: procedencia } }), 'Procedencia actualizada.')
    if (ok) { setProcedencia(null); detail.reload() }
  }

  return (
    <>
      <PageHeader back="solicitudes" kicker={`SOLICITUD #${s.id}`} title={s.tramite ? `${s.tramite.tipo_tramite.replaceAll('_', ' ')} · ${s.tramite.tipo_proceso.replaceAll('_', ' ')}` : `Solicitud #${s.id}`}
        actions={<>
          <StatusPill estado={s.estado_actual} />
        </>} />
      <Alert error={action.error} message={action.message} />

      <div className="grid-2">
        <Panel title="Datos de la solicitud">
          <KeyValue items={[
            ['Carrera de destino', s.carrera?.nombre],
            ['Coordinador', s.coordinador?.nombres_completos],
            ['Procedencia', procedencia === null ? <>{s.procedencia_estudios} {s.puede_editar && <button className="link-button" onClick={() => setProcedencia(s.procedencia_estudios)}>Editar</button>}</> : (
              <span className="inline-edit"><input value={procedencia} maxLength={255} onChange={(e) => setProcedencia(e.target.value)} /><button className="btn btn-primary" onClick={guardarProcedencia} disabled={action.busy}>Guardar</button><button className="btn btn-ghost" onClick={() => setProcedencia(null)}>Cancelar</button></span>
            )],
            ['Creada', formatDate(s.created_at, true)],
          ]} />
        </Panel>
        <Panel title="Resultado">
          {s.resultado ? <KeyValue items={[['Conclusión', conclusionLabels[s.resultado.conclusion_general] ?? s.resultado.conclusion_general], ['Créditos reconocidos', s.resultado.total_creditos_reconocidos]]} /> : <Empty>El análisis académico aún no tiene resultado.</Empty>}
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
            <article key={d.id} className="doc-item"><span className={`document-step ${d.estado==='aprobado'?'validated':''}`}>{d.estado==='aprobado'?<Check size={17}/>:index+1}</span>
              <div className="doc-main">
                <strong>{d.requisito?.nombre ?? `Documento #${d.id}`}</strong>
                {d.requisito?.descripcion && <p>{d.requisito.descripcion}</p>}<small className="muted">{d.obligatorio?'Obligatorio':'Complementario'}{d.recibido_at ? ` · Recibido el ${formatDate(d.recibido_at)}` : ' · Pendiente de entrega presencial'}</small>
                {d.observaciones?.length ? <p className="doc-observation">Observación: {d.observaciones[d.observaciones.length - 1].observacion}</p> : null}
              </div>
              <span className={`status ${d.estado === 'aprobado' ? 'approved' : d.estado === 'observado' ? '' : 'received'}`}><i />{docEstadoLabels[d.estado ?? ''] ?? d.estado}</span>
              <div className="doc-actions">
                {d.download_url && <button className="btn btn-ghost" onClick={() => action.run(() => download(d.download_url!, `${d.requisito?.nombre ?? 'documento'}.pdf`))}><Download size={14} /> Ver</button>}

              </div>
            </article>
          ))}</div>
        )}
      </Panel>

      <Panel title="Seguimiento">
        <Timeline items={(s.historial_estados ?? []).slice().reverse().map((h) => ({ id: h.id, estado: h.estado, observacion: h.observacion, fecha: formatDate(h.created_at, true) }))} />
      </Panel>
    </>
  )
}
