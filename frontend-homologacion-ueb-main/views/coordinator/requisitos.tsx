'use client'

import { FormEvent, useState } from 'react'
import { FileCheck2, Pencil, Plus, Trash2, ClipboardCheck } from 'lucide-react'
import { QuickRequirementAssignment } from '@/views/admin/asignar-requisitos'
import { ProcessSelector } from '@/components/app/process-selector'
import { api } from '@/lib/api'
import { Procedure, procedureLabel, Requirement } from '@/lib/requirements'
import { Alert, Empty, Field, fieldError, Loading, PageHeader, Panel, useAction, useAsync } from '@/components/app/ui'

type Career = { id: number; nombre: string; activa: boolean; facultad_id?:number; facultad?:string }
type Catalog = { facultades?:{id:number;nombre:string;activa:boolean}[]; carreras: Career[]; tramites: Procedure[]; requisitos: Requirement[] }

function RequirementForm({ careerId, procedureId, entry, careers, admin, onSaved, onCancel }: { careerId: number; procedureId: number; entry?: Requirement; careers: Career[]; admin: boolean; onSaved: () => void; onCancel: () => void }) {
  const action = useAction()
  const [selected, setSelected] = useState([careerId])
  const [form, setForm] = useState({ nombre: entry?.nombre ?? '', descripcion: entry?.descripcion ?? '', activa: entry?.activa ?? true, obligatorio: entry?.obligatorio ?? true })
  async function submit(event: FormEvent) {
    event.preventDefault()
    if (!selected.length) { action.setError(new Error('Selecciona al menos una carrera.')); return }
    const endpoint = admin ? '/admin/catalogs/requisitos' : '/coordinator/requirements'
    const body = { ...form, activa: entry ? form.activa : true, nombre: form.nombre.trim(), tramite_proceso_id: procedureId, ...(admin && !entry ? { carrera_ids: selected } : { carrera_id: careerId }) }
    const result = await action.run(() => api(`${endpoint}${entry ? `/${entry.id}` : ''}`, { method: entry ? 'PUT' : 'POST', body }))
    if (result) onSaved()
  }
  return <Panel title={entry ? 'Editar requisito asignado' : 'Añadir requisito específico'}>
    <Alert error={action.error} />
    <form className="form-grid management-form" onSubmit={submit}>
      <p className="required-fields-note"><span className="required-mark">*</span> Campos obligatorios.</p>
      {admin && !entry && <fieldset className="catalog-checks"><legend>Asignar a carreras <span className="required-mark">*</span></legend>{careers.filter(c => c.activa).map(c => <label key={c.id}><input type="checkbox" checked={selected.includes(c.id)} onChange={e => setSelected(e.target.checked ? [...selected, c.id] : selected.filter(id => id !== c.id))} />{c.nombre}</label>)}{fieldError(action.error, 'carrera_ids') && <small className="field-error">{fieldError(action.error, 'carrera_ids')}</small>}</fieldset>}
      <Field label="Nombre del requisito" error={fieldError(action.error, 'nombre')}><input autoFocus required maxLength={150} value={form.nombre} onChange={e => setForm({ ...form, nombre: e.target.value })} /></Field>
      {entry && <Field label="Disponibilidad"><select value={form.activa ? '1' : '0'} onChange={e => setForm({ ...form, activa: e.target.value === '1' })}><option value="1">Activo para nuevas solicitudes</option><option value="0">Inactivo</option></select></Field>}
      <Field label="Indicaciones" error={fieldError(action.error, 'descripcion')}><textarea maxLength={2000} rows={3} value={form.descripcion} onChange={e => setForm({ ...form, descripcion: e.target.value })} /></Field>
      <Field label="Obligatoriedad"><select value={form.obligatorio ? '1' : '0'} onChange={e => setForm({ ...form, obligatorio: e.target.value === '1' })}><option value="1">Obligatorio</option><option value="0">Complementario</option></select></Field>
      <p className="muted">Los cambios se aplican a nuevas solicitudes. Los expedientes anteriores conservan sus requisitos y revisiones.</p>
      <div className="form-actions"><button type="button" className="btn btn-outline" disabled={action.busy} onClick={onCancel}>Cancelar</button><button className="btn btn-primary" disabled={action.busy}>{action.busy ? 'Guardando...' : 'Guardar requisito'}</button></div>
    </form>
  </Panel>
}

function AssignedRequirementsSummary({catalog}:{catalog:Catalog}) {
  const groups = catalog.carreras.flatMap(career=>catalog.tramites.flatMap(procedure=>{
    const requirements=catalog.requisitos.filter(r=>r.carrera_id===career.id&&r.tramite_proceso_id===procedure.id)
    return requirements.length?[{career,procedure,requirements}]:[]
  }))
  return <details className="assigned-requirements-summary"><summary><ClipboardCheck size={19}/><span>Consultar requisitos asignados</span><small>{groups.length} lista(s) por carrera y trámite</small></summary><div className="assigned-requirements-body"><p>Consulta las listas actuales. El coordinador administra los ajustes de su carrera.</p>{groups.length?groups.map(group=><details className="assigned-requirements-group" key={`${group.career.id}-${group.procedure.id}`}><summary><span><strong>{group.career.nombre}</strong><small>{procedureLabel(group.procedure)}</small></span><span>{group.requirements.filter(r=>r.activa).length} activos</span></summary><ul>{group.requirements.map(r=><li key={r.id}><div><strong>{r.nombre}</strong><small>{r.obligatorio?'Obligatorio':'Complementario'} · {r.origen_configuracion==='coordinador'?'Ajustado por el coordinador':'Asignado por el administrador'}</small></div><span className={`badge ${r.activa?'badge-ok':'badge-off'}`}>{r.activa?'Activo':'Inactivo'}</span></li>)}</ul></details>):<Empty>Todavía no hay requisitos asignados. Selecciona las carreras y los requisitos en la asignación rápida.</Empty>}</div></details>
}

function RequirementsPanel({ admin = false }: { admin?: boolean }) {
  const catalog = useAsync(() => api<{ data: Catalog }>(admin ? '/admin/catalogs' : '/coordinator/requirements').then(r => r.data), [admin])
  const action = useAction()
  const [career, setCareer] = useState('')
  const [procedure, setProcedure] = useState('')
  const [editing, setEditing] = useState<Requirement | 'new' | null>(null)
  const [removing, setRemoving] = useState<Requirement | null>(null)
  const careerId = Number(career || (catalog.data?.carreras.length === 1 ? catalog.data.carreras[0].id : 0))
  const procedureId = Number(procedure)
  const entries = catalog.data?.requisitos.filter(r => r.carrera_id === careerId && r.tramite_proceso_id === procedureId) ?? []
  const required = entries.filter(r => r.activa && r.obligatorio).length
  const selectedProcess = catalog.data?.tramites.find(p => p.id === procedureId)
  const title = selectedProcess?.tipo_tramite.toLowerCase().includes('reconocimiento') ? 'Requisitos de reconocimiento' : selectedProcess ? 'Requisitos de homologación' : 'Requisitos de homologación y reconocimiento'
  function clearSelection() { setEditing(null); setRemoving(null); action.setError(null); action.setMessage('') }
  async function remove() {
    if (!removing) return
    const endpoint = admin ? '/admin/catalogs/requisitos' : '/coordinator/requirements'
    const result = await action.run(() => api(`${endpoint}/${removing.id}`, { method: 'DELETE' }), 'Requisito eliminado.')
    if (result) { setRemoving(null); await catalog.reload() }
  }
  return <>
    <PageHeader icon={ClipboardCheck} kicker={admin ? 'ADMINISTRACIÓN' : 'COORDINACIÓN'} title={title} subtitle={admin ? 'Crea y asigna requisitos por carrera y trámite. El coordinador puede ajustar los requisitos de sus carreras.' : 'Consulta los requisitos asignados por el administrador y ajusta los de tu carrera.'} />
    <Alert error={catalog.error ?? action.error} message={action.message} onRetry={catalog.error ? catalog.reload : undefined} />
    {catalog.loading && !catalog.data ? <Loading /> : !catalog.data ? null : !catalog.data.carreras.length ? <Panel><Empty>{admin ? 'Crea primero una carrera para asignarle requisitos.' : 'No tienes carreras asignadas. Solicita la asignación al administrador.'}</Empty></Panel> : <>
      {admin&&<QuickRequirementAssignment catalog={catalog.data} onAssigned={()=>catalog.reload()}/>}
      {admin ? <AssignedRequirementsSummary catalog={catalog.data}/> : <Panel className="requirements-workspace" title="Consultar y ajustar requisitos asignados">
        <p className="requirements-purpose">{admin ? 'Selecciona una carrera y un trámite para consultar su lista actual, editar sus indicaciones o añadir un requisito específico. Para asignar varios requisitos precargados a varias carreras, usa «Asignación rápida» arriba.' : 'Selecciona tu carrera y el trámite para consultar los requisitos que asignó el administrador. Puedes ajustar las indicaciones, añadir requisitos o desactivar los que no correspondan.'}</p>
        <div className="form-grid management-form">
        <Field required label="Carrera"><select disabled={!!editing} value={careerId || ''} onChange={e => { setCareer(e.target.value); clearSelection() }}><option value="">Selecciona una carrera</option>{catalog.data.carreras.map(c => <option key={c.id} value={c.id}>{c.nombre}{c.activa ? '' : ' (inactiva)'}</option>)}</select></Field>
        <ProcessSelector procedures={catalog.data.tramites} value={procedure} disabled={!!editing} onChange={value=>{setProcedure(value);clearSelection()}}/>
      </div>
      {!careerId || !procedureId ? <Empty>Elige la carrera y el trámite que deseas consultar. Aquí aparecerán sus requisitos asignados.</Empty> : <>
        <div className="management-banner"><FileCheck2 size={28} /><div><strong>{required} requisitos obligatorios activos</strong><p>Estos requisitos se incluirán en las nuevas solicitudes de {catalog.data.carreras.find(c => c.id === careerId)?.nombre} para {selectedProcess ? procedureLabel(selectedProcess) : 'este trámite'}. Los expedientes existentes conservan su lista.</p></div></div>
        {editing && <RequirementForm key={`${careerId}-${procedureId}-${editing === 'new' ? 'new' : editing.id}`} admin={admin} careers={catalog.data.carreras} careerId={careerId} procedureId={procedureId} entry={editing === 'new' ? undefined : editing} onCancel={() => setEditing(null)} onSaved={() => { setEditing(null); action.setMessage('Requisito guardado correctamente.'); catalog.reload() }} />}
        {removing && <div className="confirm-banner" role="alert"><p>¿Eliminar <strong>{removing.nombre}</strong>? Si tiene historial, desactívalo desde Editar.</p><button className="btn btn-outline" onClick={() => setRemoving(null)}>Cancelar</button><button className="btn btn-danger" disabled={action.busy} onClick={remove}>Eliminar</button></div>}
        <Panel className="requirements-workspace-list" title="Lista de requisitos para este trámite" actions={!editing && <button className="btn btn-primary" onClick={() => { clearSelection(); setEditing('new') }}><Plus size={15} /> Añadir requisito específico</button>}>
          {!entries.length ? <Empty>{admin ? 'Esta carrera aún no tiene requisitos para este trámite. Usa la asignación rápida de arriba o añade un requisito específico.' : 'Esta carrera aún no tiene requisitos para este trámite. Solicita la asignación al administrador o añade los requisitos que correspondan.'}</Empty> : <div className="table-wrap"><table className="data-table"><thead><tr><th>Requisito e indicaciones</th><th>Configurado por</th><th>Tipo</th><th>Estado</th><th>Acciones</th></tr></thead><tbody>{entries.map(r => <tr key={r.id}><td><strong>{r.nombre}</strong><small className="cell-description">{r.descripcion}</small></td><td>{r.origen_configuracion === 'coordinador' ? 'Coordinador' : 'Administrador'}</td><td>{r.obligatorio ? 'Obligatorio' : 'Complementario'}</td><td><span className={`badge ${r.activa ? 'badge-ok' : 'badge-off'}`}>{r.activa ? 'Activo' : 'Inactivo'}</span></td><td><div className="row-actions"><button className="btn btn-ghost" onClick={() => { clearSelection(); setEditing(r) }}><Pencil size={14} /> Editar</button><button className="btn btn-ghost danger-text" aria-label={`Eliminar ${r.nombre}`} onClick={() => { clearSelection(); setRemoving(r) }}><Trash2 size={14} /></button></div></td></tr>)}</tbody></table></div>}
        </Panel>
      </>}
      </Panel>}
    </>}
  </>
}
export function CoordinatorRequirements() { return <RequirementsPanel /> }
export function AdminRequirements() { return <RequirementsPanel admin /> }
