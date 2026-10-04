'use client'
import { FormEvent, useState } from 'react'
import { BookOpen, Building2, GraduationCap, ListChecks, Pencil, Plus, Search, Trash2 } from 'lucide-react'
import { api } from '@/lib/api'
import { Alert, Empty, Field, Loading, PageHeader, Panel, useAction, useAsync } from '@/components/app/ui'
type Entry = { id: number; nombre: string; activa: boolean; facultad_id?: number; facultad?: string; modalidades?: Entry[]; carrera_id?: number; tramite_proceso_id?: number; descripcion?: string; obligatorio?: boolean }
type Kind = 'facultades' | 'carreras' | 'modalidades' | 'requisitos'
type Catalogs = Record<Kind, Entry[]> & { tramites: {id: number; nombre: string}[] }
const sections = [{id: 'facultades', label: 'Facultades', icon: Building2}, {id: 'carreras', label: 'Carreras', icon: GraduationCap}, {id: 'modalidades', label: 'Modalidades', icon: BookOpen}, {id: 'requisitos', label: 'Requisitos', icon: ListChecks}] as const
function CatalogForm({ kind, entry, data, onDone, onCancel }: {kind: Kind; entry?: Entry; data: Catalogs; onDone: () => void; onCancel: () => void}) {
  const action = useAction()
  const [name, setName] = useState(entry?.nombre ?? '')
  const [active, setActive] = useState(entry?.activa ?? true)
  const [faculty, setFaculty] = useState(String(entry?.facultad_id ?? ''))
  const [modalities, setModalities] = useState<number[]>(entry?.modalidades?.map(m => m.id) ?? [])
  const [career, setCareer] = useState(String(entry?.carrera_id ?? ''))
  const [process, setProcess] = useState(String(entry?.tramite_proceso_id ?? ''))
  const [description, setDescription] = useState(entry?.descripcion ?? '')
  const [required, setRequired] = useState(entry?.obligatorio ?? true)
  async function submit(e: FormEvent) {
    e.preventDefault()
    const body = { nombre: name.trim(), activa: active, ...(kind === 'carreras' ? { facultad_id: Number(faculty), modalidad_ids: modalities } : {}), ...(kind === 'requisitos' ? { carrera_id: career ? Number(career) : null, tramite_proceso_id: Number(process), descripcion: description, obligatorio: required } : {}) }
    const result = await action.run(() => api(`/admin/catalogs/${kind}${entry ? `/${entry.id}` : ''}`, { method: entry ? 'PUT' : 'POST', body }))
    if (result) onDone()
  }
  return <Panel title={entry ? `Editar ${entry.nombre}` : 'Nuevo registro'} actions={<button className="btn btn-ghost" disabled={action.busy} onClick={onCancel}>Cerrar</button>}>
    <Alert error={action.error}/><form className="form-grid management-form" onSubmit={submit}>
      <Field label="Nombre"><input autoFocus required maxLength={150} value={name} onChange={e=>setName(e.target.value)}/></Field>
      <Field label="Disponibilidad"><select value={active ? '1':'0'} onChange={e=>setActive(e.target.value==='1')}><option value="1">Activo</option><option value="0">Inactivo</option></select></Field>
      {kind === 'carreras' && <><Field label="Facultad"><select required value={faculty} onChange={e=>setFaculty(e.target.value)}><option value="">Selecciona una facultad</option>{data.facultades.filter(x=>x.activa).map(x=><option key={x.id} value={x.id}>{x.nombre}</option>)}</select></Field>
        <fieldset className="catalog-checks"><legend>Modalidades de esta carrera</legend>{data.modalidades.filter(x=>x.activa).map(x=><label key={x.id}><input type="checkbox" checked={modalities.includes(x.id)} onChange={e=>setModalities(e.target.checked ? [...modalities,x.id] : modalities.filter(id=>id!==x.id))}/>{x.nombre}</label>)}</fieldset></>}
      {kind === 'requisitos' && <><Field label="Trámite y proceso"><select required value={process} onChange={e=>setProcess(e.target.value)}><option value="">Selecciona un trámite</option>{data.tramites.map(x=><option key={x.id} value={x.id}>{x.nombre}</option>)}</select></Field><Field label="Aplicar a"><select value={career} onChange={e=>setCareer(e.target.value)}><option value="">Todas las carreras</option>{data.carreras.map(x=><option key={x.id} value={x.id}>{x.nombre}</option>)}</select></Field><Field label="Indicaciones para la entrega"><textarea maxLength={2000} value={description} onChange={e=>setDescription(e.target.value)}/></Field><label className="check-inline"><input type="checkbox" checked={required} onChange={e=>setRequired(e.target.checked)}/>Requisito obligatorio para completar la documentación</label><p className="muted">Los cambios se aplican a nuevas solicitudes. Los expedientes existentes conservan sus requisitos.</p></>}
      <div className="form-actions"><button type="button" className="btn btn-outline" disabled={action.busy} onClick={onCancel}>Cancelar</button><button className="btn btn-primary" disabled={action.busy}>{action.busy ? 'Guardando…' : 'Guardar registro'}</button></div>
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
  return <><PageHeader kicker="ADMINISTRACIÓN ACADÉMICA" title="Catálogos institucionales" subtitle="Organiza la oferta académica y los documentos que se entregan de forma presencial." actions={<button className="btn btn-primary" onClick={()=>{setEditing('new');setRemoving(null)}}><Plus size={16}/>Nuevo registro</button>}/>
    <div className="catalog-tabs" role="tablist" aria-label="Catálogos">{sections.map(({id,label,icon: Icon})=><button key={id} role="tab" aria-selected={kind===id} className={kind===id ? 'selected':''} onClick={()=>{setKind(id);setEditing(null);setRemoving(null);setSearch('');action.setError(null)}}><Icon size={19}/><span>{label}</span><b>{data[id].length}</b></button>)}</div>
    <Alert error={catalogs.error ?? action.error} message={action.message}/>
    {editing && <CatalogForm key={`${kind}-${editing==='new'?'new':editing.id}`} kind={kind} entry={editing==='new'?undefined:editing} data={data} onDone={()=>{setEditing(null);catalogs.reload();action.setMessage('Registro guardado correctamente.')}} onCancel={()=>setEditing(null)}/>}
    {removing && <div className="confirm-banner" role="alert"><div><strong>¿Eliminar {removing.nombre}?</strong><p>Si tiene registros asociados, deberás desactivarlo para conservar el historial.</p></div><button className="btn btn-outline" onClick={()=>setRemoving(null)}>Cancelar</button><button className="btn btn-danger" disabled={action.busy} onClick={remove}>Eliminar</button></div>}
    <Panel title={sections.find(s=>s.id===kind)?.label} actions={<label className="search-box"><Search size={16}/><input aria-label="Buscar catálogo" placeholder="Buscar por nombre…" value={search} onChange={e=>setSearch(e.target.value)}/></label>}>
    {!entries.length ? <Empty>No hay registros para mostrar. Crea uno con el botón «Nuevo registro».</Empty> : <div className="table-wrap"><table className="data-table"><thead><tr><th>Nombre</th><th>{kind==='carreras'?'Facultad / modalidad':kind==='requisitos'?'Alcance':'Disponibilidad'}</th><th>Estado</th><th>Acciones</th></tr></thead><tbody>{entries.map(x=><tr key={x.id}><td><strong>{x.nombre}</strong>{kind==='requisitos'&&<small className="cell-description">{x.descripcion}</small>}</td><td>{kind==='carreras'?<>{x.facultad ?? 'Sin facultad'}<small className="cell-description">{x.modalidades?.map(m=>m.nombre).join(' · ')}</small></>:kind==='requisitos'?<>{x.carrera_id ? data.carreras.find(c=>c.id===x.carrera_id)?.nombre:'Todas las carreras'}<small className="cell-description">{x.obligatorio?'Obligatorio':'Complementario'}</small></>: 'Catálogo institucional'}</td><td><span className={`status ${x.activa?'approved':''}`}>{x.activa?'Activo':'Inactivo'}</span></td><td><div className="row-actions"><button className="btn btn-ghost" onClick={()=>{setEditing(x);setRemoving(null)}} aria-label={`Editar ${x.nombre}`}><Pencil size={15}/>Editar</button><button className="btn btn-ghost danger-text" onClick={()=>{setRemoving(x);setEditing(null)}} aria-label={`Eliminar ${x.nombre}`}><Trash2 size={15}/></button></div></td></tr>)}</tbody></table></div>}
    </Panel></>
}
