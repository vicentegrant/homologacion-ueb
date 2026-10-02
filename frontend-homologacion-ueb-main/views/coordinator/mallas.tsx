'use client'

import { FormEvent, useState } from 'react'
import { ArrowRight, Plus } from 'lucide-react'
import { api, Paginated } from '@/lib/api'
import { href, navigate } from '@/lib/router'
import { Alert, Empty, Field, fieldError, KeyValue, Loading, PageHeader, Pager, Panel, useAction, useAsync } from '@/components/app/ui'
import { Asignatura, getCoordinatorCatalogo, Malla } from './catalogo'

function NuevaMalla({ onCancel }: { onCancel: () => void }) {
  const catalogo = useAsync(() => getCoordinatorCatalogo(), [])
  const students = useAsync(() => api<Paginated<{ id: number; nombres_completos: string; cedula: string }>>('/coordinator/students', { query: { per_page: 100 } }), [])
  const action = useAction()
  const [form, setForm] = useState({ nombre: '', tipo: 'institucional', carrera_id: '', estudiante_id: '' })

  async function submit(event: FormEvent) {
    event.preventDefault()
    const body = form.tipo === 'institucional'
      ? { nombre: form.nombre, tipo: form.tipo, carrera_id: Number(form.carrera_id), activa: true }
      : { nombre: form.nombre, tipo: form.tipo, estudiante_id: Number(form.estudiante_id), activa: true }
    const created = await action.run(() => api<{ data: Malla }>('/coordinator/curricula', { method: 'POST', body }))
    if (created) navigate(`mallas/${created.data.id}`)
  }

  return (
    <Panel title="Nueva malla curricular" actions={<button className="btn btn-ghost" onClick={onCancel}>Cancelar</button>}>
      <Alert error={action.error ?? catalogo.error ?? students.error} />
      <form className="form-grid" onSubmit={submit}>
        <Field label="Nombre" error={fieldError(action.error, 'nombre')}><input required maxLength={150} value={form.nombre} onChange={(e) => setForm({ ...form, nombre: e.target.value })} placeholder="Malla 2024 — Ingeniería de Software" /></Field>
        <Field label="Tipo" hint="Institucional: plan de la UEB. Origen: materias que cursó el estudiante en otra institución.">
          <select value={form.tipo} onChange={(e) => setForm({ ...form, tipo: e.target.value })}><option value="institucional">Institucional (UEB)</option><option value="origen">Origen (del estudiante)</option></select>
        </Field>
        {form.tipo === 'institucional' ? (
          <Field label="Carrera" error={fieldError(action.error, 'carrera_id')}>
            <select required value={form.carrera_id} onChange={(e) => setForm({ ...form, carrera_id: e.target.value })}><option value="">Selecciona…</option>{catalogo.data?.carreras.map((c) => <option key={c.id} value={c.id}>{c.nombre}</option>)}</select>
          </Field>
        ) : (
          <Field label="Estudiante" error={fieldError(action.error, 'estudiante_id')}>
            <select required value={form.estudiante_id} onChange={(e) => setForm({ ...form, estudiante_id: e.target.value })}><option value="">Selecciona…</option>{students.data?.data.map((s) => <option key={s.id} value={s.id}>{s.nombres_completos} ({s.cedula})</option>)}</select>
          </Field>
        )}
        <div className="form-actions"><button className="btn btn-primary" disabled={action.busy}>Crear malla</button></div>
      </form>
    </Panel>
  )
}

export function MallasList() {
  const [creating, setCreating] = useState(false)
  const [tipo, setTipo] = useState('')
  const [page, setPage] = useState(1)
  const list = useAsync(() => api<Paginated<Malla>>('/coordinator/curricula', { query: { tipo, page } }), [tipo, page])

  return (
    <>
      <PageHeader kicker="PLANES DE ESTUDIO" title="Mallas curriculares" subtitle="Registra la malla institucional de tu carrera y la malla de origen de cada estudiante para poder compararlas." actions={!creating && <button className="btn btn-primary" onClick={() => setCreating(true)}><Plus size={15} /> Nueva malla</button>} />
      {creating && <NuevaMalla onCancel={() => setCreating(false)} />}
      <Panel actions={<select className="compact" value={tipo} onChange={(e) => { setTipo(e.target.value); setPage(1) }} aria-label="Tipo"><option value="">Todos los tipos</option><option value="institucional">Institucionales</option><option value="origen">De origen</option></select>} title="Mallas registradas">
        <Alert error={list.error} onRetry={list.reload} />
        {list.loading && !list.data ? <Loading /> : list.data?.data.length ? (
          <div className="table-wrap"><table className="data-table">
            <thead><tr><th>Nombre</th><th>Tipo</th><th>Carrera / Estudiante</th><th>Estado</th><th /></tr></thead>
            <tbody>{list.data.data.map((m) => (
              <tr key={m.id} className="clickable" onClick={() => navigate(`mallas/${m.id}`)}>
                <td><strong>{m.nombre}</strong></td><td>{m.tipo === 'institucional' ? 'Institucional' : 'Origen'}</td><td>{m.carrera?.nombre ?? m.estudiante?.nombres_completos ?? '—'}</td>
                <td><span className={`badge ${m.activa ? 'badge-ok' : 'badge-off'}`}>{m.activa ? 'Activa' : 'Inactiva'}</span></td>
                <td><a className="row-arrow" href={href(`mallas/${m.id}`)} aria-label={`Abrir ${m.nombre}`}><ArrowRight size={16} /></a></td>
              </tr>
            ))}</tbody>
          </table></div>
        ) : <Empty>No hay mallas registradas.</Empty>}
        <Pager meta={list.data?.meta} onPage={setPage} />
      </Panel>
    </>
  )
}

const emptySubject = { codigo_asignatura: '', nombre_asignatura: '', numero_creditos: '', nivel_ciclo: 'primero', hr_carga_horaria: '' }

export function MallaDetalle({ id }: { id: number }) {
  const malla = useAsync(() => api<{ data: Malla }>(`/coordinator/curricula/${id}`).then((r) => r.data), [id])
  const catalogo = useAsync(() => getCoordinatorCatalogo(), [])
  const action = useAction()
  const [form, setForm] = useState(emptySubject)

  if (malla.loading && !malla.data) return <Loading />
  if (!malla.data) return <><PageHeader title="Malla curricular" back="mallas" /><Alert error={malla.error} onRetry={malla.reload} /></>
  const m = malla.data
  const asignaturas: Asignatura[] = m.asignaturas ?? []

  async function toggle() {
    const ok = await action.run(() => api(`/coordinator/curricula/${id}/status`, { method: 'PATCH', body: { activa: !m.activa } }), m.activa ? 'Malla desactivada.' : 'Malla activada.')
    if (ok) malla.reload()
  }

  async function addSubject(event: FormEvent) {
    event.preventDefault()
    const ok = await action.run(() => api(`/coordinator/curricula/${id}/subjects`, { method: 'POST', body: { ...form, numero_creditos: Number(form.numero_creditos), hr_carga_horaria: Number(form.hr_carga_horaria) } }), 'Asignatura agregada.')
    if (ok) { setForm({ ...emptySubject, nivel_ciclo: form.nivel_ciclo }); malla.reload() }
  }

  const totalCreditos = asignaturas.reduce((sum, a) => sum + Number(a.numero_creditos), 0)

  return (
    <>
      <PageHeader back="mallas" kicker={m.tipo === 'institucional' ? 'MALLA INSTITUCIONAL' : 'MALLA DE ORIGEN'} title={m.nombre}
        actions={<button className="btn btn-outline" onClick={toggle} disabled={action.busy}>{m.activa ? 'Desactivar' : 'Activar'}</button>} />
      <Alert error={action.error} message={action.message} />
      <Panel title="Información">
        <KeyValue items={[[m.tipo === 'institucional' ? 'Carrera' : 'Estudiante', m.carrera?.nombre ?? m.estudiante?.nombres_completos], ['Estado', m.activa ? 'Activa' : 'Inactiva'], ['Asignaturas', asignaturas.length], ['Créditos totales', totalCreditos]]} />
      </Panel>
      <Panel title="Asignaturas">
        <form className="form-grid subject-form" onSubmit={addSubject}>
          <Field label="Código" error={fieldError(action.error, 'codigo_asignatura')}><input required maxLength={50} value={form.codigo_asignatura} onChange={(e) => setForm({ ...form, codigo_asignatura: e.target.value })} /></Field>
          <Field label="Nombre" error={fieldError(action.error, 'nombre_asignatura')}><input required maxLength={150} value={form.nombre_asignatura} onChange={(e) => setForm({ ...form, nombre_asignatura: e.target.value })} /></Field>
          <Field label="Créditos" error={fieldError(action.error, 'numero_creditos')}><input required type="number" min={0} value={form.numero_creditos} onChange={(e) => setForm({ ...form, numero_creditos: e.target.value })} /></Field>
          <Field label="Ciclo" error={fieldError(action.error, 'nivel_ciclo')}><select value={form.nivel_ciclo} onChange={(e) => setForm({ ...form, nivel_ciclo: e.target.value })}>{(catalogo.data?.niveles_ciclo ?? ['primero']).map((n) => <option key={n} value={n}>{n.charAt(0).toUpperCase() + n.slice(1)}</option>)}</select></Field>
          <Field label="Carga horaria" error={fieldError(action.error, 'hr_carga_horaria')}><input required type="number" min={0} value={form.hr_carga_horaria} onChange={(e) => setForm({ ...form, hr_carga_horaria: e.target.value })} /></Field>
          <div className="form-actions"><button className="btn btn-primary" disabled={action.busy}><Plus size={14} /> Agregar</button></div>
        </form>
        {asignaturas.length ? (
          <div className="table-wrap"><table className="data-table">
            <thead><tr><th>Código</th><th>Asignatura</th><th>Ciclo</th><th>Créditos</th><th>Horas</th></tr></thead>
            <tbody>{asignaturas.map((a) => <tr key={a.id}><td>{a.codigo_asignatura}</td><td>{a.nombre_asignatura}</td><td>{a.nivel_ciclo}</td><td>{a.numero_creditos}</td><td>{a.hr_carga_horaria}</td></tr>)}</tbody>
          </table></div>
        ) : <Empty>Esta malla aún no tiene asignaturas.</Empty>}
      </Panel>
    </>
  )
}
