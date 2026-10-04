'use client'

import { FormEvent, useState } from 'react'
import { ArrowRight, Plus } from 'lucide-react'
import { api, Paginated, SolicitudEstudiante } from '@/lib/api'
import { estadoLabels, formatDate } from '@/lib/format'
import { Procedure, procedureLabel, Requirement, requirementsFor } from '@/lib/requirements'
import { href, navigate } from '@/lib/router'
import { Alert, Empty, Field, fieldError, Loading, PageHeader, Pager, Panel, StatusPill, useAction, useAsync } from '@/components/app/ui'

type Catalogo = {
  tramites: Procedure[]
  requisitos: Requirement[]
  procedencia_estudios: string | null
  procedencia_configurada: boolean
  asignaciones: { coordinador_carrera_id: number; carrera: { id: number; nombre: string }; coordinador: { id: number; nombres_completos: string }; disponible: boolean }[]
}

function NuevaSolicitud({ onCancel }: { onCancel: () => void }) {
  const catalogo = useAsync(() => api<{ data: Catalogo }>('/student/catalogo').then((r) => r.data), [])
  const action = useAction()
  const [form, setForm] = useState({ coordinador_carrera_id: '', tramite_proceso_id: '', procedencia_estudios: '' })
  const origin = catalogo.data?.procedencia_configurada ? catalogo.data.procedencia_estudios ?? '' : form.procedencia_estudios
  const selectedAssignment = catalogo.data?.asignaciones.find(a => a.coordinador_carrera_id === Number(form.coordinador_carrera_id))
  const requirements = selectedAssignment && form.tramite_proceso_id ? requirementsFor(catalogo.data?.requisitos ?? [], selectedAssignment.carrera.id, Number(form.tramite_proceso_id)) : []
  const ready = !!selectedAssignment?.disponible && requirements.some(r => r.obligatorio) && !catalogo.error

  async function submit(event: FormEvent) {
    event.preventDefault()
    const created = await action.run(() => api<{ data: { id: number } }>('/student/solicitudes', {
      method: 'POST',
      body: { coordinador_carrera_id: Number(form.coordinador_carrera_id), tramite_proceso_id: Number(form.tramite_proceso_id), procedencia_estudios: origin },
    }))
    if (created) navigate(`solicitudes/${created.data.id}`)
  }

  if (catalogo.loading) return <Panel title="Nueva solicitud"><Loading /></Panel>
  const asignaciones = catalogo.data?.asignaciones ?? []

  return (
    <Panel title="Nueva solicitud" actions={<button className="btn btn-ghost" onClick={onCancel}>Cancelar</button>}>
      <Alert error={catalogo.error ?? action.error} />
      {asignaciones.length === 0 ? (
        <Empty>No tienes una carrera ni un coordinador asignados. Solicítalo al Administrador antes de crear una solicitud.</Empty>
      ) : (
        <form className="form-grid" onSubmit={submit}>
          <Field label="Carrera de destino" error={fieldError(action.error, 'coordinador_carrera_id')}>
            <select required value={form.coordinador_carrera_id} onChange={(e) => setForm({ ...form, coordinador_carrera_id: e.target.value })}>
              <option value="">Selecciona…</option>
              {asignaciones.map((a) => <option key={a.coordinador_carrera_id} value={a.coordinador_carrera_id} disabled={!a.disponible}>{a.carrera.nombre} — {a.coordinador.nombres_completos}{a.disponible ? '' : ' (no disponible)'}</option>)}
            </select>
          </Field>
          <Field label="Trámite y proceso" error={fieldError(action.error, 'tramite_proceso_id')}>
            <select required value={form.tramite_proceso_id} onChange={(e) => setForm({ ...form, tramite_proceso_id: e.target.value })}>
              <option value="">Selecciona…</option>
              {catalogo.data?.tramites.map((t) => <option key={t.id} value={t.id}>{procedureLabel(t)}</option>)}
            </select>
          </Field>
          <Field label="Institución de origen" error={fieldError(action.error, 'procedencia_estudios')} hint={catalogo.data?.procedencia_configurada ? 'Registrada por tu coordinador. No necesitas volver a escribirla.' : 'Universidad o instituto donde cursaste las materias.'}>
            <input readOnly={catalogo.data?.procedencia_configurada} required maxLength={255} value={origin} onChange={(e) => setForm({ ...form, procedencia_estudios: e.target.value })} />
          </Field>
          {selectedAssignment && form.tramite_proceso_id && <div className="student-requirements-preview"><strong>Documentos para este trámite</strong><p>Entrega estos documentos a tu coordinador. Su validación se realiza presencialmente.</p>{requirements.length ? <ul className="requirement-preview-list">{requirements.map(r => <li key={r.id}><div><strong>{r.nombre}</strong>{r.descripcion && <p>{r.descripcion}</p>}</div><span className="badge">{r.obligatorio ? 'Obligatorio' : 'Complementario'}</span></li>)}</ul> : <Empty>El coordinador debe configurar los requisitos de esta carrera y trámite.</Empty>}{requirements.length > 0 && !requirements.some(r => r.obligatorio) && <p className="form-error">Falta configurar al menos un documento obligatorio antes de crear esta solicitud.</p>}</div>}
          <div className="form-actions"><button className="btn btn-primary" disabled={action.busy || !ready}>{action.busy ? 'Creando...' : 'Crear solicitud'}</button></div>
        </form>
      )}
    </Panel>
  )
}

export function StudentSolicitudes() {
  const [creating, setCreating] = useState(false)
  const [estado, setEstado] = useState('')
  const [page, setPage] = useState(1)
  const list = useAsync(() => api<Paginated<SolicitudEstudiante>>('/student/solicitudes', { query: { estado, page, per_page: 15 } }), [estado, page])

  return (
    <>
      <PageHeader kicker="MIS TRÁMITES" title="Mis solicitudes" subtitle="Crea tu solicitud, entrega los documentos al coordinador y consulta tu checklist de revisión presencial." actions={!creating && <button className="btn btn-primary" onClick={() => setCreating(true)}><Plus size={15} /> Nueva solicitud</button>} />
      {creating && <NuevaSolicitud onCancel={() => setCreating(false)} />}
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
                <td>{s.id}</td><td>{s.tramite ? `${s.tramite.tipo_tramite} · ${s.tramite.tipo_proceso}` : '—'}</td><td>{s.carrera?.nombre ?? '—'}</td><td>{s.procedencia_estudios}</td><td>{formatDate(s.created_at)}</td><td><StatusPill estado={s.estado_actual} /></td>
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
