'use client'
import { FormEvent, useState } from 'react'
import { BookOpen, Building2, GraduationCap, Pencil, Plus, Search, Trash2 } from 'lucide-react'
import { api } from '@/lib/api'
import { Alert, Empty, Field, fieldError, Loading, PageHeader, Panel, useAction, useAsync } from '@/components/app/ui'
import { href } from '@/lib/router'
type Entry = { id: number; nombre: string; activa: boolean; facultad_id?: number; facultad?: string; modalidades?: Entry[]; carrera_id?: number; tramite_proceso_id?: number; descripcion?: string; obligatorio?: boolean }
type Kind = 'facultades' | 'carreras' | 'modalidades'
type Catalogs = Record<Kind, Entry[]> & { tramites: {id: number; nombre: string}[] }
const sections = [{id: 'facultades', label: 'Facultades', icon: Building2}, {id: 'carreras', label: 'Carreras', icon: GraduationCap}, {id: 'modalidades', label: 'Modalidades', icon: BookOpen}] as const
const singular: Record<Kind, string> = { facultades: 'facultad', carreras: 'carrera', modalidades: 'modalidad' }
function CatalogForm({ kind, entry, data, onDone, onCancel }: {kind: Kind; entry?: Entry; data: Catalogs; onDone: () => void; onCancel: () => void}) {
  const action = useAction()
  const [name, setName] = useState(entry?.nombre ?? '')
  const [active, setActive] = useState(entry?.activa ?? true)
  const [faculty, setFaculty] = useState(String(entry?.facultad_id ?? ''))
  const [modalities, setModalities] = useState<number[]>(entry?.modalidades?.map(m => m.id) ?? [])
  const faculties = data.facultades.filter(x => x.activa || x.id === entry?.facultad_id)
  const availableModalities = data.modalidades.filter(x => x.activa || entry?.modalidades?.some(m => m.id === x.id))
  const missingPrerequisites = kind === 'carreras' && (!faculties.length || !availableModalities.length)
  async function submit(e: FormEvent) {
    e.preventDefault()
    if (kind === 'carreras' && !faculty) { action.setError(new Error('Selecciona primero la facultad a la que pertenecer? la carrera.')); return }
    if (kind === 'carreras' && !modalities.length) { action.setError(new Error('Selecciona al menos una modalidad para la carrera.')); return }
    const body = { nombre: name.trim(), activa: entry ? active : true, ...(kind === 'carreras' ? { facultad_id: Number(faculty), modalidad_ids: modalities } : {}) }
    const result = await action.run(() => api(`/admin/catalogs/${kind}${entry ? `/${entry.id}` : ''}`, { method: entry ? 'PUT' : 'POST', body }))
    if (result) onDone()
  }
  return <Panel title={entry ? `Editar ${entry.nombre}` : `Nueva ${singular[kind]}`} actions={<button className="btn btn-ghost" disabled={action.busy} onClick={onCancel}>Cerrar</button>}>
    <Alert error={action.error}/><form className="form-grid management-form" onSubmit={submit}>
        <p className="required-fields-note"><span className="required-mark" aria-hidden="true">*</span> Campos obligatorios.</p>
      {kind === 'carreras' && <Field label="Facultad" error={fieldError(action.error, 'facultad_id')}><select required value={faculty} onChange={e=>setFaculty(e.target.value)}><option value="">Selecciona una facultad</option>{faculties.map(x=><option key={x.id} value={x.id}>{x.nombre}{x.activa ? '' : ' (inactiva · asociación existente)'}</option>)}</select></Field>}
      <Field label="Nombre" error={fieldError(action.error, 'nombre')}><input autoFocus={kind !== 'carreras'} disabled={kind === 'carreras' && !faculty} required maxLength={150} value={name} onChange={e=>setName(e.target.value)}/></Field>
      {entry && <Field label="Disponibilidad"><select value={active ? '1':'0'} onChange={e=>setActive(e.target.value==='1')}><option value="1">Activo</option><option value="0">Inactivo</option></select></Field>}
      {kind === 'carreras' && <>
        <div className="admin-form-heading"><span>Oferta académica de la carrera</span><small>Asocia una facultad y al menos una modalidad. Selecciona esta carrera al registrar o editar un coordinador en Usuarios.</small></div>
        {missingPrerequisites && <div className="admin-form-note"><div><strong>Completa primero los catálogos</strong><p>{!faculties.length ? 'Necesitas registrar una facultad activa. ' : ''}{!availableModalities.length ? 'Necesitas registrar una modalidad activa.' : ''}</p><button type="button" className="btn btn-outline" onClick={onCancel}>Volver a los catálogos</button></div></div>}
        <fieldset className="catalog-checks admin-modality-options"><legend>Modalidades de esta carrera <span className="required-mark" aria-label="obligatorio">*</span></legend><small>Selecciona una o varias modalidades.</small>{availableModalities.map(x=><label key={x.id} className={modalities.includes(x.id) ? 'selected' : ''}><input type="checkbox" checked={modalities.includes(x.id)} onChange={e=>setModalities(e.target.checked ? [...modalities,x.id] : modalities.filter(id=>id!==x.id))}/>{x.nombre}{x.activa ? '' : ' (inactiva)'}</label>)}{fieldError(action.error, 'modalidad_ids') && <span className="admin-unavailable-note">{fieldError(action.error, 'modalidad_ids')}</span>}</fieldset>
      </>}
      <div className="form-actions"><button type="button" className="btn btn-outline" disabled={action.busy} onClick={onCancel}>Cancelar</button><button className="btn btn-primary" disabled={action.busy || missingPrerequisites}>{action.busy ? 'Guardando…' : `Guardar ${singular[kind]}`}</button></div>
    </form></Panel>
}
export function AdminCatalogos() {
  const catalogs = useAsync(()=>api<{data: Catalogs}>('/admin/catalogs').then(r=>r.data),[])
  const action=useAction()
  const [kind,setKind]=useState<Kind>('facultades')
  const [search,setSearch]=useState('')
  const [editing,setEditing]=useState<Entry | 'new' | null>(null)
  const [removing,setRemoving]=useState<Entry | null>(null)
  if (!catalogs.data) return catalogs.loading ? <Loading/> : <Alert error={catalogs.error} onRetry={catalogs.reload}/>
  const data=catalogs.data
  const entries=data[kind].filter(x=>x.nombre.toLocaleLowerCase().includes(search.toLocaleLowerCase()))
  async function remove() { if(!removing)return; const result=await action.run(()=>api(`/admin/catalogs/${kind}/${removing.id}`,{method:'DELETE'}),'Registro eliminado.'); if(result){setRemoving(null); catalogs.reload()} }
  return <><PageHeader icon={Building2} kicker="ADMINISTRACIÓN ACADÉMICA" title="Catálogos institucionales" subtitle="Organiza facultades, modalidades y carreras; después asigna cada carrera a su coordinador." actions={<button className="btn btn-primary" onClick={()=>{setEditing('new');setRemoving(null)}}><Plus size={16}/>Nueva {singular[kind]}</button>}/>
    <div className="admin-setup-flow" aria-label="Orden de configuración"><div><span>1</span><p><strong>Facultades y modalidades</strong><small>Registra la estructura institucional.</small></p></div><div><span>2</span><p><strong>Carreras</strong><small>Asocia facultad y modalidades.</small></p></div><a href={href('/usuarios')}><span>3</span><p><strong>Coordinadores</strong><small>Selecciona sus carreras al registrarlo.</small></p></a></div>
    <div className="catalog-tabs" aria-label="Catálogos">{sections.map(({id,label,icon: Icon})=><button key={id} aria-pressed={kind===id} className={kind===id ? 'selected':''} onClick={()=>{setKind(id);setEditing(null);setRemoving(null);setSearch('');action.setError(null);action.setMessage('')}}><Icon size={19}/><span>{label}</span><b>{data[id].length}</b></button>)}</div>
    <Alert error={catalogs.error ?? action.error} message={action.message}/>
    {editing && <CatalogForm key={`${kind}-${editing==='new'?'new':editing.id}`} kind={kind} entry={editing==='new'?undefined:editing} data={data} onDone={()=>{setEditing(null);catalogs.reload();action.setMessage('Registro guardado correctamente.')}} onCancel={()=>setEditing(null)}/>}
    {removing && <div className="confirm-banner" role="alert"><div><strong>¿Eliminar {removing.nombre}?</strong><p>Si tiene registros asociados, deberás desactivarlo para conservar el historial.</p></div><button className="btn btn-outline" onClick={()=>setRemoving(null)}>Cancelar</button><button className="btn btn-danger" disabled={action.busy} onClick={remove}>Eliminar</button></div>}
    <Panel title={sections.find(s=>s.id===kind)?.label} actions={<label className="search-box"><Search size={16}/><input aria-label="Buscar catálogo" placeholder="Buscar por nombre…" value={search} onChange={e=>setSearch(e.target.value)}/></label>}>
    {!entries.length ? <Empty>No hay registros para mostrar. Crea uno con el botón de arriba.</Empty> : <div className="table-wrap"><table className="data-table"><thead><tr><th>Nombre</th><th>{kind==='carreras'?'Facultad / modalidad':'Disponibilidad'}</th><th>Estado</th><th>Acciones</th></tr></thead><tbody>{entries.map(x=><tr key={x.id}><td><strong>{x.nombre}</strong></td><td>{kind==='carreras'?<>{x.facultad ?? 'Sin facultad'}<small className="cell-description">{x.modalidades?.map(m=>m.nombre).join(' · ')}</small></>:'Catálogo institucional'}</td><td><span className={`status ${x.activa?'approved':''}`}>{x.activa?'Activo':'Inactivo'}</span></td><td><div className="row-actions"><button className="btn btn-ghost" onClick={()=>{setEditing(x);setRemoving(null)}} aria-label={`Editar ${x.nombre}`}><Pencil size={15}/>Editar</button><button className="btn btn-ghost danger-text" onClick={()=>{setRemoving(x);setEditing(null)}} aria-label={`Eliminar ${x.nombre}`}><Trash2 size={15}/></button></div></td></tr>)}</tbody></table></div>}
    </Panel></>
}
