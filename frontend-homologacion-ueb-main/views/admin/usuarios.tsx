'use client'

import { FormEvent, useState } from 'react'
import { Pencil, Plus, Search } from 'lucide-react'
import { api, ApiUser, Paginated } from '@/lib/api'
import { formatDate } from '@/lib/format'
import { Alert, Empty, Field, fieldError, Loading, PageHeader, Pager, Panel, useAction, useAsync } from '@/components/app/ui'
import { roleLabels } from '@/components/app/shell'

type Usuario = Omit<ApiUser, 'roles'> & { roles?: string[]; creador?: { nombres_completos: string } | null; carreras?: { id: number; nombre: string }[]; created_at: string }
type Rol = { id: number; nombre: string }

const emptyForm = { tipo_identificacion: 'cedula', nombres_completos: '', cedula: '', email: '', numero_celular: '', password: '', rol_id: '' }

function UsuarioForm({ roles, user, onDone, onCancel }: { roles: Rol[]; user?: Usuario; onDone: () => void; onCancel: () => void }) {
  const action = useAction()
  const currentRole = roles.find((r) => user?.roles?.includes(r.nombre))
  const [form, setForm] = useState(user ? { tipo_identificacion: user.tipo_identificacion ?? 'cedula', nombres_completos: user.nombres_completos, cedula: user.cedula ?? '', email: user.email, numero_celular: user.numero_celular ?? '', password: '', rol_id: String(currentRole?.id ?? '') } : emptyForm)
  const set = (key: keyof typeof form) => (e: { target: { value: string } }) => setForm({ ...form, [key]: e.target.value })

  async function submit(event: FormEvent) {
    event.preventDefault()
    const body: Record<string, unknown> = { ...form, rol_id: Number(form.rol_id) }
    delete body.password
    const ok = await action.run(() => api(user ? `/admin/users/${user.id}` : '/admin/users', { method: user ? 'PATCH' : 'POST', body }))
    if (ok) onDone()
  }

  return (
    <Panel title={user ? `Editar: ${user.nombres_completos}` : 'Nuevo usuario'} actions={<button className="btn btn-ghost" onClick={onCancel}>Cancelar</button>}>
      <Alert error={action.error} />
      <form className="form-grid" onSubmit={submit}>
        <Field label="Nombres completos" error={fieldError(action.error, 'nombres_completos')}><input required maxLength={255} value={form.nombres_completos} onChange={set('nombres_completos')} /></Field>
        <Field label="Tipo de identificación"><select value={form.tipo_identificacion} onChange={e=>setForm({...form,tipo_identificacion:e.target.value,cedula:''})}><option value="cedula">Cédula</option><option value="pasaporte">Pasaporte</option></select></Field><Field label="Número de identificación" error={fieldError(action.error, 'cedula')}><input required minLength={form.tipo_identificacion==='cedula'?10:5} maxLength={form.tipo_identificacion==='cedula'?10:20} pattern={form.tipo_identificacion==='cedula'?'[0-9]{10}':'[A-Z0-9]{5,20}'} value={form.cedula} onChange={e=>setForm({...form,cedula:e.target.value.toUpperCase()})}/></Field>
        <Field label="Correo" error={fieldError(action.error, 'email')}><input required type="email" value={form.email} onChange={set('email')} /></Field>
        <Field label="Celular" error={fieldError(action.error, 'numero_celular')}><input required minLength={7} maxLength={20} value={form.numero_celular} onChange={set('numero_celular')} /></Field>
        <p>Las cuentas nuevas reciben una contraseña temporal por correo y deben cambiarla al ingresar. Para recuperar el acceso, utiliza Recuperar contraseña.</p>
          <Field label="Rol" error={fieldError(action.error, 'rol_id')}>
          <select required value={form.rol_id} onChange={set('rol_id')}><option value="">Selecciona…</option>{roles.map((r) => <option key={r.id} value={r.id}>{roleLabels[r.nombre as keyof typeof roleLabels] ?? r.nombre}</option>)}</select>
        </Field>
        <div className="form-actions"><button className="btn btn-primary" disabled={action.busy}>{action.busy ? 'Guardando...' : user ? 'Guardar cambios' : 'Crear usuario'}</button></div>
      </form>
    </Panel>
  )
}

function CarrerasCoordinador({ user, onClose }: { user: Usuario; onClose: () => void }) {
  const all = useAsync(() => api<{ data: { id: number; nombre: string }[] }>('/admin/careers').then((r) => r.data), [])
  const assigned = useAsync(() => api<{ data: { id: number }[] }>(`/admin/coordinators/${user.id}/careers`).then((r) => r.data.map((c) => c.id)), [user.id])
  const action = useAction()
  const [selected, setSelected] = useState<number[] | null>(null)
  const current = selected ?? assigned.data ?? []

  async function save() {
    await action.run(() => api(`/admin/coordinators/${user.id}/careers`, { method: 'PUT', body: { carrera_ids: current } }), 'Carreras actualizadas.')
  }

  return (
    <Panel title={`Carreras de ${user.nombres_completos}`} actions={<button className="btn btn-ghost" onClick={onClose}>Cerrar</button>}>
      <Alert error={all.error ?? assigned.error ?? action.error} message={action.message} />
      {all.loading || assigned.loading ? <Loading /> : !all.data?.length ? <Empty>No hay carreras registradas en el sistema.</Empty> : (
        <>
          <div className="check-grid">{all.data.map((c) => (
            <label key={c.id} className="check-inline"><input type="checkbox" checked={current.includes(c.id)} onChange={(e) => setSelected(e.target.checked ? [...current, c.id] : current.filter((id) => id !== c.id))} /> {c.nombre}</label>
          ))}</div>
          <div className="form-actions"><button className="btn btn-primary" onClick={save} disabled={action.busy}>Guardar asignación</button></div>
        </>
      )}
    </Panel>
  )
}

export function AdminUsuarios({ me }: { me: ApiUser }) {
  const roles = useAsync(() => api<{ data: Rol[] }>('/roles').then((r) => r.data), [])
  const [filters, setFilters] = useState({ search: '', rol: '', tipo_identificacion: '' })
  const [draft, setDraft] = useState('')
  const [page, setPage] = useState(1)
  const list = useAsync(() => api<Paginated<Usuario>>('/admin/users', { query: { ...filters, page } }), [filters, page])
  const action = useAction()
  const [mode, setMode] = useState<{ type: 'new' } | { type: 'edit' | 'careers'; user: Usuario } | null>(null)

  async function toggleStatus(u: Usuario) {
    const ok = await action.run(() => api(`/admin/users/${u.id}/status`, { method: 'PATCH', body: { cuenta_activa: !u.cuenta_activa } }), u.cuenta_activa ? 'Cuenta desactivada.' : 'Cuenta activada.')
    if (ok) list.reload()
  }

  function done() {
    setMode(null)
    action.setMessage('Usuario guardado correctamente.')
    list.reload()
  }

  return (
    <>
      <PageHeader kicker="ADMINISTRACIÓN" title="Usuarios" subtitle="Crea cuentas, asigna roles y controla el acceso. No existe autorregistro." actions={!mode && <button className="btn btn-primary" onClick={() => setMode({ type: 'new' })}><Plus size={15} /> Nuevo usuario</button>} />
      <Alert error={roles.error ?? action.error} message={action.message} />
      {mode?.type === 'new' && roles.data && <UsuarioForm roles={roles.data} onDone={done} onCancel={() => setMode(null)} />}
      {mode?.type === 'edit' && roles.data && <UsuarioForm key={mode.user.id} roles={roles.data} user={mode.user} onDone={done} onCancel={() => setMode(null)} />}
      {mode?.type === 'careers' && <CarrerasCoordinador key={mode.user.id} user={mode.user} onClose={() => setMode(null)} />}
      <Panel>
        <form className="toolbar" onSubmit={(e) => { e.preventDefault(); setFilters({ ...filters, search: draft }); setPage(1) }}>
          <div className="search-input"><Search size={15} /><input placeholder="Nombre, identificación o correo" value={draft} onChange={(e) => setDraft(e.target.value)} /></div>
          <select aria-label="Tipo de identificación" value={filters.tipo_identificacion} onChange={e=>{setFilters({...filters,tipo_identificacion:e.target.value});setPage(1)}}><option value="">Cédula y pasaporte</option><option value="cedula">Cédula</option><option value="pasaporte">Pasaporte</option></select><select value={filters.rol} onChange={(e) => { setFilters({ ...filters, rol: e.target.value }); setPage(1) }} aria-label="Rol"><option value="">Todos los roles</option>{roles.data?.map((r) => <option key={r.id} value={r.nombre}>{roleLabels[r.nombre as keyof typeof roleLabels] ?? r.nombre}</option>)}</select>
          <button className="btn btn-outline">Buscar</button>
        </form>
        <Alert error={list.error} onRetry={list.reload} />
        {list.loading && !list.data ? <Loading /> : list.data?.data.length ? (
          <div className="table-wrap"><table className="data-table">
            <thead><tr><th>Usuario</th><th>Identificación</th><th>Rol</th><th>Creado</th><th>Cuenta</th><th /></tr></thead>
            <tbody>{list.data.data.map((u) => {
              const isCoord = u.roles?.includes('coordinador')
              return (
                <tr key={u.id}>
                  <td><strong>{u.nombres_completos}</strong><small className="cell-sub">{u.email}</small></td>
                  <td>{u.cedula}</td>
                  <td>{u.roles?.map((r) => roleLabels[r as keyof typeof roleLabels] ?? r).join(', ') || '—'}{isCoord && u.carreras?.length ? <small className="cell-sub">{u.carreras.map((c) => c.nombre).join(', ')}</small> : null}</td>
                  <td>{formatDate(u.created_at)}{u.creador && <small className="cell-sub">por {u.creador.nombres_completos}</small>}</td>
                  <td><span className={`badge ${u.cuenta_activa ? 'badge-ok' : 'badge-off'}`}>{u.cuenta_activa ? 'Activa' : 'Inactiva'}</span></td>
                  <td className="row-actions">
                    <button className="btn btn-ghost" onClick={() => setMode({ type: 'edit', user: u })} aria-label="Editar"><Pencil size={14} /></button>
                    {isCoord && <button className="btn btn-ghost" onClick={() => setMode({ type: 'careers', user: u })}>Carreras</button>}
                    {u.id !== me.id && <button className="btn btn-ghost" disabled={action.busy} onClick={() => toggleStatus(u)}>{u.cuenta_activa ? 'Desactivar' : 'Activar'}</button>}
                  </td>
                </tr>
              )
            })}</tbody>
          </table></div>
        ) : <Empty>No se encontraron usuarios.</Empty>}
        <Pager meta={list.data?.meta} onPage={setPage} />
      </Panel>
    </>
  )
}
