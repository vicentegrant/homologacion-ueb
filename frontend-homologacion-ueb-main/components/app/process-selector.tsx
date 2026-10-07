'use client'

import { useState } from 'react'
import { ArrowRightLeft, BookOpenCheck, Check } from 'lucide-react'
import { Procedure, procedureLabel } from '@/lib/requirements'
import { Field } from '@/components/app/ui'

export function ProcessSelector({procedures,value,onChange,disabled=false,error,typeError}:{procedures:Procedure[];value:string;onChange:(value:string)=>void;disabled?:boolean;error?:string;typeError?:string}) {
 const selected=procedures.find(p=>p.id===Number(value))
 const [choice,setChoice]=useState(selected?.tipo_tramite??'')
 const type=selected?.tipo_tramite??choice
 const options=procedures.filter(p=>p.tipo_tramite===type)
 function chooseType(next:string){setChoice(next);const matches=procedures.filter(p=>p.tipo_tramite===next);onChange(matches.length===1?String(matches[0].id):'')}
 return <div className="process-selector">
  <fieldset className="process-choice-menu" disabled={disabled}>
   <legend>Primero, elige el proceso <span className="required-mark">*</span></legend>
   <div className="process-choice-options">{[{id:'homologacion',label:'Homologación',description:'Entre carreras de la UEB o desde otra institución.',Icon:ArrowRightLeft},{id:'reconocimiento',label:'Reconocimiento',description:'Comparación de malla a malla.',Icon:BookOpenCheck}].map(({id,label,description,Icon})=><label className={type===id?'selected':''} key={id}><input required type="radio" name="procedure_type" value={id} checked={type===id} onChange={()=>chooseType(id)}/><span className="process-choice-icon"><Icon size={22}/></span><span className="process-choice-text"><strong>{label}</strong><small>{description}</small></span>{type===id&&<Check className="process-choice-check" size={18}/>}</label>)}</div>
   {(typeError??(!type?error:undefined))&&<small className="field-error" role="alert">{typeError??error}</small>}
  </fieldset>
  {type&&options.length>1&&<Field label={type==='homologacion'?'Tipo de homologación':'Tipo de reconocimiento'} error={error}><select required name="tramite_proceso_id" disabled={disabled} value={value} onChange={e=>onChange(e.target.value)}><option value="">Selecciona la opción correspondiente</option>{options.map(p=><option key={p.id} value={p.id}>{procedureLabel(p)}</option>)}</select></Field>}
  {type&&options.length===1&&<div className="process-selected-detail"><strong>{type==='reconocimiento'?'Proceso de reconocimiento':'Proceso de homologación'}</strong><span>{procedureLabel(options[0])}</span>{error&&<small className="field-error" role="alert">{error}</small>}</div>}
  {type&&!options.length&&<p className="field-error" role="alert">No hay opciones configuradas para este proceso.</p>}
 </div>
}
