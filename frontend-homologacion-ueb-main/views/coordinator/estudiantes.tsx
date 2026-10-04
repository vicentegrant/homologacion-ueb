'use client'
import { FormEvent, useState } from 'react'
import { GraduationCap, Pencil, Plus, Search, UserCheck, UserRoundX } from 'lucide-react'
import { api, ApiUser, Paginated } from '@/lib/api'
import { academicCycles } from '@/lib/format'
import { Alert, Empty, Field, fieldError, Loading, PageHeader, Panel, Pager, useAction, useAsync, useDebouncedValue } from '@/components/app/ui'
type Career={id:number;nombre:string;facultad?:string|null;activa:boolean;modalidades:{id:number;nombre:string;activa:boolean}[]}
type OriginCareer={id:number;nombre:string;facultad?:string|null}
type Student={creador_id:number|null;id:number;nombres_completos:string;tipo_identificacion:string;cedula:string;email:string;numero_celular:string;cuenta_activa:boolean;carreras?:{id:number;nombre:string;modalidad_id?:number}[];antecedentes_academicos?:{id:number;procedencia?:'interna'|'externa'|null;carrera_origen_id?:number|null;carrera_origen:string;universidad_origen:string;tipo_institucion:string;periodo_cursado:string}[]}
function StudentForm({student,careers,origins,institution,onDone,onCancel}:{student?:Student;careers:Career[];origins:OriginCareer[];institution:string;onDone:(message?:string)=>void;onCancel:()=>void}) {
 const action = useAction()
 const background = student?.antecedentes_academicos?.slice().sort((a,b)=>b.id-a.id)[0]
 const [form,setForm] = useState({
  nombres_completos:student?.nombres_completos??'', tipo_identificacion:student?.tipo_identificacion??'cedula', cedula:student?.cedula??'', email:student?.email??'', numero_celular:student?.numero_celular??'',
  procedencia:background?.procedencia??'', carrera_id:String(student?.carreras?.[0]?.id??''), modalidad_id:String(student?.carreras?.[0]?.modalidad_id??''),
  carrera_origen_id:String(background?.carrera_origen_id??''), carrera_origen:background?.carrera_origen??'', universidad_origen:background?.universidad_origen??'', tipo_institucion:background?.tipo_institucion??'publica',
  periodo_cursado:background && academicCycles.includes(background.periodo_cursado) ? background.periodo_cursado : '',
 })
 const set = (key:keyof typeof form,value:string)=>setForm(old=>({...old,[key]:value}))
 const internal = form.procedencia === 'interna'
 const destination = careers.find(c=>c.id===Number(form.carrera_id))
 const availableDestinations = careers.filter(c=>c.activa && c.modalidades.some(m=>m.activa))
 const internalOrigins = origins.filter(c=>c.id!==Number(form.carrera_id))
 async function submit(e:FormEvent) {
  e.preventDefault()
  // El origen externo se registra como antecedente, sin crear una carrera en el catálogo UEB.
  const body = {nombres_completos:form.nombres_completos, tipo_identificacion:form.tipo_identificacion, cedula:form.cedula.trim().toUpperCase(), email:form.email, numero_celular:form.numero_celular,
   procedencia:form.procedencia, carrera_id:Number(form.carrera_id), modalidad_id:Number(form.modalidad_id), periodo_cursado:form.periodo_cursado,
   ...(internal ? {carrera_origen_id:Number(form.carrera_origen_id)} : {universidad_origen:form.universidad_origen.trim(), tipo_institucion:form.tipo_institucion, carrera_origen:form.carrera_origen.trim()}),
  }
  const result = await action.run(()=>api<{message?:string}>(`/coordinator/students${student?`/${student.id}`:''}`,{method:student?'PUT':'POST',body}))
  if(result) onDone(result.message)
 }
 return <Panel title={student?'Editar estudiante':'Registrar estudiante'} actions={<button className="btn btn-ghost" disabled={action.busy} onClick={onCancel}>Cerrar</button>}>
  <Alert error={action.error}/>
  <form className="form-grid management-form coordinator-student-form" onSubmit={submit}>
   <div className="student-form-section"><strong>1. Datos del estudiante</strong><p>Identificación y datos de contacto para su cuenta.</p></div>
   <Field label="Nombres completos" error={fieldError(action.error,'nombres_completos')}><input autoFocus required maxLength={255} value={form.nombres_completos} onChange={e=>set('nombres_completos',e.target.value)}/></Field>
   <Field label="Tipo de identificación"><select value={form.tipo_identificacion} onChange={e=>setForm({...form,tipo_identificacion:e.target.value,cedula:''})}><option value="cedula">Cédula ecuatoriana</option><option value="pasaporte">Pasaporte</option></select></Field>
   <Field label={form.tipo_identificacion==='cedula'?'Número de cédula':'Número de pasaporte'} error={fieldError(action.error,'cedula')} hint={form.tipo_identificacion==='cedula'?'Exactamente 10 dígitos.':'Entre 5 y 20 letras o números.'}><input required inputMode={form.tipo_identificacion==='cedula'?'numeric':'text'} minLength={form.tipo_identificacion==='cedula'?10:5} maxLength={form.tipo_identificacion==='cedula'?10:20} pattern={form.tipo_identificacion==='cedula'?'[0-9]{10}':'[A-Za-z0-9]{5,20}'} value={form.cedula} onChange={e=>set('cedula',e.target.value.toUpperCase())}/></Field>
   <Field label="Correo electrónico" error={fieldError(action.error,'email')}><input required type="email" value={form.email} onChange={e=>set('email',e.target.value)}/></Field>
   <Field label="Celular" error={fieldError(action.error,'numero_celular')}><input required inputMode="numeric" minLength={10} maxLength={10} pattern="[0-9]{10}" value={form.numero_celular} onChange={e=>set('numero_celular',e.target.value)}/></Field>
   <div className="student-form-section"><strong>2. Destino en la UEB</strong><p>La carrera que recibirá la homologación debe estar asignada a tu coordinación.</p></div>
   {!availableDestinations.length && <div className="student-form-notice">No tienes carreras activas con modalidades disponibles. El administrador debe configurar la oferta y asignarte una carrera antes de registrar estudiantes.</div>}
   <Field label="Carrera de destino" error={fieldError(action.error,'carrera_id')}><select required value={form.carrera_id} onChange={e=>setForm({...form,carrera_id:e.target.value,modalidad_id:'',carrera_origen_id:form.carrera_origen_id===e.target.value?'':form.carrera_origen_id})}><option value="">Selecciona una de tus carreras</option>{availableDestinations.map(c=><option key={c.id} value={c.id}>{c.nombre}{c.facultad ? ` · ${c.facultad}` : ''}</option>)}</select></Field>
   <Field label="Modalidad de destino" error={fieldError(action.error,'modalidad_id')}><select required disabled={!destination?.activa} value={form.modalidad_id} onChange={e=>set('modalidad_id',e.target.value)}><option value="">{form.carrera_id?'Selecciona una modalidad':'Selecciona primero la carrera'}</option>{destination?.modalidades.filter(m=>m.activa).map(m=><option key={m.id} value={m.id}>{m.nombre}</option>)}</select></Field>
   <div className="student-form-section"><strong>3. Procedencia de los estudios</strong><p>Indica dónde estudió antes y qué carrera desea homologar.</p></div>
   <fieldset className="student-origin-options"><legend>Tipo de homologación</legend>{[{value:'interna',title:'Interna · misma universidad',text:'Estudios de otra carrera de la UEB.'},{value:'externa',title:'Externa · otra institución',text:'Estudios realizados fuera de la UEB.'}].map(option=><label key={option.value} className={form.procedencia===option.value?'selected':''}><input required type="radio" name="procedencia" value={option.value} checked={form.procedencia===option.value} onChange={()=>setForm(old=>({...old,procedencia:option.value,carrera_origen_id:'',carrera_origen:'',universidad_origen:'',tipo_institucion:'publica'}))}/><span><strong>{option.title}</strong><small>{option.text}</small></span></label>)}</fieldset>
   {background && !background.procedencia && <div className="student-form-notice">Este registro anterior no tiene procedencia clasificada. Revisa sus datos antes de elegir interna o externa: {background.universidad_origen} · {background.carrera_origen}.</div>}
   {internal ? <>
    <Field label="Institución de origen"><input readOnly value={institution}/></Field>
    <Field label="Carrera de origen en la UEB" error={fieldError(action.error,'carrera_origen_id')} hint="Puede pertenecer a otra coordinación. Debe ser distinta de la carrera de destino."><select required disabled={!form.carrera_id} value={form.carrera_origen_id} onChange={e=>set('carrera_origen_id',e.target.value)}><option value="">{form.carrera_id?'Selecciona la carrera de origen':'Selecciona primero el destino'}</option>{internalOrigins.map(c=><option key={c.id} value={c.id}>{c.nombre}{c.facultad?` · ${c.facultad}`:''}</option>)}</select></Field>
    {form.carrera_id && !internalOrigins.length && <div className="student-form-notice">No hay otra carrera activa de la UEB para una homologación interna. El administrador debe registrar la carrera de origen.</div>}
   </> : form.procedencia === 'externa' ? <>
    <Field label="Institución de origen" error={fieldError(action.error,'universidad_origen')} hint="Nombre completo de la institución donde cursó sus estudios."><input required maxLength={255} placeholder="Ej.: Universidad de Cuenca" value={form.universidad_origen} onChange={e=>set('universidad_origen',e.target.value)}/></Field>
    <Field label="Tipo de institución"><select required value={form.tipo_institucion} onChange={e=>set('tipo_institucion',e.target.value)}><option value="publica">Universidad pública</option><option value="privada">Universidad privada</option><option value="instituto">Instituto</option></select></Field>
    <Field label="Carrera de origen en esa institución" error={fieldError(action.error,'carrera_origen')} hint="Escríbela como aparece en el certificado. Puede llamarse igual que la carrera de destino."><input required maxLength={255} value={form.carrera_origen} onChange={e=>set('carrera_origen',e.target.value)} placeholder="Nombre de la carrera externa"/></Field>
   </> : null}
   {form.procedencia && <Field label="Ciclo o semestre cursado" error={fieldError(action.error,'periodo_cursado')} hint={background?.periodo_cursado && !academicCycles.includes(background.periodo_cursado) ? 'El registro anterior indica '+background.periodo_cursado+'. Selecciona el ciclo o semestre correspondiente.' : 'Nivel cursado en la institución de origen.'}><select required value={form.periodo_cursado} onChange={e=>set('periodo_cursado',e.target.value)}><option value="">Selecciona un ciclo o semestre</option>{academicCycles.map(cycle=><option key={cycle} value={cycle}>{cycle}</option>)}</select></Field>}
   <div className="student-form-notice">Las nuevas cuentas ingresan con su correo y su identificación como contraseña inicial; deben cambiarla en el primer ingreso.</div>
   <div className="form-actions"><button type="button" className="btn btn-outline" disabled={action.busy} onClick={onCancel}>Cancelar</button><button className="btn btn-primary" disabled={action.busy||!availableDestinations.length||!form.procedencia}>{action.busy?'Guardando…':student?'Guardar cambios':'Crear estudiante'}</button></div>
  </form>
 </Panel>
}
export function CoordinatorStudents(_props:{me:ApiUser}){
 const [search,setSearch]=useState('');const [type,setType]=useState('');const [page,setPage]=useState(1)
 const [editing,setEditing]=useState<Student|'new'|null>(null);const [statusTarget,setStatusTarget]=useState<Student|null>(null)
 const debouncedSearch=useDebouncedValue(search)
 const students=useAsync(()=>api<Paginated<Student>>('/coordinator/students',{query:{search:debouncedSearch,tipo_identificacion:type,page,per_page:12}}),[debouncedSearch,type,page])
 const catalog=useAsync(()=>api<{data:{carreras:Career[];carreras_origen:OriginCareer[];universidad_interna:string}}>('/coordinator/catalogo').then(r=>r.data),[])
 const action=useAction()
 async function editStudent(id:number){const r=await action.run(()=>api<{data:Student}>(`/coordinator/students/${id}`));if(r){setEditing(r.data);setStatusTarget(null)}}
 async function changeStatus(){if(!statusTarget)return;const result=await action.run(()=>api(`/coordinator/students/${statusTarget.id}/status`,{method:'PATCH',body:{cuenta_activa:!statusTarget.cuenta_activa}}),'Estado de cuenta actualizado.');if(result){setStatusTarget(null);students.reload()}}
 return <><PageHeader kicker="COORDINACIÓN ACADÉMICA" title="Estudiantes" subtitle="Registra estudiantes y asigna su carrera y modalidad de destino." actions={<button className="btn btn-primary" onClick={()=>{setEditing('new');setStatusTarget(null)}}><Plus size={16}/>Nuevo estudiante</button>}/>
 <div className="management-banner"><GraduationCap size={28}/><div><strong>Un expediente, un acompañamiento claro</strong><p>Administra los estudiantes de tus carreras. Su documentación se recibe y valida presencialmente.</p></div><span>{students.data?.meta.total??'—'} estudiantes</span></div>
 <Alert error={students.error??catalog.error??action.error} message={action.message}/>
 {editing&&catalog.data&&<StudentForm key={editing==='new'?'new':editing.id} student={editing==='new'?undefined:editing} careers={catalog.data.carreras} origins={catalog.data.carreras_origen} institution={catalog.data.universidad_interna} onDone={(message)=>{setEditing(null);students.reload();action.setMessage(message??'Estudiante guardado correctamente.')}} onCancel={()=>setEditing(null)}/>}
 {statusTarget&&<div className="confirm-banner"><p>¿{statusTarget.cuenta_activa?'Desactivar':'Reactivar'} la cuenta de <strong>{statusTarget.nombres_completos}</strong>? Su historial se conservará.</p><button className="btn btn-outline" onClick={()=>setStatusTarget(null)}>Cancelar</button><button className="btn btn-primary" disabled={action.busy} onClick={changeStatus}>Confirmar</button></div>}
 <Panel><div className="management-toolbar"><label className="search-box"><Search size={17}/><input aria-label="Buscar estudiantes" placeholder="Nombre, identificación o correo…" value={search} onChange={e=>{setSearch(e.target.value);setPage(1)}}/></label><select aria-label="Filtrar identificación" value={type} onChange={e=>{setType(e.target.value);setPage(1)}}><option value="">Todas las identificaciones</option><option value="cedula">Cédula</option><option value="pasaporte">Pasaporte</option></select></div>
 {students.loading&&!students.data?<Loading/>:!students.data?.data.length?<Empty>No se encontraron estudiantes. Puedes registrar uno nuevo o ajustar la búsqueda.</Empty>:<div className="table-wrap"><table className="data-table"><thead><tr><th>Estudiante</th><th>Identificación</th><th>Estado</th><th>Acciones</th></tr></thead><tbody>{students.data.data.map(s=><tr key={s.id}><td><strong>{s.nombres_completos}</strong><small className="cell-description">{s.email}</small></td><td>{s.cedula}<small className="cell-description">{s.tipo_identificacion==='pasaporte'?'Pasaporte':'Cédula'}</small></td><td><span className={`status ${s.cuenta_activa?'approved':''}`}>{s.cuenta_activa?'Activo':'Inactivo'}</span></td><td><div className="row-actions"><button className="btn btn-ghost" disabled={action.busy} onClick={()=>editStudent(s.id)}><Pencil size={15}/>Editar</button><button className="btn btn-ghost" onClick={()=>setStatusTarget(s)}>{s.cuenta_activa?<UserRoundX size={15}/>:<UserCheck size={15}/>} {s.cuenta_activa?'Desactivar':'Reactivar'}</button></div></td></tr>)}</tbody></table></div>}
 {students.data&&<Pager meta={students.data.meta} onPage={setPage}/>}</Panel></>
}
