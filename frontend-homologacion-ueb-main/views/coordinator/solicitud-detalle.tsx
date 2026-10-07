'use client'

import { FormEvent, useState } from 'react'
import { Check, Download, FileText, X } from 'lucide-react'
import { api, ApiError, download, Paginated, upload } from '@/lib/api'
import { conclusionLabels, docEstadoLabels, estadoLabel, formatDate } from '@/lib/format'
import { href } from '@/lib/router'
import { tramiteLabel } from '@/lib/requirements'
import { Alert, Empty, Field, fieldError, KeyValue, Loading, PageHeader, Panel, StatusPill, Timeline, useAction, useAsync } from '@/components/app/ui'
import { Malla } from './catalogo'

type Detalle = {
  id: number
  carrera_origen?: string | null
  procedencia_estudios: string
  carrera?: { id: number; nombre: string } | null
  estudiante?: { id: number; nombres_completos: string; cedula: string; email: string; tiene_proceso_previo?: boolean; detalle_proceso_previo?: string | null; procesos_anteriores?: number; antecedente_academico?: {universidad_origen: string; carrera_origen: string} }
  tramite?: { tipo_tramite: { nombre: string }; tipo_proceso: { nombre: string } }
  estado_actual?: { id: number; nombre: string } | null
  historial_estados?: { id: number; estado: { nombre: string }; observacion: string | null; etapa_origen: string | null; usuario_responsable: { nombres_completos: string } | null; created_at: string }[]
  resultado?: { conclusion_general: string; total_creditos_reconocidos: number; total_creditos_destino?: number | null; porcentaje_cobertura?: number | null; informe_tecnico_disponible: boolean; informe_generado_at: string | null } | null
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

type SubjectRef = { id: number; codigo: string; nombre: string; creditos: number; nivel_ciclo: string; malla?: { id: number; nombre: string } }
type Comparacion = { id: number; asignatura_origen?: SubjectRef; asignaturas_origen?: SubjectRef[]; creditos_origen_total?: number; asignatura_destino?: SubjectRef; porcentaje_coincidencia: number; observacion: string | null }

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

  // Validar = registrar la recepción (si falta) y validar, en un solo paso.
  async function validar(doc: Documento) {
    const ok = await action.run(async () => {
      if (!(doc.recibido_at && doc.estado === 'presentado')) {
        await api(`/coordinator/documents/${doc.id}/review`, { method: 'PATCH', body: { estado: 'presentado' } })
      }
      return api(`/coordinator/documents/${doc.id}/review`, { method: 'PATCH', body: { estado: 'aprobado' } })
    }, 'Documento validado.')
    if (ok) { docs.reload(); onChanged() }
  }

  return (
    <Panel title="Documentación" actions={editable ? <span className="muted">Valida cada documento o usa Observar si debe corregirse.</span> : null}>
      <Alert error={docs.error ?? action.error} message={action.message} />
      {docs.loading && !docs.data ? <Loading /> : !docs.data?.length ? <Empty>Sin documentos requeridos.</Empty> : (
        <div className="doc-list">{docs.data.map((d) => {
          const validado = d.estado === 'aprobado'
          return (
            <article key={d.id} className="doc-item" style={{ gridTemplateColumns: 'minmax(0, 1fr) auto' }}>
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
              <div className="doc-actions">
                {editable && <>
                  <button type="button" className="btn btn-outline" aria-pressed={validado} disabled={validado || action.busy} onClick={() => validar(d)}
                    style={{ display: 'inline-flex', alignItems: 'center', gap: 8, ...(validado ? { color: '#076a4e', borderColor: '#9fd6bf', background: '#e8f6ef', opacity: 1 } : {}) }}>
                    <span aria-hidden="true" style={{ width: 18, height: 18, borderRadius: 5, display: 'grid', placeItems: 'center', color: '#fff', border: `2px solid ${validado ? '#13835e' : '#7d92ad'}`, background: validado ? '#13835e' : '#fff' }}>
                      {validado && <Check size={12} strokeWidth={3} />}
                    </span>
                    {validado ? 'Validado' : 'Validar'}
                  </button>
                  <button className="btn btn-ghost" disabled={action.busy} onClick={() => { setObserving(d.id); setObservacion('') }}>Observar</button>
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

async function loadCurricula(query: Record<string, string | number>) {
  const first = await api<Paginated<Malla>>('/coordinator/curricula', { query: { ...query, per_page: 100 } })
  const pages = await Promise.all(Array.from({ length: first.meta.last_page - 1 }, (_, i) => api<Paginated<Malla>>('/coordinator/curricula', { query: { ...query, per_page: 100, page: i + 2 } })))
  return [first, ...pages].flatMap(page => page.data)
}

function Analisis({ s, onChanged }: { s: Detalle; onChanged: () => void }) {
  const estado = s.estado_actual?.nombre ?? ''
  const editable = estado === 'en_proceso'
  const comps = useAsync(() => api<{ data: Comparacion[] }>(`/coordinator/solicitudes/${s.id}/comparisons`).then(r => r.data), [s.id, estado])
  const curricula = useAsync(async () => {
    if (!editable || !s.estudiante || !s.carrera) return { origen: [], destino: [] }
    const [origen, destino] = await Promise.all([
      loadCurricula({ tipo: 'origen', estudiante: s.estudiante.id, activa: 1, include_subjects: 1 }),
      loadCurricula({ tipo: 'institucional', carrera: s.carrera.id, activa: 1, include_subjects: 1 }),
    ])
    return { origen: origen.filter(m => m.asignaturas?.length), destino: destino.filter(m => m.asignaturas?.some(a => a.numero_creditos > 0)) }
  }, [s.id, editable])
  const action = useAction()
  const [pair, setPair] = useState({ origen: '', destino: '' })
  const [editing, setEditing] = useState<number | null>(null)
  const [origins, setOrigins] = useState<number[]>([])
  const [target, setTarget] = useState('')
  const [percentage, setPercentage] = useState('')
  const [note, setNote] = useState('')
  const [conclusionNote, setConclusionNote] = useState('')
  const rows = comps.data ?? []
  const sourcesOf = (c: Comparacion) => c.asignaturas_origen?.length ? c.asignaturas_origen : c.asignatura_origen ? [c.asignatura_origen] : []
  const selectedOrigin = pair.origen || String(rows[0]?.asignatura_origen?.malla?.id ?? '')
  const selectedDestination = pair.destino || String(rows[0]?.asignatura_destino?.malla?.id ?? '')
  const originCurriculum = curricula.data?.origen.find(m => m.id === Number(selectedOrigin))
  const destinationCurriculum = curricula.data?.destino.find(m => m.id === Number(selectedDestination))
  const sources = originCurriculum?.asignaturas ?? []
  const destinations = destinationCurriculum?.asignaturas ?? []
  const otherRows = rows.filter(c => c.id !== editing)
  const usedOrigins = new Set(otherRows.flatMap(c => sourcesOf(c).map(a => a.id)))
  const usedTargets = new Set(otherRows.map(c => c.asignatura_destino?.id))
  const credits = sources.filter(a => origins.includes(a.id)).reduce((sum, a) => sum + Number(a.numero_creditos), 0)
  const destinationCredits = Number(destinations.find(a => a.id === Number(target))?.numero_creditos ?? 0)
  function clearForm() { setEditing(null); setOrigins([]); setTarget(''); setPercentage(''); setNote('') }
  async function save(event: FormEvent) {
    event.preventDefault()
    const ok = await action.run(() => api(editing ? `/coordinator/comparisons/${editing}` : `/coordinator/solicitudes/${s.id}/comparisons`, { method: editing ? 'PUT' : 'POST', body: { asignaturas_origen_ids: origins, asignatura_destino_id: Number(target), porcentaje_coincidencia: Number(percentage), observacion: note || null } }), 'Equivalencia manual guardada. La revisión continúa abierta.')
    if (ok) { clearForm(); await comps.reload(); onChanged() }
  }
  async function remove(id: number) {
    const ok = await action.run(() => api(`/coordinator/comparisons/${id}`, { method: 'DELETE' }), 'Equivalencia eliminada.')
    if (ok) { clearForm(); await comps.reload(); onChanged() }
  }
  async function finish(event: FormEvent) {
    event.preventDefault()
    const ok = await action.run(() => api(`/coordinator/solicitudes/${s.id}/compare-curricula`, { method: 'POST', body: { malla_origen_id: Number(selectedOrigin), malla_destino_id: Number(selectedDestination), observacion: conclusionNote || null } }), 'Análisis manual finalizado. Ya puedes generar el informe académico.')
    if (ok) { await comps.reload(); onChanged() }
  }
  async function generateReport() {
    const ok = await action.run(() => api(`/coordinator/solicitudes/${s.id}/technical-report`, { method: 'POST' }), 'Informe académico generado. Solicitud terminada.')
    if (ok) onChanged()
  }
  if (!editable && !s.resultado && !rows.length) return null
  return <Panel title="Análisis académico manual">
    <Alert error={comps.error ?? curricula.error ?? action.error} message={action.message} />
    {editable && (curricula.loading ? <Loading text="Cargando mallas..." /> : !curricula.data?.origen.length || !curricula.data.destino.length ? <div className="api-info">Antes de proceder, carga una malla de origen del estudiante y una malla institucional de destino, ambas activas y con materias y créditos. <a href={href('mallas')}>Ir a Mallas curriculares</a></div> : <>
      <div className="analysis-guide"><strong>1. Selecciona las mallas · 2. Registra equivalencias · 3. Finaliza la revisión</strong><p>El coordinador evalúa contenidos y asigna el porcentaje de coincidencia. Puedes combinar varias materias de origen para una de destino. No se decide por el nombre de las materias.</p></div>
      <div className="form-grid">
        <Field label="Malla de origen"><select required disabled={rows.length > 0} value={selectedOrigin} onChange={e => { setPair({ ...pair, origen: e.target.value }); clearForm() }}><option value="">Selecciona una malla</option>{curricula.data?.origen.map(m => <option key={m.id} value={m.id}>{m.nombre}</option>)}</select></Field>
        <Field label="Malla de destino UEB"><select required disabled={rows.length > 0} value={selectedDestination} onChange={e => { setPair({ ...pair, destino: e.target.value }); clearForm() }}><option value="">Selecciona una malla</option>{curricula.data?.destino.map(m => <option key={m.id} value={m.id}>{m.nombre}</option>)}</select></Field>
      </div>
      {selectedOrigin && selectedDestination && <form className="form-grid manual-comparison-form" onSubmit={save}>
        <fieldset className="origin-subjects"><legend>Materias de origen <span className="required-mark">*</span></legend><p className="field-hint">Selecciona una o varias. Los créditos se suman; una materia no puede utilizarse dos veces.</p>{sources.map(a => <label key={a.id}><input type="checkbox" disabled={usedOrigins.has(a.id)} checked={origins.includes(a.id)} onChange={e => setOrigins(e.target.checked ? [...origins, a.id] : origins.filter(id => id !== a.id))} /><span><strong>{a.codigo_asignatura} · {a.nombre_asignatura}</strong><small>{a.numero_creditos} créditos{usedOrigins.has(a.id) ? ' · Ya utilizada' : ''}</small></span></label>)}</fieldset>
        <Field label="Materia de destino"><select required value={target} onChange={e => setTarget(e.target.value)}><option value="">Selecciona una materia</option>{destinations.map(a => <option key={a.id} value={a.id} disabled={usedTargets.has(a.id)}>{a.codigo_asignatura} · {a.nombre_asignatura} ({a.numero_creditos} créditos){usedTargets.has(a.id) ? ' ? Ya comparada' : ''}</option>)}</select></Field>
        <Field label="Coincidencia de contenidos (%)" hint="Valor evaluado por el coordinador, entre 0 y 100." error={fieldError(action.error, 'porcentaje_coincidencia')}><input required type="number" min={0} max={100} step="0.01" value={percentage} onChange={e => setPercentage(e.target.value)} /></Field>
        <Field label="Justificación de la equivalencia"><textarea maxLength={2000} value={note} onChange={e => setNote(e.target.value)} placeholder="Describe los contenidos revisados y el motivo de tu decisión." /></Field>
        <div className="analysis-credit-summary">Créditos de origen: <strong>{credits}</strong> · Destino: <strong>{destinationCredits}</strong></div>
        <div className="form-actions">{editing && <button type="button" className="btn btn-outline" onClick={clearForm}>Cancelar edición</button>}<button className="btn btn-primary" disabled={action.busy || !origins.length || !target}>{editing ? 'Guardar equivalencia' : 'Añadir equivalencia'}</button></div>
      </form>}
    </>)}
    {rows.length > 0 && <div className="table-wrap"><table className="data-table"><thead><tr><th>Materias de origen</th><th>Materia UEB</th><th>Créditos origen / destino</th><th>Coincidencia</th><th>Justificación</th>{editable && <th>Acciones</th>}</tr></thead><tbody>{rows.map(c => <tr key={c.id}><td>{sourcesOf(c).map(a => <div key={a.id}>{a.codigo} · {a.nombre}</div>)}</td><td>{c.asignatura_destino?.nombre}</td><td>{c.creditos_origen_total ?? c.asignatura_origen?.creditos} / {c.asignatura_destino?.creditos}</td><td>{Number(c.porcentaje_coincidencia)}%</td><td>{c.observacion || 'Sin observación'}</td>{editable && <td><div className="row-actions"><button className="btn btn-ghost" disabled={action.busy} onClick={() => { setEditing(c.id); setOrigins(sourcesOf(c).map(a => a.id)); setTarget(String(c.asignatura_destino?.id ?? '')); setPercentage(String(Number(c.porcentaje_coincidencia))); setNote(c.observacion ?? '') }}>Editar</button><button className="btn btn-danger" disabled={action.busy} onClick={() => remove(c.id)}>Eliminar</button></div></td>}</tr>)}</tbody></table></div>}
        {editable && selectedOrigin && selectedDestination && <form className="analysis-finalize" onSubmit={finish}><Field label="Conclusión de la revisión" hint={!rows.length ? 'Explica por qué no existen equivalencias; este texto es obligatorio si no registraste ninguna.' : undefined}><textarea required={!rows.length} maxLength={2000} value={conclusionNote} onChange={e => setConclusionNote(e.target.value)} /></Field><button className="btn btn-primary" disabled={action.busy || editing !== null}>Finalizar análisis manual</button></form>}
    {s.resultado && <div className="result-summary"><KeyValue items={[
      [editable ? 'Resultado provisional' : 'Resultado', conclusionLabels[s.resultado.conclusion_general] ?? s.resultado.conclusion_general],
      ['Créditos reconocidos', s.resultado.total_creditos_reconocidos],
      ['Créditos de destino', s.resultado.total_creditos_destino],
      ['Cobertura de destino', s.resultado.porcentaje_cobertura != null ? s.resultado.porcentaje_cobertura + '%' : 'Sin registro'],
      ['Informe', s.resultado.informe_tecnico_disponible ? 'Disponible' : 'Pendiente de generación'],
    ]} /><div className="page-actions">
      {['revisado', 'aprobado'].includes(estado) && !s.resultado.informe_tecnico_disponible && <button className="btn btn-primary" disabled={action.busy} onClick={generateReport}><FileText size={15} /> Generar informe académico</button>}
      {s.resultado.informe_tecnico_disponible && <button className="btn btn-outline" disabled={action.busy} onClick={() => action.run(() => download(`/coordinator/solicitudes/${s.id}/technical-report`, `informe-academico-${s.id}.pdf`))}><Download size={15} /> Descargar informe</button>}
    </div></div>}
  </Panel>
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

// ---------------------------------------------------------------------------

export function CoordinatorSolicitudDetalle({ id }: { id: number }) {
  const detail = useAsync(() => api<{ data: Detalle }>(`/coordinator/solicitudes/${id}`).then((r) => r.data), [id])
  const refresh = () => { void detail.reload() }

  if (detail.loading && !detail.data) return <Loading />
  if (!detail.data) return <><PageHeader icon={FileText} title={`Solicitud #${id}`} back="solicitudes" /><Alert error={detail.error instanceof ApiError && detail.error.status === 404 ? new Error('La solicitud no existe o no pertenece a tu coordinación.') : detail.error} onRetry={detail.reload} /></>
  const s = detail.data
  const estado = s.estado_actual?.nombre ?? ''

  return (
    <>
      <PageHeader icon={FileText} back="solicitudes" kicker={`SOLICITUD #${s.id}`} title={s.estudiante?.nombres_completos ?? `Solicitud #${s.id}`} subtitle={s.tramite ? tramiteLabel(s.tramite) : undefined} actions={<StatusPill estado={estado} />} />
      <Alert error={detail.error} onRetry={detail.reload} />
      {(s.estudiante?.procesos_anteriores ?? 0) > 0 && <div className="api-info">Este estudiante registra {s.estudiante?.procesos_anteriores} proceso(s) anterior(es). Consulta su historial en Estudiantes → Procesos.</div>}
      {s.carrera_origen && <Panel title="Origen de los estudios"><KeyValue items={[["Universidad de origen", s.procedencia_estudios], ["Carrera de origen", s.carrera_origen], ["Carrera de destino UEB", s.carrera?.nombre]]} /></Panel>}
      {detail.refreshing && <p className="muted" role="status">Actualizando expediente…</p>}
      {estado === 'pendiente' && <div className="api-info">La revisión iniciará al registrar la entrega presencial de los documentos.</div>}
      <div className="grid-2">
        <Panel title="Expediente">
          <KeyValue items={[['Estudiante', s.estudiante?.nombres_completos], ['Cédula', s.estudiante?.cedula], ['Correo', s.estudiante?.email], ['Carrera de destino', s.carrera?.nombre], ['Procedencia', s.procedencia_estudios], ['Creada', formatDate(s.created_at, true)]]} />
        </Panel>
        <Panel title="Historial de estados">
          <Timeline items={(s.historial_estados ?? []).slice().reverse().map((h) => ({ id: h.id, estado: h.estado.nombre, observacion: h.observacion, fecha: formatDate(h.created_at, true), actor: h.usuario_responsable?.nombres_completos }))} />
        </Panel>
      </div>
      <div className="api-info">El estado avanza automáticamente con la revisión documental, la comparación de mallas y la generación del informe.</div>
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
