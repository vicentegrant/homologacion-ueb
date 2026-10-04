'use client'

import { FormEvent, useState } from 'react'
import { Check, Download, FileText, Trash2, X } from 'lucide-react'
import { api, ApiError, download, Paginated, upload } from '@/lib/api'
import { conclusionLabels, docEstadoLabels, estadoLabel, formatDate } from '@/lib/format'
import { href } from '@/lib/router'
import { Alert, Empty, Field, fieldError, KeyValue, Loading, PageHeader, Panel, StatusPill, Timeline, useAction, useAsync } from '@/components/app/ui'
import { Asignatura, getCoordinatorCatalogo, Malla } from './catalogo'

type Detalle = {
  id: number
  procedencia_estudios: string
  carrera?: { id: number; nombre: string } | null
  estudiante?: { id: number; nombres_completos: string; cedula: string; email: string }
  tramite?: { tipo_tramite: { nombre: string }; tipo_proceso: { nombre: string } }
  estado_actual?: { id: number; nombre: string } | null
  historial_estados?: { id: number; estado: { nombre: string }; observacion: string | null; etapa_origen: string | null; usuario_responsable: { nombres_completos: string } | null; created_at: string }[]
  resultado?: { conclusion_general: string; total_creditos_reconocidos: number; informe_tecnico_disponible: boolean; informe_generado_at: string | null } | null
  resolucion?: { numero_resolucion: string; fecha_aprobacion: string; download_url: string } | null
  created_at: string
}

type Documento = {
  id: number
  requisito?: { nombre: string; descripcion: string | null }
  estado?: string
  obligatorio: boolean
  recibido_at?: string | null
  validez: boolean
  presentado: boolean
  download_url: string | null
  observaciones?: { id: number; observacion: string; created_at: string }[]
  verificaciones?: { id: number; estado: boolean; coordinador: { nombres_completos: string } | null; updated_at: string }[]
}

type SubjectRef = { id: number; codigo: string; nombre: string; creditos: number; nivel_ciclo: string; malla?: { nombre: string } }
type Comparacion = { id: number; asignatura_origen?: SubjectRef; asignatura_destino?: SubjectRef; porcentaje_coincidencia: number; observacion: string | null }

// ---------------------------------------------------------------------------

function Documentos({ solicitudId, estado, onChanged }: { solicitudId: number; estado: string; onChanged: () => void }) {
  const docs = useAsync(() => api<{ data: Documento[] }>(`/coordinator/solicitudes/${solicitudId}/documents`).then((r) => r.data), [solicitudId, estado])
  const action = useAction()
  const [observing, setObserving] = useState<number | null>(null)
  const [observacion, setObservacion] = useState('')
  const editable = ['pendiente','en_revision','observado'].includes(estado)

  async function review(doc: Documento, nuevo: 'presentado' | 'aprobado' | 'observado') {
    const ok = await action.run(() => api(`/coordinator/documents/${doc.id}/review`, { method: 'PATCH', body: nuevo === 'observado' ? { estado: nuevo, observacion } : { estado: nuevo } }), nuevo === 'aprobado' ? 'Documento validado.' : nuevo === 'presentado' ? 'Entrega presencial registrada.' : 'Observación registrada; se notificó al estudiante.')
    if (ok) { setObserving(null); setObservacion(''); docs.reload(); onChanged() }
  }

  return (
    <Panel title="Documentación" actions={editable ? <span className="muted">Registra la entrega, revisa el documento y valida o explica qué debe corregirse.</span> : null}>
      <Alert error={docs.error ?? action.error} message={action.message} />
      {docs.loading && !docs.data ? <Loading /> : !docs.data?.length ? <Empty>Sin documentos requeridos.</Empty> : (
        <div className="doc-list">{docs.data.map((d) => {
          const lastVerification = d.verificaciones?.[d.verificaciones.length - 1]
          return (
            <article key={d.id} className="doc-item">
              <div className="doc-main">
                <strong>{d.requisito?.nombre ?? `Documento #${d.id}`}</strong>
                <p>
                  {d.recibido_at ? `Recibido presencialmente el ${formatDate(d.recibido_at)}` : 'Pendiente de recepción presencial'}
                  {d.obligatorio ? ' · Obligatorio' : ' · Complementario'}
                </p>
                {d.observaciones?.map((o) => <p key={o.id} className="doc-observation">{formatDate(o.created_at)}: {o.observacion}</p>)}
                {observing === d.id && (
                  <div className="observe-box">
                    <textarea placeholder="Explica qué debe corregir el estudiante" value={observacion} maxLength={2000} onChange={(e) => setObservacion(e.target.value)} />
                    <div><button className="btn btn-ghost" onClick={() => setObserving(null)}>Cancelar</button><button className="btn btn-danger" disabled={!observacion.trim() || action.busy} onClick={() => review(d, 'observado')}>Registrar observación</button></div>
                  </div>
                )}
              </div>
              <span className={`status ${d.estado === 'aprobado' ? 'approved' : d.estado === 'observado' ? '' : 'received'}`}><i />{docEstadoLabels[d.estado ?? ''] ?? d.estado}</span>
              <div className="doc-actions">
                {d.download_url && <button className="btn btn-ghost" onClick={() => action.run(() => download(d.download_url!, `${d.requisito?.nombre ?? 'documento'}.pdf`))}><Download size={14} /> Ver</button>}
                {editable && <>
                  {(!d.recibido_at || d.estado==='observado') && <button className="btn btn-outline" disabled={action.busy} onClick={()=>review(d,'presentado')}>{d.estado==='observado'?'Recibir corrección':'Registrar entrega'}</button>}
                  {d.recibido_at && d.estado==='presentado' && <button className="btn btn-primary" disabled={action.busy} onClick={()=>review(d,'aprobado')}><Check size={14}/>Validar documento</button>}
                  <button className="btn btn-ghost" disabled={action.busy} onClick={()=>{setObserving(d.id);setObservacion('')}}>Observar</button>
                </>}
              </div>
            </article>
          )
        })}</div>
      )}
    </Panel>
  )
}

// ---------------------------------------------------------------------------

async function loadSubjects(query: Record<string, string | number>): Promise<(Asignatura & { malla: string })[]> {
  const first = await api<Paginated<Malla>>('/coordinator/curricula', { query: { ...query, include_subjects: 1, per_page: 100 } })
  const pages = await Promise.all(Array.from({ length: first.meta.last_page - 1 }, (_, i) =>
    api<Paginated<Malla>>('/coordinator/curricula', { query: { ...query, include_subjects: 1, per_page: 100, page: i + 2 } })))
  return [first, ...pages].flatMap((page) => page.data.flatMap((m) =>
    (m.asignaturas ?? []).map((a) => ({ ...a, malla: m.nombre }))))
}

function Analisis({ s, onChanged }: { s: Detalle; onChanged: () => void }) {
  const estado = s.estado_actual?.nombre ?? ''
  const enProceso = estado === 'en_proceso'
  const comps = useAsync(() => api<{ data: Comparacion[] }>(`/coordinator/solicitudes/${s.id}/comparisons`).then((r) => r.data), [s.id])
  const subjects = useAsync(async () => {
    if (!enProceso || !s.estudiante || !s.carrera) return { origen: [], destino: [] }
    const [origen, destino] = await Promise.all([
      loadSubjects({ tipo: 'origen', estudiante: s.estudiante.id }),
      loadSubjects({ tipo: 'institucional', carrera: s.carrera.id, activa: 1 }),
    ])
    return { origen, destino }
  }, [s.id, enProceso])
  const action = useAction()
  const [form, setForm] = useState({ asignatura_origen_id: '', asignatura_destino_id: '', porcentaje_coincidencia: '', observacion: '' })
  const [result, setResult] = useState({ conclusion_general: 'total', total_creditos_reconocidos: '' })

  async function addComparison(event: FormEvent) {
    event.preventDefault()
    const ok = await action.run(() => api(`/coordinator/solicitudes/${s.id}/comparisons`, { method: 'POST', body: {
      asignatura_origen_id: Number(form.asignatura_origen_id), asignatura_destino_id: Number(form.asignatura_destino_id),
      porcentaje_coincidencia: Number(form.porcentaje_coincidencia), observacion: form.observacion || null,
    } }), 'Comparación registrada.')
    if (ok) { setForm({ asignatura_origen_id: '', asignatura_destino_id: '', porcentaje_coincidencia: '', observacion: '' }); comps.reload() }
  }

  async function removeComparison(id: number) {
    const ok = await action.run(() => api(`/coordinator/comparisons/${id}`, { method: 'DELETE' }), 'Comparación eliminada.')
    if (ok) comps.reload()
  }

  async function saveResult(event: FormEvent) {
    event.preventDefault()
    const ok = await action.run(() => api(`/coordinator/solicitudes/${s.id}/result`, { method: 'POST', body: { conclusion_general: result.conclusion_general, total_creditos_reconocidos: Number(result.total_creditos_reconocidos) } }), 'Resultado registrado.')
    if (ok) onChanged()
  }

  async function generateReport() {
    const ok = await action.run(() => api(`/coordinator/solicitudes/${s.id}/technical-report`, { method: 'POST' }), 'Informe técnico generado.')
    if (ok) onChanged()
  }

  const origen = subjects.data?.origen ?? []
  const destino = subjects.data?.destino ?? []
  const showSection = enProceso || (comps.data?.length ?? 0) > 0 || s.resultado

  if (!showSection) return null

  return (
    <Panel title="Análisis académico">
      <Alert error={comps.error ?? subjects.error ?? action.error} message={action.message} />
      {comps.data?.length ? (
        <div className="table-wrap"><table className="data-table">
          <thead><tr><th>Asignatura de origen</th><th>Asignatura UEB</th><th>Créditos</th><th>Coincidencia</th><th>Observación</th>{enProceso && <th />}</tr></thead>
          <tbody>{comps.data.map((c) => (
            <tr key={c.id}>
              <td><strong>{c.asignatura_origen?.codigo}</strong> {c.asignatura_origen?.nombre}</td>
              <td><strong>{c.asignatura_destino?.codigo}</strong> {c.asignatura_destino?.nombre}</td>
              <td>{c.asignatura_origen?.creditos} → {c.asignatura_destino?.creditos}</td>
              <td>{Number(c.porcentaje_coincidencia)}%</td><td>{c.observacion ?? '—'}</td>
              {enProceso && <td><button className="btn btn-ghost" onClick={() => removeComparison(c.id)} aria-label="Eliminar comparación"><Trash2 size={14} /></button></td>}
            </tr>
          ))}</tbody>
        </table></div>
      ) : <Empty>No hay comparaciones registradas.</Empty>}

      {enProceso && !s.resultado && (
        subjects.loading || (subjects.refreshing && !origen.length && !destino.length) ? <Loading text="Cargando mallas..." /> : (origen.length === 0 || destino.length === 0) ? (
          <div className="api-info">
            Para comparar necesitas {origen.length === 0 && <>una <strong>malla de origen</strong> del estudiante con asignaturas</>}{origen.length === 0 && destino.length === 0 && ' y '}{destino.length === 0 && <>una <strong>malla institucional activa</strong> de {s.carrera?.nombre} con asignaturas</>}. <a href={href('mallas')}>Ir a Mallas curriculares</a>
          </div>
        ) : (
          <form className="form-grid" onSubmit={addComparison}>
            <Field label="Asignatura de origen" error={fieldError(action.error, 'asignatura_origen_id')}>
              <select required value={form.asignatura_origen_id} onChange={(e) => setForm({ ...form, asignatura_origen_id: e.target.value })}><option value="">Selecciona…</option>{origen.map((a) => <option key={a.id} value={a.id}>{a.codigo_asignatura} · {a.nombre_asignatura} ({a.numero_creditos} cr.)</option>)}</select>
            </Field>
            <Field label="Asignatura UEB" error={fieldError(action.error, 'asignatura_destino_id')}>
              <select required value={form.asignatura_destino_id} onChange={(e) => setForm({ ...form, asignatura_destino_id: e.target.value })}><option value="">Selecciona…</option>{destino.map((a) => <option key={a.id} value={a.id}>{a.codigo_asignatura} · {a.nombre_asignatura} ({a.numero_creditos} cr.)</option>)}</select>
            </Field>
            <Field label="Coincidencia (%)" error={fieldError(action.error, 'porcentaje_coincidencia')}><input required type="number" min={0} max={100} step="0.01" value={form.porcentaje_coincidencia} onChange={(e) => setForm({ ...form, porcentaje_coincidencia: e.target.value })} /></Field>
            <Field label="Observación (opcional)"><input maxLength={2000} value={form.observacion} onChange={(e) => setForm({ ...form, observacion: e.target.value })} /></Field>
            <div className="form-actions"><button className="btn btn-outline" disabled={action.busy}>Agregar comparación</button></div>
          </form>
        )
      )}

      {enProceso && !s.resultado && (comps.data?.length ?? 0) > 0 && (
        <form className="form-grid result-form" onSubmit={saveResult}>
          <Field label="Conclusión general" error={fieldError(action.error, 'conclusion_general')}>
            <select value={result.conclusion_general} onChange={(e) => setResult({ ...result, conclusion_general: e.target.value })}>{Object.entries(conclusionLabels).map(([v, l]) => <option key={v} value={v}>{l}</option>)}</select>
          </Field>
          <Field label="Créditos reconocidos" error={fieldError(action.error, 'total_creditos_reconocidos')}><input required type="number" min={0} value={result.total_creditos_reconocidos} onChange={(e) => setResult({ ...result, total_creditos_reconocidos: e.target.value })} /></Field>
          <div className="form-actions"><button className="btn btn-primary" disabled={action.busy}>Registrar resultado</button></div>
          <p className="muted form-note">Total o parcial cambia la solicitud a Aprobado; Rechazada la cambia a Rechazado.</p>
        </form>
      )}

      {s.resultado && (
        <div className="result-summary">
          <KeyValue items={[
            ['Conclusión', conclusionLabels[s.resultado.conclusion_general] ?? s.resultado.conclusion_general],
            ['Créditos reconocidos', s.resultado.total_creditos_reconocidos],
            ['Informe técnico', s.resultado.informe_tecnico_disponible ? `Generado ${formatDate(s.resultado.informe_generado_at, true)}` : 'No generado'],
          ]} />
          <div className="page-actions">
            {estado === 'aprobado' && !s.resultado.informe_tecnico_disponible && <button className="btn btn-primary" disabled={action.busy} onClick={generateReport}><FileText size={15} /> Generar informe técnico</button>}
            {s.resultado.informe_tecnico_disponible && <button className="btn btn-outline" onClick={() => action.run(() => download(`/coordinator/solicitudes/${s.id}/technical-report`, `informe-tecnico-${s.id}.pdf`))}><Download size={15} /> Descargar informe</button>}
          </div>
        </div>
      )}
    </Panel>
  )
}

// ---------------------------------------------------------------------------

export function ResolucionForm({ endpoint, onDone }: { endpoint: string; onDone: () => void }) {
  const action = useAction()
  const [numero, setNumero] = useState('')
  const [fecha, setFecha] = useState(new Date().toISOString().slice(0, 10))
  const [file, setFile] = useState<File | null>(null)

  async function submit(event: FormEvent) {
    event.preventDefault()
    if (!file) return
    const ok = await action.run(() => upload(endpoint, { numero_resolucion: numero, fecha_aprobacion: fecha, archivo: file }), 'Resolución registrada. La solicitud quedó finalizada.')
    if (ok) onDone()
  }

  return (
    <Panel title="Registrar resolución del Consejo">
      <Alert error={action.error} message={action.message} />
      <form className="form-grid" onSubmit={submit}>
        <Field label="Número de resolución" error={fieldError(action.error, 'numero_resolucion')}><input required maxLength={100} value={numero} onChange={(e) => setNumero(e.target.value)} /></Field>
        <Field label="Fecha de aprobación" error={fieldError(action.error, 'fecha_aprobacion')}><input required type="date" value={fecha} onChange={(e) => setFecha(e.target.value)} /></Field>
        <Field label="PDF de la resolución" error={fieldError(action.error, 'archivo')}><input required type="file" accept="application/pdf" onChange={(e) => setFile(e.target.files?.[0] ?? null)} /></Field>
        <div className="form-actions"><button className="btn btn-primary" disabled={action.busy || !file}>{action.busy ? 'Registrando...' : 'Registrar y finalizar'}</button></div>
      </form>
    </Panel>
  )
}

function CambioEstado({ s, onDone }: { s: Detalle; onDone: () => void }) {
  const catalogo = useAsync(() => getCoordinatorCatalogo(), [])
  const action = useAction()
  const [estado, setEstado] = useState('')
  const [observacion, setObservacion] = useState('')
  const actual = s.estado_actual?.nombre ?? ''

  // en_proceso avanza al registrar el resultado; en_consejo → listo al registrar la resolución.
  const opciones = (catalogo.data?.transiciones[actual] ?? []).filter((t) => {
    if (actual === 'en_proceso') return false
    if (t === 'listo') return false
    if (actual === 'en_revision' && t === 'observado') return false // ocurre al observar un documento
    return true
  })

  async function submit(event: FormEvent) {
    event.preventDefault()
    const ok = await action.run(() => api(`/coordinator/solicitudes/${s.id}/state`, { method: 'POST', body: { estado, observacion: observacion || null } }), `Estado cambiado a ${estadoLabel(estado)}.`)
    if (ok) { setEstado(''); setObservacion(''); onDone() }
  }

  if (!opciones.length) return null

  return (
    <Panel title="Avanzar la solicitud">
      <Alert error={action.error} message={action.message} />
      <form className="form-grid" onSubmit={submit}>
        <Field label="Nuevo estado"><select required value={estado} onChange={(e) => setEstado(e.target.value)}><option value="">Selecciona…</option>{opciones.map((o) => <option key={o} value={o}>{estadoLabel(o)}</option>)}</select></Field>
        <Field label={estado === 'rechazado' ? 'Motivo del rechazo (obligatorio)' : 'Observación (opcional)'} error={fieldError(action.error, 'observacion')}><input required={estado === 'rechazado'} maxLength={2000} value={observacion} onChange={(e) => setObservacion(e.target.value)} /></Field>
        <div className="form-actions"><button className={`btn ${estado === 'rechazado' ? 'btn-danger' : 'btn-primary'}`} disabled={action.busy || !estado}>Confirmar</button></div>
      </form>
    </Panel>
  )
}

// ---------------------------------------------------------------------------

export function CoordinatorSolicitudDetalle({ id }: { id: number }) {
  const detail = useAsync(() => api<{ data: Detalle }>(`/coordinator/solicitudes/${id}`).then((r) => r.data), [id])
  const refresh = () => { void detail.reload() }

  if (detail.loading && !detail.data) return <Loading />
  if (!detail.data) return <><PageHeader title={`Solicitud #${id}`} back="solicitudes" /><Alert error={detail.error instanceof ApiError && detail.error.status === 404 ? new Error('La solicitud no existe o no pertenece a tus carreras.') : detail.error} onRetry={detail.reload} /></>
  const s = detail.data
  const estado = s.estado_actual?.nombre ?? ''

  return (
    <>
      <PageHeader back="solicitudes" kicker={`SOLICITUD #${s.id}`} title={s.estudiante?.nombres_completos ?? `Solicitud #${s.id}`} subtitle={s.tramite ? `${s.tramite.tipo_tramite.nombre} · ${s.tramite.tipo_proceso.nombre}` : undefined} actions={<StatusPill estado={estado} />} />
      <Alert error={detail.error} onRetry={detail.reload} />
      {detail.refreshing && <p className="muted" role="status">Actualizando expediente…</p>}
      {estado === 'pendiente' && <div className="api-info">El estudiante todavía no envía esta solicitud a revisión.</div>}
      <div className="grid-2">
        <Panel title="Expediente">
          <KeyValue items={[['Estudiante', s.estudiante?.nombres_completos], ['Cédula', s.estudiante?.cedula], ['Correo', s.estudiante?.email], ['Carrera de destino', s.carrera?.nombre], ['Procedencia', s.procedencia_estudios], ['Creada', formatDate(s.created_at, true)]]} />
        </Panel>
        <Panel title="Historial de estados">
          <Timeline items={(s.historial_estados ?? []).slice().reverse().map((h) => ({ id: h.id, estado: h.estado.nombre, observacion: h.observacion, fecha: formatDate(h.created_at, true), actor: h.usuario_responsable?.nombres_completos }))} />
        </Panel>
      </div>
      <CambioEstado s={s} onDone={refresh} />
      <Documentos solicitudId={s.id} estado={estado} onChanged={refresh} />
      <Analisis s={s} onChanged={refresh} />
      {estado === 'en_consejo' && !s.resolucion && <ResolucionForm endpoint={`/coordinator/solicitudes/${s.id}/resolution`} onDone={refresh} />}
      {s.resolucion && (
        <Panel title="Resolución">
          <KeyValue items={[['Número', s.resolucion.numero_resolucion], ['Fecha de aprobación', formatDate(s.resolucion.fecha_aprobacion)]]} />
          <button className="btn btn-outline" onClick={() => download(s.resolucion!.download_url, `resolucion-${s.id}.pdf`)}><Download size={15} /> Descargar resolución</button>
        </Panel>
      )}
    </>
  )
}
