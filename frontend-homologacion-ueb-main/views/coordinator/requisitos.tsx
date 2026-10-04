'use client'

import { FormEvent, useState } from 'react'
import { FileCheck2, Pencil, Plus, Trash2 } from 'lucide-react'
import { api } from '@/lib/api'
import { Procedure, procedureLabel, Requirement } from '@/lib/requirements'
import { Alert, Empty, Field, fieldError, Loading, PageHeader, Panel, useAction, useAsync } from '@/components/app/ui'

type Catalog = { carreras: { id: number; nombre: string; activa: boolean }[]; tramites: Procedure[]; requisitos: Requirement[]; generales: Requirement[] }

function RequirementForm({ careerId, procedureId, entry, onSaved, onCancel }: { careerId: number; procedureId: number; entry?: Requirement; onSaved: () => void; onCancel: () => void }) {
  const action = useAction()
  const [form, setForm] = useState({ nombre: entry?.nombre ?? '', descripcion: entry?.descripcion ?? '', activa: entry?.activa ?? true, obligatorio: entry?.obligatorio ?? true })
  async function submit(event: FormEvent) {
    event.preventDefault()
    const result = await action.run(() => api(`/coordinator/requirements${entry ? `/${entry.id}` : ''}`, { method: entry ? 'PUT' : 'POST', body: { ...form, nombre: form.nombre.trim(), carrera_id: careerId, tramite_proceso_id: procedureId } }))
    if (result) onSaved()
  }
  return <Panel title={entry ? 'Editar requisito' : 'Nuevo requisito'}>
    <Alert error={action.error} />
    <form className="form-grid management-form" onSubmit={submit}>
      <Field label="Documento o requisito" error={fieldError(action.error, 'nombre')}><input autoFocus required maxLength={150} value={form.nombre} onChange={e => setForm({ ...form, nombre: e.target.value })} placeholder="Ej.: Certificado de notas" /></Field>
      <Field label="Disponibilidad"><select value={form.activa ? '1' : '0'} onChange={e => setForm({ ...form, activa: e.target.value === '1' })}><option value="1">Activo para nuevas solicitudes</option><option value="0">Inactivo</option></select></Field>
      <Field label="Indicaciones para el estudiante" error={fieldError(action.error, 'descripcion')}><textarea maxLength={2000} rows={3} value={form.descripcion} onChange={e => setForm({ ...form, descripcion: e.target.value })} placeholder="Qué debe presentar y cómo se entrega." /></Field>
      <Field label="Obligatoriedad"><select value={form.obligatorio ? '1' : '0'} onChange={e => setForm({ ...form, obligatorio: e.target.value === '1' })}><option value="1">Obligatorio</option><option value="0">Complementario</option></select></Field>
      <p className="muted">Los cambios se aplican a nuevas solicitudes. Los expedientes existentes conservan sus documentos e instrucciones.</p>
      <div className="form-actions"><button type="button" className="btn btn-outline" disabled={action.busy} onClick={onCancel}>Cancelar</button><button className="btn btn-primary" disabled={action.busy}>{action.busy ? 'Guardando…' : 'Guardar requisito'}</button></div>
    </form>
  </Panel>
}

export function CoordinatorRequirements() {
  const catalog = useAsync(() => api<{ data: Catalog }>('/coordinator/requirements').then(r => r.data), [])
  const action = useAction()
  const [career, setCareer] = useState('')
  const [procedure, setProcedure] = useState('')
  const [editing, setEditing] = useState<Requirement | 'new' | null>(null)
  const [removing, setRemoving] = useState<Requirement | null>(null)
  const careerId = Number(career || (catalog.data?.carreras.length === 1 ? catalog.data.carreras[0].id : 0))
  const procedureId = Number(procedure)
  const own = catalog.data?.requisitos.filter(r => r.carrera_id === careerId && r.tramite_proceso_id === procedureId) ?? []
  const general = catalog.data?.generales.filter(r => r.tramite_proceso_id === procedureId) ?? []
  const required = [...own.filter(r => r.activa), ...general].filter(r => r.obligatorio).length
  function clearSelection() { setEditing(null); setRemoving(null); action.setError(null); action.setMessage('') }
  async function remove() {
    if (!removing) return
    const result = await action.run(() => api(`/coordinator/requirements/${removing.id}`, { method: 'DELETE' }), 'Requisito eliminado.')
    if (result) { setRemoving(null); await catalog.reload() }
  }
  return <>
    <PageHeader kicker="COORDINACIÓN ACADÉMICA" title="Requisitos por trámite" subtitle="Define los documentos e indicaciones que recibirán los estudiantes de tus carreras." />
    <Alert error={catalog.error ?? action.error} message={action.message} onRetry={catalog.error ? catalog.reload : undefined} />
    {catalog.loading && !catalog.data ? <Loading /> : !catalog.data ? null : !catalog.data.carreras.length ? <Panel><Empty>No tienes carreras asignadas. Solicita la asignación al administrador para configurar sus requisitos.</Empty></Panel> : <>
      <Panel title="Selecciona la carrera y el trámite">
        <div className="form-grid management-form">
          <Field label="Carrera a tu cargo"><select disabled={action.busy || !!editing} value={careerId || ''} onChange={e => { setCareer(e.target.value); clearSelection() }}><option value="">Selecciona una carrera</option>{catalog.data.carreras.map(c => <option key={c.id} value={c.id}>{c.nombre}{c.activa ? '' : ' (inactiva)'}</option>)}</select></Field>
          <Field label="Trámite"><select disabled={action.busy || !!editing} value={procedure} onChange={e => { setProcedure(e.target.value); clearSelection() }}><option value="">Selecciona un trámite</option>{catalog.data.tramites.map(p => <option key={p.id} value={p.id}>{procedureLabel(p)}</option>)}</select></Field>
        </div>
      </Panel>
      {!careerId || !procedureId ? <Panel><Empty>Selecciona una carrera y un trámite para consultar y configurar sus requisitos.</Empty></Panel> : <>
        <div className="management-banner"><FileCheck2 size={28} /><div><strong>{required} requisito{required === 1 ? '' : 's'} obligatorio{required === 1 ? '' : 's'} activo{required === 1 ? '' : 's'}</strong><p>{required ? 'El estudiante verá estos documentos antes de crear su solicitud.' : 'Configura al menos un documento obligatorio para que el estudiante pueda crear este trámite.'}</p></div></div>
        {editing && <RequirementForm key={`${careerId}-${procedureId}-${editing === 'new' ? 'new' : editing.id}`} careerId={careerId} procedureId={procedureId} entry={editing === 'new' ? undefined : editing} onCancel={() => setEditing(null)} onSaved={() => { setEditing(null); action.setError(null); action.setMessage('Requisito guardado. Se aplicará a nuevas solicitudes.'); catalog.reload() }} />}
        {removing && <div className="confirm-banner" role="alert"><p>¿Eliminar <strong>{removing.nombre}</strong>? Si ya forma parte de un expediente, utiliza «Editar» para desactivarlo.</p><button className="btn btn-outline" disabled={action.busy || !!editing} onClick={() => setRemoving(null)}>Cancelar</button><button className="btn btn-danger" disabled={action.busy || !!editing} onClick={remove}>Eliminar</button></div>}
        <Panel title="Requisitos de esta carrera" actions={!editing && <button className="btn btn-primary" onClick={() => { setEditing('new'); setRemoving(null); action.setError(null); action.setMessage('') }}><Plus size={15} /> Nuevo requisito</button>}>
          {!own.length ? <Empty>Aún no hay requisitos específicos. Puedes añadir documentos para este trámite.</Empty> : <div className="table-wrap"><table className="data-table"><thead><tr><th>Documento e indicaciones</th><th>Tipo</th><th>Estado</th><th>Acciones</th></tr></thead><tbody>{own.map(r => <tr key={r.id}><td><strong>{r.nombre}</strong><small className="cell-description">{r.descripcion || 'Sin indicaciones adicionales.'}</small></td><td>{r.obligatorio ? 'Obligatorio' : 'Complementario'}</td><td><span className={`badge ${r.activa ? 'badge-ok' : 'badge-off'}`}>{r.activa ? 'Activo' : 'Inactivo'}</span></td><td><div className="row-actions"><button className="btn btn-ghost" disabled={action.busy || !!editing} onClick={() => { setEditing(r); setRemoving(null); action.setError(null); action.setMessage('') }}><Pencil size={14} /> Editar</button><button className="btn btn-ghost danger-text" disabled={action.busy || !!editing} aria-label={`Eliminar ${r.nombre}`} onClick={() => { setRemoving(r); setEditing(null) }}><Trash2 size={14} /></button></div></td></tr>)}</tbody></table></div>}
        </Panel>
        <Panel title="Requisitos institucionales incluidos">
          <p className="muted">Aplican a todas las carreras y los administra el administrador. Se incluyen automáticamente; evita volver a registrarlos.</p>
          {!general.length ? <Empty>Este trámite no tiene requisitos institucionales activos.</Empty> : <ul className="requirement-preview-list">{general.map(r => <li key={r.id}><div><strong>{r.nombre}</strong>{r.descripcion && <p>{r.descripcion}</p>}</div><span className="badge">{r.obligatorio ? 'Obligatorio' : 'Complementario'}</span></li>)}</ul>}
        </Panel>
      </>}
    </>}
  </>
}
