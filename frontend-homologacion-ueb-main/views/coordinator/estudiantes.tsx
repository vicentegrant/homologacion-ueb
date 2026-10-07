'use client'
import { FormEvent, useRef, useState } from 'react'
import { GraduationCap, Pencil, Plus, Search, ClipboardCheck, UserCheck, UserRoundX, ArrowLeft, ArrowRight, Check, FileCheck2, UserRound, School, ListChecks } from 'lucide-react'
import { ProcessSelector } from '@/components/app/process-selector'
import { api, ApiError, ApiUser, Paginated } from '@/lib/api'
import { academicCycles } from '@/lib/format'
import { identificationError } from '@/lib/cedula'
import { Procedure, procedureLabel } from '@/lib/requirements'
import { Alert, Empty, Field, fieldError, Loading, PageHeader, Panel, Pager, useAction, useAsync, useDebouncedValue } from '@/components/app/ui'
type Career={id:number;nombre:string;facultad?:string|null;activa:boolean;modalidades:{id:number;nombre:string;activa:boolean}[]}
type OriginCareer={id:number;nombre:string;facultad?:string|null}
type Student={creador_id:number|null;id:number;nombres_completos:string;nombres?:string|null;apellidos?:string|null;tipo_identificacion:string;cedula:string;email:string;numero_celular:string;cuenta_activa:boolean;tiene_proceso_previo?:boolean;detalle_proceso_previo?:string|null;procesos_registrados?:number;procesos_finalizados?:number;solicitudes?:{id:number;created_at:string;estado_actual?:{nombre:string};tramite?:{tipo_tramite:{nombre:string};tipo_proceso:{nombre:string}}}[];carreras?:{id:number;nombre:string;modalidad_id?:number}[];antecedentes_academicos?:{id:number;procedencia?:'interna'|'externa'|null;carrera_origen_id?:number|null;carrera_origen:string;universidad_origen:string;tipo_institucion:string;periodo_cursado:string}[]}
function StudentForm({student,careers,origins,institution,procedures,onDone,onCancel}:{student?:Student;careers:Career[];origins:OriginCareer[];institution:string;procedures:Procedure[];onDone:(message?:string)=>void;onCancel:()=>void}) {
 const [step,setStep] = useState(0)
 const formRef = useRef<HTMLFormElement>(null)
 const stepNames = ['Trámite y destino','Datos del estudiante','Estudios de origen','Revisar y guardar']
 const stepIcons = [FileCheck2,UserRound,School,ListChecks]
 const action = useAction()
 const background = student?.antecedentes_academicos?.slice().sort((a,b)=>b.id-a.id)[0]
 const [form,setForm] = useState({
  nombres:student?.nombres??'', apellidos:student?.apellidos??'', tipo_identificacion:student?.tipo_identificacion??'cedula', cedula:student?.cedula??'', email:student?.email??'', numero_celular:student?.numero_celular??'',
  procedencia:background?.procedencia??'', carrera_id:String(student?.carreras?.[0]?.id??''), modalidad_id:String(student?.carreras?.[0]?.modalidad_id??''),
  carrera_origen_id:String(background?.carrera_origen_id??''), carrera_origen:background?.carrera_origen??'', universidad_origen:background?.universidad_origen??'', tipo_institucion:background?.tipo_institucion??'publica',
  periodo_cursado:background && academicCycles.includes(background.periodo_cursado) ? background.periodo_cursado : '',
  tramite_proceso_id:'',
 })
 const set = (key:keyof typeof form,value:string)=>setForm(old=>({...old,[key]:value}))
 const internal = form.procedencia === 'interna'
 const destination = careers.find(c=>c.id===Number(form.carrera_id))
 const availableDestinations = careers.filter(c=>c.activa && c.modalidades.some(m=>m.activa))
 const internalOrigins = origins
 const selectedProcedure = procedures.find(p=>p.id===Number(form.tramite_proceso_id))
 const requiredOrigin = !student ? selectedProcedure?.tipo_proceso==='otra_universidad'?'externa':selectedProcedure?.tipo_proceso==='carreras_facultad'?'interna':null : null
 function selectProcedure(id:string) {
  const procedure=procedures.find(p=>p.id===Number(id))
  const origin=procedure?.tipo_proceso==='otra_universidad'?'externa':procedure?.tipo_proceso==='carreras_facultad'?'interna':''
  setForm(old=>({...old,tramite_proceso_id:id,procedencia:origin,carrera_origen_id:'',carrera_origen:'',universidad_origen:'',periodo_cursado:'',tipo_institucion:'publica'}))
 }
 async function nextStep() {
  const section = formRef.current?.querySelector(`[data-form-step="${step}"]`)
  const controls = section?.querySelectorAll<HTMLInputElement|HTMLSelectElement|HTMLTextAreaElement>('input,select,textarea')
  for (const control of controls ?? []) {
   if (!control.checkValidity()) {
    const key=control.name
    action.setError(new ApiError('Revisa el campo indicado.',422,{[key]:[control.validationMessage]}))
    control.focus();return
   }
  }
  if (step===1) {
   const error=identificationError(form.tipo_identificacion,form.cedula)
   if(error){action.setError(new ApiError(error,422,{cedula:[error]}));return}
   const result=await action.run(()=>api<{success:boolean}>(`/coordinator/students${student?`/${student.id}`:''}/validate`,{method:'POST',body:{nombres:form.nombres,apellidos:form.apellidos,tipo_identificacion:form.tipo_identificacion,cedula:form.cedula.trim().toUpperCase(),email:form.email,numero_celular:form.numero_celular}}))
   if(!result)return
  }
  action.setError(null);setStep(old=>Math.min(old+1,3))
 }
 async function submit(e:FormEvent) {
  e.preventDefault()
  if (step<3) {nextStep();return}
  const identityError = identificationError(form.tipo_identificacion, form.cedula)
    if (identityError) { setStep(1);action.setError(new ApiError(identityError,422,{cedula:[identityError]})); return }
  // El origen externo se registra como antecedente, sin crear una carrera en el catálogo UEB.
  const body = {nombres:form.nombres, apellidos:form.apellidos, tipo_identificacion:form.tipo_identificacion, cedula:form.cedula.trim().toUpperCase(), email:form.email, numero_celular:form.numero_celular,
   procedencia:form.procedencia, carrera_id:Number(form.carrera_id), modalidad_id:Number(form.modalidad_id), periodo_cursado:form.periodo_cursado,
   ...(!student ? {tramite_proceso_id:Number(form.tramite_proceso_id)} : {}),
   ...(internal ? {carrera_origen_id:Number(form.carrera_origen_id)} : {universidad_origen:form.universidad_origen.trim(), tipo_institucion:form.tipo_institucion, carrera_origen:form.carrera_origen.trim()}),
  }
  const result = await action.run(async()=>{
   try {return await api<{message?:string}>(`/coordinator/students${student?`/${student.id}`:''}`,{method:student?'PUT':'POST',body})}
   catch(error) {
    if(error instanceof ApiError&&Object.keys(error.errors).length){
     const fields=Object.keys(error.errors)
     setStep(fields.some(f=>['nombres','apellidos','nombres_completos','tipo_identificacion','cedula','email','numero_celular'].includes(f))?1:fields.some(f=>['carrera_id','modalidad_id','tramite_proceso_id'].includes(f))?0:2)
    }
    throw error
   }
  })
  if(result) onDone(result.message)
 }
 return <Panel title={student?'Editar estudiante':'Registrar estudiante'} actions={<button className="btn btn-ghost" disabled={action.busy} onClick={onCancel}>Cerrar</button>}>
  <Alert error={action.error} inlineFields/>
  <nav className="student-wizard-steps" aria-label="Pasos del registro">{stepNames.map((name,index)=>{const Icon=stepIcons[index];return <button type="button" key={name} aria-current={step===index?'step':undefined} className={step===index?'current':step>index?'completed':''} disabled={index>step||action.busy} onClick={()=>setStep(index)}><span>{step>index?<Check size={18}/>:<Icon size={18}/>}</span><div><small>Paso {index+1}</small><strong>{name}</strong></div></button>})}</nav>
  <form ref={formRef} className="form-grid management-form coordinator-student-form" noValidate onSubmit={submit}>
        <p className="required-fields-note"><span className="required-mark" aria-hidden="true">*</span> Campos obligatorios.</p>
   <fieldset className="student-wizard-fields" aria-label="Trámite y carrera de destino" data-form-step="0" hidden={step!==0} disabled={step!==0||action.busy}>   {!student&&<ProcessSelector procedures={procedures} value={form.tramite_proceso_id} onChange={selectProcedure} disabled={action.busy} error={fieldError(action.error,'tramite_proceso_id')} typeError={fieldError(action.error,'procedure_type')}/>}
   <div className="student-form-section"><strong>¿A qué carrera de la UEB desea acceder?</strong><p>Selecciona la carrera y modalidad en las que se registrará el proceso.</p></div>
   {!availableDestinations.length && <div className="student-form-notice">No tienes carreras activas con modalidades disponibles. El administrador debe configurar la oferta y asignarte una carrera antes de registrar estudiantes.</div>}
   <Field label="Carrera de destino" error={fieldError(action.error,'carrera_id')}><select required name="carrera_id" value={form.carrera_id} onChange={e=>setForm({...form,carrera_id:e.target.value,modalidad_id:'',carrera_origen_id:form.carrera_origen_id})}><option value="">Selecciona una de tus carreras</option>{availableDestinations.map(c=><option key={c.id} value={c.id}>{c.nombre}{c.facultad ? ` · ${c.facultad}` : ''}</option>)}</select></Field>
   <Field label="Modalidad de destino" error={fieldError(action.error,'modalidad_id')}><select required disabled={!destination?.activa} name="modalidad_id" value={form.modalidad_id} onChange={e=>set('modalidad_id',e.target.value)}><option value="">{form.carrera_id?'Selecciona una modalidad':'Selecciona primero la carrera'}</option>{destination?.modalidades.filter(m=>m.activa).map(m=><option key={m.id} value={m.id}>{m.nombre}</option>)}</select></Field>
</fieldset>
   <fieldset className="student-wizard-fields" aria-label="Datos del estudiante" data-form-step="1" hidden={step!==1} disabled={step!==1||action.busy}>   <div className="student-form-section"><strong>Identificación y contacto</strong><p>Identificación y datos de contacto para su cuenta.</p></div>
   {student && !student.nombres && <p className="muted">Registro anterior: {student.nombres_completos}. Completa sus nombres y apellidos por separado.</p>}
   <Field label="Nombres" error={fieldError(action.error,'nombres')}><input required maxLength={120} autoComplete="given-name" name="nombres" value={form.nombres} onChange={e=>set('nombres',e.target.value)}/></Field>
   <Field label="Apellidos" error={fieldError(action.error,'apellidos')}><input required maxLength={120} autoComplete="family-name" name="apellidos" value={form.apellidos} onChange={e=>set('apellidos',e.target.value)}/></Field>
   <Field required label="Tipo de identificación" error={fieldError(action.error,'tipo_identificacion')}><select name="tipo_identificacion" value={form.tipo_identificacion} onChange={e=>{setForm({...form,tipo_identificacion:e.target.value,cedula:''});action.setError(null)}}><option value="cedula">Cédula ecuatoriana</option><option value="pasaporte">Pasaporte</option></select></Field>
   <Field label={form.tipo_identificacion==='cedula'?'Número de cédula':'Número de pasaporte'} error={fieldError(action.error,'cedula')} hint={form.tipo_identificacion==='cedula'?'Cédula ecuatoriana: exactamente 10 dígitos, sin letras ni espacios.':'Pasaporte: entre 5 y 20 letras o números, sin espacios ni símbolos.'}><input key={form.tipo_identificacion} required inputMode={form.tipo_identificacion==='cedula'?'numeric':'text'} minLength={form.tipo_identificacion==='cedula'?10:5} maxLength={form.tipo_identificacion==='cedula'?10:20} pattern={form.tipo_identificacion==='cedula'?'[0-9]{10}':'[A-Z0-9]{5,20}'} onInvalid={e=>e.currentTarget.setCustomValidity(identificationError(form.tipo_identificacion,e.currentTarget.value)??'')} onInput={e=>e.currentTarget.setCustomValidity('')} name="cedula" value={form.cedula} onChange={e=>set('cedula',e.target.value.toUpperCase())}/></Field>
   <Field label="Correo electrónico" error={fieldError(action.error,'email')}><input required type="email" name="email" value={form.email} onChange={e=>set('email',e.target.value)}/></Field>
   <Field label="Celular" error={fieldError(action.error,'numero_celular')}><input required inputMode="numeric" minLength={10} maxLength={10} pattern="[0-9]{10}" name="numero_celular" value={form.numero_celular} onChange={e=>set('numero_celular',e.target.value)}/></Field>
</fieldset>
   <fieldset className="student-wizard-fields" aria-label="Estudios de origen" data-form-step="2" hidden={step!==2} disabled={step!==2||action.busy}>   <div className="student-form-section"><strong>¿Dónde realizó sus estudios?</strong><p>Selecciona la institución de origen y completa los estudios que se evaluarán.</p></div>
   {requiredOrigin&&<div className="student-form-notice">{requiredOrigin==='externa'?'Este trámite corresponde a estudios realizados fuera de la UEB. Escribe la institución y carrera de origen.':'Este trámite corresponde a estudios realizados en la UEB. Selecciona la carrera de origen.'} Para cambiar el tipo de origen, vuelve a «Trámite y destino».</div>}
   <fieldset className="student-origin-options"><legend>Institución donde estudió <span className="required-mark">*</span></legend>{[{value:'interna',title:'En la UEB',text:'Estudios realizados en la UEB, en la misma carrera u otra.'},{value:'externa',title:'En otra institución',text:'Estudios realizados fuera de la UEB.'}].filter(option=>!requiredOrigin||option.value===requiredOrigin).map(option=><label key={option.value} className={form.procedencia===option.value?'selected':''}><input required type="radio" name="procedencia" value={option.value} checked={form.procedencia===option.value} onChange={()=>setForm(old=>({...old,procedencia:option.value,carrera_origen_id:'',carrera_origen:'',universidad_origen:'',tipo_institucion:'publica'}))}/><span><strong>{option.title}</strong><small>{option.text}</small></span></label>)}</fieldset>
   {fieldError(action.error,'procedencia')&&<small className="field-error" role="alert">{fieldError(action.error,'procedencia')}</small>}
   {background && !background.procedencia && <div className="student-form-notice">Este registro anterior no tiene procedencia clasificada. Revisa sus datos antes de elegir interna o externa: {background.universidad_origen} · {background.carrera_origen}.</div>}
   {internal ? <>
    <Field label="Institución de origen"><input readOnly value={institution}/></Field>
    <Field label="Carrera de origen en la UEB" error={fieldError(action.error,'carrera_origen_id')} hint="Puede ser la misma carrera de destino; se comparan sus mallas, no solo su nombre."><select required disabled={!form.carrera_id} name="carrera_origen_id" value={form.carrera_origen_id} onChange={e=>set('carrera_origen_id',e.target.value)}><option value="">{form.carrera_id?'Selecciona la carrera de origen':'Selecciona primero el destino'}</option>{internalOrigins.map(c=><option key={c.id} value={c.id}>{c.nombre}{c.facultad?` · ${c.facultad}`:''}</option>)}</select></Field>
    {form.carrera_id && !internalOrigins.length && <div className="student-form-notice">No hay carreras de origen activas de la UEB. El administrador debe registrar la carrera de origen.</div>}
   </> : form.procedencia === 'externa' ? <>
    <Field label="Institución de origen" error={fieldError(action.error,'universidad_origen')} hint="Nombre completo de la institución donde cursó sus estudios."><input required maxLength={255} placeholder="Ej.: Universidad de Cuenca" name="universidad_origen" value={form.universidad_origen} onChange={e=>set('universidad_origen',e.target.value)}/></Field>
    <Field label="Tipo de institución" error={fieldError(action.error,'tipo_institucion')}><select required name="tipo_institucion" value={form.tipo_institucion} onChange={e=>set('tipo_institucion',e.target.value)}><option value="publica">Universidad pública</option><option value="privada">Universidad privada</option><option value="instituto">Instituto</option></select></Field>
    <Field label="Carrera de origen en esa institución" error={fieldError(action.error,'carrera_origen')} hint="Escríbela como aparece en el certificado. Puede llamarse igual que la carrera de destino."><input required maxLength={255} name="carrera_origen" value={form.carrera_origen} onChange={e=>set('carrera_origen',e.target.value)} placeholder="Nombre de la carrera externa"/></Field>
   </> : null}
   {form.procedencia && <Field label="Ciclo o semestre cursado" error={fieldError(action.error,'periodo_cursado')} hint={background?.periodo_cursado && !academicCycles.includes(background.periodo_cursado) ? 'El registro anterior indica '+background.periodo_cursado+'. Selecciona el ciclo o semestre correspondiente.' : 'Nivel cursado en la institución de origen.'}><select required name="periodo_cursado" value={form.periodo_cursado} onChange={e=>set('periodo_cursado',e.target.value)}><option value="">Selecciona un ciclo o semestre</option>{academicCycles.map(cycle=><option key={cycle} value={cycle}>{cycle}</option>)}</select></Field>}

</fieldset>
   <fieldset className="student-wizard-fields" aria-label="Revisión final" data-form-step="3" hidden={step!==3} disabled={step!==3||action.busy}>   <div className="student-form-section"><strong>Confirma los datos antes de guardar</strong><p>{student?'Se actualizará el expediente del estudiante.':'Se creará la cuenta del estudiante y su solicitud con los requisitos de la carrera y trámite elegidos.'}</p></div>
   <div className="student-review-grid">
    {[['Trámite y destino',<><strong>{student?'Se conserva el trámite existente':selectedProcedure?procedureLabel(selectedProcedure):'Sin seleccionar'}</strong><span>{destination?.nombre} · {destination?.modalidades.find(m=>m.id===Number(form.modalidad_id))?.nombre}</span></>],['Estudiante',<dl className="student-review-details">{[['Nombres',form.nombres],['Apellidos',form.apellidos],[form.tipo_identificacion==='cedula'?'Cédula':'Pasaporte',form.cedula],['Correo electrónico',form.email],['Celular',form.numero_celular]].map(([label,value])=><div key={label}><dt>{label}</dt><dd>{value}</dd></div>)}</dl>],['Estudios de origen',<><strong>{internal?institution:form.universidad_origen}</strong><span>{internal?origins.find(c=>c.id===Number(form.carrera_origen_id))?.nombre:form.carrera_origen}</span><span>{form.periodo_cursado}</span></>]].map(([title,content],index)=><article key={index}><div><h3>{title}</h3><button type="button" className="link-button" disabled={action.busy} onClick={()=>setStep(index)}><Pencil size={13} aria-hidden="true" />Editar</button></div>{content}</article>)}
   </div>
</fieldset>
   {!student&&step===3&&<div className="student-form-notice"><FileCheck2 size={20}/><span>Al guardar, se abrirá la solicitud y podrás continuar con la revisión de requisitos y el análisis de mallas.</span></div>}
   <div className="student-wizard-footer"><button type="button" className="btn btn-ghost" disabled={action.busy} onClick={onCancel}>Cancelar</button><div>{step>0&&<button type="button" className="btn btn-outline" disabled={action.busy} onClick={()=>{action.setError(null);setStep(old=>old-1)}}><ArrowLeft size={16}/>Anterior</button>}{step<3?<button key="continue" type="button" className="btn btn-primary" disabled={action.busy||!availableDestinations.length} onClick={nextStep}>{action.busy?'Verificando datos…':'Continuar'}<ArrowRight size={16}/></button>:<button key="save" type="submit" className="btn btn-primary" disabled={action.busy}>{action.busy?'Guardando…':student?'Guardar cambios':'Crear estudiante y solicitud'}<Check size={16}/></button>}</div></div>
  </form>
 </Panel>
}
export function CoordinatorStudents(_props:{me:ApiUser}){
 const [provenance,setProvenance]=useState('');const [sort,setSort]=useState('apellidos');const [search,setSearch]=useState('');const [type,setType]=useState('');const [page,setPage]=useState(1)
 const [processStudent,setProcessStudent]=useState<Student|null>(null);const [processCareer,setProcessCareer]=useState('');const [processProcedure,setProcessProcedure]=useState('')
 const [editing,setEditing]=useState<Student|'new'|null>(null);const [statusTarget,setStatusTarget]=useState<Student|null>(null)
 const debouncedSearch=useDebouncedValue(search)
 const students=useAsync(()=>api<Paginated<Student>>('/coordinator/students',{query:{search:debouncedSearch,procedencia:provenance,sort,tipo_identificacion:type,page,per_page:12}}),[debouncedSearch,type,provenance,sort,page])
 const catalog=useAsync(()=>api<{data:{carreras:Career[];carreras_origen:OriginCareer[];universidad_interna:string;tramites:Procedure[]}}>('/coordinator/catalogo').then(r=>r.data),[])
 const action=useAction()
 async function editStudent(id:number){const r=await action.run(()=>api<{data:Student}>(`/coordinator/students/${id}`));if(r){setEditing(r.data);setStatusTarget(null)}}
 async function changeStatus(){if(!statusTarget)return;const result=await action.run(()=>api(`/coordinator/students/${statusTarget.id}/status`,{method:'PATCH',body:{cuenta_activa:!statusTarget.cuenta_activa}}),'Estado de cuenta actualizado.');if(result){setStatusTarget(null);students.reload()}}
 async function openProcesses(id:number){const result=await action.run(()=>api<{data:Student}>(`/coordinator/students/${id}`));if(result){setProcessStudent(result.data);setProcessCareer(String(result.data.carreras?.[0]?.id??''));setProcessProcedure('');setEditing(null)}}
 async function newProcess(event:FormEvent){event.preventDefault();if(!processStudent)return;const result=await action.run(()=>api(`/coordinator/students/${processStudent.id}/solicitudes`,{method:'POST',body:{carrera_id:Number(processCareer),tramite_proceso_id:Number(processProcedure)}}),'Nuevo proceso registrado. El historial anterior se conserva.');if(result){await openProcesses(processStudent.id);students.reload()}}
 return <><PageHeader icon={GraduationCap} kicker="COORDINACIÓN ACADÉMICA" title="Estudiantes" subtitle="Registra estudiantes y asigna su carrera y modalidad de destino." actions={<button className="btn btn-primary" onClick={()=>{setEditing('new');setStatusTarget(null)}}><Plus size={16}/>Nuevo estudiante</button>}/>
 <div className="management-banner"><GraduationCap size={28}/><div><strong>Un expediente, un acompañamiento claro</strong><p>Administra los estudiantes de tus carreras. Su documentación se recibe y valida presencialmente.</p></div><span>{students.data?.meta.total??'—'} estudiantes</span></div>
 <Alert error={students.error??catalog.error??action.error} message={action.message}/>
 {editing&&catalog.data&&<StudentForm key={editing==='new'?'new':editing.id} student={editing==='new'?undefined:editing} careers={catalog.data.carreras} origins={catalog.data.carreras_origen} institution={catalog.data.universidad_interna} procedures={catalog.data.tramites} onDone={(message)=>{setEditing(null);students.reload();action.setMessage(message??'Estudiante guardado correctamente.')}} onCancel={()=>setEditing(null)}/>}
 {processStudent&&catalog.data&&<Panel title={`Procesos de ${processStudent.nombres_completos}`} actions={<button className="btn btn-ghost" onClick={()=>setProcessStudent(null)}>Cerrar</button>}>
   <p className="field-hint">{processStudent.procesos_registrados??processStudent.solicitudes?.length??0} proceso(s) registrado(s). Consulta el historial antes de registrar otro trámite.</p>
   {processStudent.solicitudes?.length?<div className="process-history">{processStudent.solicitudes.map(record=><a key={record.id} href={`#/solicitudes/${record.id}`}><ClipboardCheck size={18}/><span><strong>Solicitud #{record.id}</strong><small>{record.tramite?.tipo_tramite.nombre} · {record.tramite?.tipo_proceso.nombre.replaceAll('_',' ')}</small></span><span>{record.estado_actual?.nombre.replaceAll('_',' ')}</span></a>)}</div>:<Empty>No hay solicitudes en tu coordinación.</Empty>}
   <form className="form-grid" onSubmit={newProcess}><Field label="Carrera de destino"><select required value={processCareer} onChange={e=>setProcessCareer(e.target.value)}><option value="">Selecciona la carrera</option>{processStudent.carreras?.map(c=><option key={c.id} value={c.id}>{c.nombre}</option>)}</select></Field><ProcessSelector key={processStudent.id} procedures={catalog.data.tramites} value={processProcedure} onChange={setProcessProcedure} disabled={action.busy} error={fieldError(action.error,'tramite_proceso_id')}/><div className="form-actions"><button className="btn btn-primary" disabled={action.busy}>Registrar nuevo proceso</button></div></form>
 </Panel>}
 {statusTarget&&<div className="confirm-banner"><p>¿{statusTarget.cuenta_activa?'Desactivar':'Reactivar'} la cuenta de <strong>{statusTarget.nombres_completos}</strong>? Su historial se conservará.</p><button className="btn btn-outline" onClick={()=>setStatusTarget(null)}>Cancelar</button><button className="btn btn-primary" disabled={action.busy} onClick={changeStatus}>Confirmar</button></div>}
 <Panel><div className="management-toolbar"><label className="search-box"><Search size={17}/><input aria-label="Buscar estudiantes" placeholder="Nombre, identificación o correo…" value={search} onChange={e=>{setSearch(e.target.value);setPage(1)}}/></label><select aria-label="Filtrar identificación" value={type} onChange={e=>{setType(e.target.value);setPage(1)}}><option value="">Todas las identificaciones</option><option value="cedula">Cédula</option><option value="pasaporte">Pasaporte</option></select><select aria-label="Procedencia de estudiantes" value={provenance} onChange={e=>{setProvenance(e.target.value);setPage(1)}}><option value="">Todas las procedencias</option><option value="interna">Estudios de origen en la UEB</option><option value="externa">Estudios de origen en otra institución</option></select><select aria-label="Ordenar estudiantes" value={sort} onChange={e=>{setSort(e.target.value);setPage(1)}}><option value="apellidos">Apellidos A–Z</option><option value="nombres">Nombres A–Z</option></select></div>
 {students.loading&&!students.data?<Loading/>:!students.data?.data.length?<Empty>No se encontraron estudiantes. Puedes registrar uno nuevo o ajustar la búsqueda.</Empty>:<div className="table-wrap"><table className="data-table"><thead><tr><th>Nombres</th><th>Apellidos</th><th>Identificación</th><th>Procesos registrados</th><th>Estado</th><th>Acciones</th></tr></thead><tbody>{students.data.data.map(s=><tr key={s.id}><td><strong>{s.nombres ?? s.nombres_completos}</strong><small className="cell-description">{s.email}</small></td><td>{s.apellidos ?? 'Pendiente de completar'}</td><td>{s.cedula}<small className="cell-description">{s.tipo_identificacion==='pasaporte'?'Pasaporte':'Cédula'}</small></td><td>{s.procesos_registrados ?? 0}<small className="cell-description">{s.procesos_finalizados ?? 0} finalizados</small></td><td><span className={`status ${s.cuenta_activa?'approved':''}`}>{s.cuenta_activa?'Activo':'Inactivo'}</span></td><td><div className="row-actions"><button className="btn btn-ghost" disabled={action.busy} onClick={()=>openProcesses(s.id)}><ClipboardCheck size={15}/>Procesos</button><button className="btn btn-ghost" disabled={action.busy} onClick={()=>editStudent(s.id)}><Pencil size={15}/>Editar</button><button className="btn btn-ghost" onClick={()=>setStatusTarget(s)}>{s.cuenta_activa?<UserRoundX size={15}/>:<UserCheck size={15}/>} {s.cuenta_activa?'Desactivar':'Reactivar'}</button></div></td></tr>)}</tbody></table></div>}
 {students.data&&<Pager meta={students.data.meta} onPage={setPage}/>}</Panel></>
}
