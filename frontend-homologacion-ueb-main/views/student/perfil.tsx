'use client'

import { FormEvent, useState } from 'react'
import { Pencil, Plus } from 'lucide-react'
import { api } from '@/lib/api'
import { Alert, Empty, Field, fieldError, KeyValue, Loading, PageHeader, Panel, useAction, useAsync } from '@/components/app/ui'

type Antecedente = { id: number; universidad_origen: string; carrera_origen: string; tipo_institucion: string; periodo_cursado: string }
type Perfil = {
  id: number; nombres_completos: string; cedula: string; email: string; numero_celular: string | null
  antecedentes_academicos?: Antecedente[]
  carreras?: { id: number; nombre: string; coordinador: { nombres_completos: string } | null }[]
}

const emptyAntecedente = { universidad_origen: '', carrera_origen: '', tipo_institucion: 'publica', periodo_cursado: '' }

export function StudentPerfil() {
  const perfil = useAsync(() => api<{ data: Perfil }>('/student/profile').then((r) => r.data), [])
  const celularAction = useAction()
  const antAction = useAction()
  const [celular, setCelular] = useState<string | null>(null)
  const [editing, setEditing] = useState<number | 'new' | null>(null)
  const [form, setForm] = useState(emptyAntecedente)

  if (perfil.loading && !perfil.data) return <Loading />
  if (!perfil.data) return <Alert error={perfil.error} onRetry={perfil.reload} />
  const p = perfil.data

  async function guardarCelular(event: FormEvent) {
    event.preventDefault()
    const ok = await celularAction.run(() => api('/student/profile', { method: 'PATCH', body: { numero_celular: celular } }), 'Celular actualizado.')
    if (ok) { setCelular(null); perfil.reload() }
  }

  async function guardarAntecedente(event: FormEvent) {
    event.preventDefault()
    const isNew = editing === 'new'
    const ok = await antAction.run(
      () => api(isNew ? '/student/antecedentes' : `/student/antecedentes/${editing}`, { method: isNew ? 'POST' : 'PATCH', body: form }),
      isNew ? 'Antecedente registrado.' : 'Antecedente actualizado.',
    )
    if (ok) { setEditing(null); setForm(emptyAntecedente); perfil.reload() }
  }

  function startEdit(a?: Antecedente) {
    antAction.setError(null)
    setEditing(a ? a.id : 'new')
    setForm(a ? { universidad_origen: a.universidad_origen, carrera_origen: a.carrera_origen, tipo_institucion: a.tipo_institucion, periodo_cursado: a.periodo_cursado } : emptyAntecedente)
  }

  const antecedentes = p.antecedentes_academicos ?? []

  return (
    <>
      <PageHeader kicker="MI CUENTA" title="Perfil y antecedentes" subtitle="Tus datos personales los administra la universidad; solo puedes actualizar tu número de celular." />
      <div className="grid-2">
        <Panel title="Datos personales">
          <Alert error={celularAction.error} message={celularAction.message} />
          <KeyValue items={[
            ['Nombres', p.nombres_completos],
            ['Cédula', p.cedula],
            ['Correo', p.email],
            ['Celular', celular === null ? <>{p.numero_celular ?? '—'} <button className="link-button" onClick={() => setCelular(p.numero_celular ?? '')}>Editar</button></> : (
              <form className="inline-edit" onSubmit={guardarCelular}><input value={celular} minLength={7} maxLength={20} onChange={(e) => setCelular(e.target.value)} required /><button className="btn btn-primary" disabled={celularAction.busy}>Guardar</button><button type="button" className="btn btn-ghost" onClick={() => setCelular(null)}>Cancelar</button></form>
            )],
          ]} />
        </Panel>
        <Panel title="Carrera asignada">
          {p.carreras?.length ? <KeyValue items={p.carreras.map((c) => [c.nombre ?? 'Carrera', c.coordinador?.nombres_completos ?? 'Sin coordinador'])} /> : <Empty>Aún no tienes carrera asignada. Solicítala al Administrador.</Empty>}
        </Panel>
      </div>

      <Panel title="Antecedentes académicos" actions={editing === null && <button className="btn btn-primary" onClick={() => startEdit()}><Plus size={15} /> Agregar</button>}>
        <Alert error={antAction.error} message={antAction.message} />
        {editing !== null && (
          <form className="form-grid" onSubmit={guardarAntecedente}>
            <Field label="Universidad de origen" error={fieldError(antAction.error, 'universidad_origen')}><input required maxLength={255} value={form.universidad_origen} onChange={(e) => setForm({ ...form, universidad_origen: e.target.value })} /></Field>
            <Field label="Carrera de origen" error={fieldError(antAction.error, 'carrera_origen')}><input required maxLength={255} value={form.carrera_origen} onChange={(e) => setForm({ ...form, carrera_origen: e.target.value })} /></Field>
            <Field label="Tipo de institución" error={fieldError(antAction.error, 'tipo_institucion')}>
              <select value={form.tipo_institucion} onChange={(e) => setForm({ ...form, tipo_institucion: e.target.value })}><option value="publica">Pública</option><option value="privada">Privada</option><option value="cofinanciada">Cofinanciada</option><option value="extranjera">Extranjera</option></select>
            </Field>
            <Field label="Período cursado" error={fieldError(antAction.error, 'periodo_cursado')} hint="Ejemplo: 2022-2024"><input required maxLength={100} value={form.periodo_cursado} onChange={(e) => setForm({ ...form, periodo_cursado: e.target.value })} /></Field>
            <div className="form-actions"><button type="button" className="btn btn-ghost" onClick={() => setEditing(null)}>Cancelar</button><button className="btn btn-primary" disabled={antAction.busy}>{antAction.busy ? 'Guardando...' : 'Guardar'}</button></div>
          </form>
        )}
        {antecedentes.length === 0 && editing === null ? <Empty>No has registrado antecedentes. Son obligatorios para enviar una solicitud.</Empty> : antecedentes.length > 0 && (
          <div className="table-wrap"><table className="data-table">
            <thead><tr><th>Universidad</th><th>Carrera</th><th>Tipo</th><th>Período</th><th /></tr></thead>
            <tbody>{antecedentes.map((a) => <tr key={a.id}><td>{a.universidad_origen}</td><td>{a.carrera_origen}</td><td>{a.tipo_institucion}</td><td>{a.periodo_cursado}</td><td><button className="btn btn-ghost" onClick={() => startEdit(a)} aria-label="Editar antecedente"><Pencil size={14} /></button></td></tr>)}</tbody>
          </table></div>
        )}
      </Panel>
    </>
  )
}
