'use client'

import { FormEvent, useState } from 'react'
import { Check, GraduationCap, Info, Pencil, Plus, Search } from 'lucide-react'
import { api, ApiUser, Paginated } from '@/lib/api'
import { formatDate } from '@/lib/format'
import { Alert, Empty, Field, fieldError, Loading, PageHeader, Pager, Panel, useAction, useAsync } from '@/components/app/ui'
import { roleLabels } from '@/components/app/shell'
import { href } from '@/lib/router'

type Usuario = Omit<ApiUser, 'roles'> & { roles?: string[]; creador?: { id: number; nombres_completos: string } | null; carreras?: { id: number; nombre: string }[]; created_at: string }
type Rol = { id: number; nombre: string }
type Career = { id: number; nombre: string; activa: boolean; facultad_id: number | null; facultad: string | null; modalidades: { id: number; nombre: string; activa: boolean }[] }

const emptyForm = { tipo_identificacion: 'cedula', nombres_completos: '', cedula: '', email: '', numero_celular: '', password: '', rol_id: '' }

function UsuarioForm({ roles, user, onDone, onCancel }: { roles: Rol[]; user?: Usuario; onDone: (message?: string) => void; onCancel: () => void }) {
  const action = useAction()
  const currentRole = roles.find((r) => user?.roles?.includes(r.nombre))
  const catalogs = useAsync(() => api<{ data: { carreras: Career[]; facultades: { id: number; activa: boolean }[] } }>('/admin/catalogs').then(r => r.data), [])
  const [careerIds, setCareerIds] = useState<number[]>(user?.carreras?.map(c => c.id) ?? [])
  const [careerSearch, setCareerSearch] = useState('')
  const [form, setForm] = useState(user ? { tipo_identificacion: user.tipo_identificacion ?? 'cedula', nombres_completos: user.nombres_completos, cedula: user.cedula ?? '', email: user.email, numero_celular: user.numero_celular ?? '', password: '', rol_id: String(currentRole?.id ?? '') } : emptyForm)
  const set = (key: keyof typeof form) => (e: { target: { value: string } }) => setForm({ ...form, [key]: e.target.value })
  const isCoordinator = roles.find(r => String(r.id) === form.rol_id)?.nombre === 'coordinador'

  async function submit(event: FormEvent) {
    event.preventDefault()
    if (isCoordinator && (catalogs.loading || catalogs.error || !catalogs.data)) { action.setError(new Error('Carga los catálogos antes de guardar las carreras.')); return }
    if (isCoordinator && !user && !careerIds.length) { action.setError(new Error('Selecciona al menos una carrera para el coordinador.')); return }
    const body: Record<string, unknown> = { ...form, rol_id: Number(form.rol_id) }
    if (isCoordinator) body.carrera_ids = careerIds
    delete body.password
    const ok = await action.run(() => api<{ message: string; data: Usuario }>(user ? `/admin/users/${user.id}` : '/admin/users', { method: user ? 'PATCH' : 'POST', body }))
    if (ok) onDone(ok.message)
  }

  return (
    <Panel title={user ? `Editar: ${user.nombres_completos}` : 'Nuevo usuario'} actions={<button className="btn btn-ghost" disabled={action.busy} onClick={onCancel}>Cerrar</button>}>
      <Alert error={action.error} />
      <form className="form-grid management-form admin-user-form" onSubmit={submit}>
        <div className="admin-form-heading"><span>Datos de la cuenta</span><small>Completa la identificación y el rol de acceso.</small></div>
        <Field label="Nombres completos" error={fieldError(action.error, 'nombres_completos')}><input required maxLength={255} value={form.nombres_completos} onChange={set('nombres_completos')} /></Field>
        <Field label="Tipo de identificación"><select value={form.tipo_identificacion} onChange={e=>setForm({...form,tipo_identificacion:e.target.value,cedula:''})}><option value="cedula">Cédula</option><option value="pasaporte">Pasaporte</option></select></Field><Field label="Número de identificación" error={fieldError(action.error, 'cedula')}><input required minLength={form.tipo_identificacion==='cedula'?10:5} maxLength={form.tipo_identificacion==='cedula'?10:20} pattern={form.tipo_identificacion==='cedula'?'[0-9]{10}':'[A-Z0-9]{5,20}'} value={form.cedula} onChange={e=>setForm({...form,cedula:e.target.value.toUpperCase()})}/></Field>
        <Field label="Correo" error={fieldError(action.error, 'email')}><input required type="email" value={form.email} onChange={set('email')} /></Field>
        <Field label="Celular" error={fieldError(action.error, 'numero_celular')}><input required inputMode="numeric" minLength={10} maxLength={10} pattern="[0-9]{10}" value={form.numero_celular} onChange={set('numero_celular')} /></Field>
          <Field label="Rol" error={fieldError(action.error, 'rol_id')}>
          <select required value={form.rol_id} onChange={set('rol_id')}><option value="">Selecciona…</option>{roles.map((r) => <option key={r.id} value={r.id}>{roleLabels[r.nombre as keyof typeof roleLabels] ?? r.nombre}</option>)}</select>
        </Field>
        {isCoordinator && <div className="admin-user-careers">
          <div className="admin-form-heading"><span>Carreras que coordinará</span><small>{user ? 'Puedes modificar las asignaciones; las carreras con estudiantes no pueden retirarse.' : 'Selecciona al menos una carrera. La cuenta y las asignaciones se guardan juntas.'}</small></div>
          <Alert error={catalogs.error} onRetry={catalogs.reload} />
          {catalogs.loading ? <Loading /> : catalogs.data && !catalogs.error && <>
            <label className="search-input admin-assignment-search"><Search size={16} aria-hidden="true" /><input aria-label="Buscar carrera o facultad" placeholder="Buscar carrera o facultad…" value={careerSearch} onChange={e => setCareerSearch(e.target.value)} /></label>
            <div className="admin-career-options">{catalogs.data.carreras.filter(c => `${c.nombre} ${c.facultad ?? ''}`.toLocaleLowerCase().includes(careerSearch.toLocaleLowerCase())).map(c => {
              const available = c.activa && (!c.facultad_id || catalogs.data?.facultades.some(f => f.id === c.facultad_id && f.activa))
              const retained = user?.carreras?.some(existing => existing.id === c.id)
              return <label key={c.id} className={`admin-career-option${careerIds.includes(c.id) ? ' selected' : ''}${available ? '' : ' unavailable'}`}>
                <input type="checkbox" disabled={action.busy || (!available && !retained)} checked={careerIds.includes(c.id)} onChange={e => setCareerIds(old => e.target.checked ? [...old, c.id] : old.filter(id => id !== c.id))} />
                <div><strong>{c.nombre}</strong><span>{c.facultad ?? 'Sin facultad registrada'}</span><small>{c.modalidades.map(m => m.nombre).join(' · ') || 'Sin modalidades registradas'}</small>{!available && <small className="admin-unavailable-note">Carrera o facultad inactiva{retained ? ' · asociación existente' : ''}</small>}</div>{careerIds.includes(c.id) && <Check size={18} aria-hidden="true" />}
              </label>
            })}</div>
            {!catalogs.data.carreras.some(c => `${c.nombre} ${c.facultad ?? ''}`.toLocaleLowerCase().includes(careerSearch.toLocaleLowerCase())) && <Empty>No hay carreras para mostrar.</Empty>}
            <div className="admin-assignment-heading"><small>{careerIds.length} carrera{careerIds.length === 1 ? '' : 's'} seleccionada{careerIds.length === 1 ? '' : 's'}</small><a href={href('/catalogos')} className="btn btn-ghost"><GraduationCap size={15} /> Gestionar catálogos</a></div>
          </>}
        </div>}
        {!user && <div className="admin-form-note"><Info size={19} aria-hidden="true" /><div><strong>Acceso inicial del usuario</strong><p>Ingresará con su correo y su número de identificación como contraseña. Deberá cambiarla en el primer ingreso.</p>{roles.find(r => String(r.id) === form.rol_id)?.nombre === 'coordinador' && <p>Las carreras seleccionadas se guardarán junto con la nueva cuenta.</p>}</div></div>}
        <div className="form-actions"><button type="button" className="btn btn-outline" disabled={action.busy} onClick={onCancel}>Cancelar</button><button className="btn btn-primary" disabled={action.busy || (isCoordinator && (catalogs.loading || !!catalogs.error || !catalogs.data || (!user && !careerIds.length)))}>{action.busy ? 'Guardando…' : user ? 'Guardar cambios' : 'Crear usuario'}</button></div>
      </form>
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
  const [mode, setMode] = useState<{ type: 'new' } | { type: 'edit'; user: Usuario } | null>(null)
  const [removing, setRemoving] = useState<Usuario | null>(null)

  async function removeUser() {
    if (!removing) return
    const ok = await action.run(() => api(`/admin/users/${removing.id}`, { method: 'DELETE' }), 'Usuario eliminado correctamente.')
    if (ok) { setRemoving(null); list.reload() }
  }

  async function toggleStatus(u: Usuario) {
    const ok = await action.run(() => api(`/admin/users/${u.id}/status`, { method: 'PATCH', body: { cuenta_activa: !u.cuenta_activa } }), u.cuenta_activa ? 'Cuenta desactivada.' : 'Cuenta activada.')
    if (ok) list.reload()
  }

  function done(message?: string) {
    setMode(null)
    action.setMessage(message ?? 'Usuario guardado correctamente.')
    list.reload()
  }

  return (
    <>
      <PageHeader kicker="ADMINISTRACIÓN" title="Usuarios" subtitle="Crea cuentas, asigna roles y controla el acceso. No existe autorregistro." actions={!mode && <button className="btn btn-primary" onClick={() => setMode({ type: 'new' })}><Plus size={15} /> Nuevo usuario</button>} />
      <Alert error={roles.error ?? action.error} message={action.message} />
      {removing && <div className="confirm-banner" role="alert"><p>¿Eliminar definitivamente la cuenta de <strong>{removing.nombres_completos}</strong>? Si tiene historial académico, deberás desactivarla.</p><button className="btn btn-outline" disabled={action.busy} onClick={() => setRemoving(null)}>Cancelar</button><button className="btn btn-primary" disabled={action.busy} onClick={removeUser}>Confirmar eliminación</button></div>}
      {mode?.type === 'new' && roles.data && <UsuarioForm roles={roles.data} onDone={done} onCancel={() => setMode(null)} />}
      {mode?.type === 'edit' && roles.data && <UsuarioForm key={mode.user.id} roles={roles.data} user={mode.user} onDone={done} onCancel={() => setMode(null)} />}
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
                    {u.id !== me.id && <button className="btn btn-ghost" disabled={action.busy} onClick={() => toggleStatus(u)}>{u.cuenta_activa ? 'Desactivar' : 'Activar'}</button>}
                    {u.id !== me.id && <button className="btn btn-ghost" disabled={action.busy} onClick={() => { setRemoving(u); action.setError(null) }}>Eliminar</button>}
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
