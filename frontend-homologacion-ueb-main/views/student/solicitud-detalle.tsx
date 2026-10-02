'use client'

import { useState } from 'react'
import { Download, Send, Upload } from 'lucide-react'
import { api, download, upload } from '@/lib/api'
import { conclusionLabels, docEstadoLabels, formatDate } from '@/lib/format'
import { Alert, Empty, KeyValue, Loading, PageHeader, Panel, StatusPill, Timeline, useAction, useAsync } from '@/components/app/ui'

type Documento = {
  id: number
  requisito?: { id: number; nombre: string; descripcion: string | null }
  estado?: string
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

function canUpload(solicitud: string | null, doc?: string) {
  if (solicitud === 'pendiente') return doc === 'pendiente' || doc === 'presentado'
  if (solicitud === 'observado') return doc === 'observado' || doc === 'pendiente'
  return false
}

export function StudentSolicitudDetalle({ id }: { id: number }) {
  const detail = useAsync(() => api<{ data: Detalle }>(`/student/solicitudes/${id}`).then((r) => r.data), [id])
  const action = useAction()
  const [procedencia, setProcedencia] = useState<string | null>(null)

  if (detail.loading && !detail.data) return <Loading />
  if (!detail.data) return <><PageHeader title={`Solicitud #${id}`} back="solicitudes" /><Alert error={detail.error} onRetry={detail.reload} /></>
  const s = detail.data
  const docs = s.documentos ?? []
  const completos = docs.every((d) => d.presentado)

  async function subir(docId: number, file?: File | null) {
    if (!file) return
    await action.run(() => upload(`/student/solicitudes/${id}/documentos/${docId}`, { archivo: file }), 'Documento cargado.')
    detail.reload()
  }

  async function enviar() {
    const ok = await action.run(() => api(`/student/solicitudes/${id}/enviar`, { method: 'POST' }), 'Solicitud enviada a revisión.')
    if (ok) detail.reload()
  }

  async function guardarProcedencia() {
    const ok = await action.run(() => api(`/student/solicitudes/${id}`, { method: 'PATCH', body: { procedencia_estudios: procedencia } }), 'Procedencia actualizada.')
    if (ok) { setProcedencia(null); detail.reload() }
  }

  return (
    <>
      <PageHeader back="solicitudes" kicker={`SOLICITUD #${s.id}`} title={s.tramite ? `${s.tramite.tipo_tramite} · ${s.tramite.tipo_proceso}` : `Solicitud #${s.id}`}
        actions={<>
          <StatusPill estado={s.estado_actual} />
          {s.puede_enviar && <button className="btn btn-primary" onClick={enviar} disabled={action.busy || !completos} title={completos ? '' : 'Carga todos los documentos primero'}><Send size={15} /> {s.estado_actual === 'observado' ? 'Reenviar corrección' : 'Enviar a revisión'}</button>}
        </>} />
      <Alert error={action.error} message={action.message} />
      {s.puede_enviar && !completos && <div className="api-info">Carga todos los documentos requeridos para poder enviar la solicitud. También necesitas antecedentes académicos registrados en tu perfil.</div>}

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

      <Panel title="Documentos requeridos">
        {docs.length === 0 ? <Empty>Esta solicitud no tiene requisitos configurados.</Empty> : (
          <div className="doc-list">{docs.map((d) => (
            <article key={d.id} className="doc-item">
              <div className="doc-main">
                <strong>{d.requisito?.nombre ?? `Documento #${d.id}`}</strong>
                {d.requisito?.descripcion && <p>{d.requisito.descripcion}</p>}
                {d.observaciones?.length ? <p className="doc-observation">Observación: {d.observaciones[d.observaciones.length - 1].observacion}</p> : null}
              </div>
              <span className={`status ${d.estado === 'aprobado' ? 'approved' : d.estado === 'observado' ? '' : 'received'}`}><i />{docEstadoLabels[d.estado ?? ''] ?? d.estado}</span>
              <div className="doc-actions">
                {d.download_url && <button className="btn btn-ghost" onClick={() => action.run(() => download(d.download_url!, `${d.requisito?.nombre ?? 'documento'}.pdf`))}><Download size={14} /> Ver</button>}
                {canUpload(s.estado_actual, d.estado) && (
                  <label className="btn btn-outline file-button">
                    <Upload size={14} /> {d.presentado ? 'Reemplazar' : 'Subir PDF'}
                    <input type="file" accept="application/pdf" disabled={action.busy} onChange={(e) => { subir(d.id, e.target.files?.[0]); e.target.value = '' }} />
                  </label>
                )}
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
