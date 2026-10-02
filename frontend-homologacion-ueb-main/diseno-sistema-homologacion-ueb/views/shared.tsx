'use client'

import { FormEvent, useState } from 'react'
import { ArrowRight, Search } from 'lucide-react'
import { api, Paginated, SolicitudResumen } from '@/lib/api'
import { estadoLabels, formatDate } from '@/lib/format'
import { href, navigate } from '@/lib/router'
import { Alert, Empty, Loading, PageHeader, Pager, Panel, StatusPill, useAsync } from '@/components/app/ui'

/** Listado de solicitudes para Administrador (/admin) y Coordinador (/coordinator). */
export function SolicitudesList({ base, careers }: { base: '/admin' | '/coordinator'; careers?: { id: number; nombre: string }[] }) {
  const [filters, setFilters] = useState({ estado: '', carrera: '', estudiante: '' })
  const [draft, setDraft] = useState(filters.estudiante)
  const [page, setPage] = useState(1)
  const list = useAsync(() => api<Paginated<SolicitudResumen>>(`${base}/solicitudes`, { query: { ...filters, page } }), [base, filters, page])

  function search(event: FormEvent) {
    event.preventDefault()
    setFilters({ ...filters, estudiante: draft })
    setPage(1)
  }

  return (
    <>
      <PageHeader kicker="EXPEDIENTES" title="Solicitudes" subtitle={base === '/coordinator' ? 'Solicitudes de las carreras que coordinas.' : 'Todas las solicitudes del sistema.'} />
      <Panel>
        <form className="toolbar" onSubmit={search}>
          <div className="search-input"><Search size={15} /><input placeholder="Buscar por estudiante" value={draft} onChange={(e) => setDraft(e.target.value)} /></div>
          <select value={filters.estado} onChange={(e) => { setFilters({ ...filters, estado: e.target.value }); setPage(1) }} aria-label="Estado">
            <option value="">Todos los estados</option>
            {Object.entries(estadoLabels).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
          </select>
          {careers && careers.length > 1 && (
            <select value={filters.carrera} onChange={(e) => { setFilters({ ...filters, carrera: e.target.value }); setPage(1) }} aria-label="Carrera">
              <option value="">Todas mis carreras</option>
              {careers.map((c) => <option key={c.id} value={c.id}>{c.nombre}</option>)}
            </select>
          )}
          <button className="btn btn-outline">Buscar</button>
        </form>
        <Alert error={list.error} onRetry={list.reload} />
        {list.loading && !list.data ? <Loading /> : list.data?.data.length ? (
          <div className="table-wrap"><table className="data-table">
            <thead><tr><th>#</th><th>Estudiante</th><th>Carrera</th><th>Procedencia</th>{base === '/admin' && <th>Coordinador</th>}<th>Creada</th><th>Estado</th><th /></tr></thead>
            <tbody>{list.data.data.map((s) => (
              <tr key={s.id} className="clickable" onClick={() => navigate(`solicitudes/${s.id}`)}>
                <td>{s.id}</td><td><strong>{s.estudiante?.nombres_completos}</strong><small className="cell-sub">{s.estudiante?.cedula}</small></td><td>{s.carrera?.nombre ?? '—'}</td><td>{s.procedencia_estudios}</td>
                {base === '/admin' && <td>{s.coordinador?.nombres_completos ?? '—'}</td>}
                <td>{formatDate(s.created_at)}</td><td><StatusPill estado={s.estado_actual?.nombre} /></td>
                <td><a className="row-arrow" href={href(`solicitudes/${s.id}`)} aria-label={`Abrir solicitud ${s.id}`}><ArrowRight size={16} /></a></td>
              </tr>
            ))}</tbody>
          </table></div>
        ) : <Empty>No hay solicitudes que coincidan con los filtros.</Empty>}
        <Pager meta={list.data?.meta} onPage={setPage} />
      </Panel>
    </>
  )
}

type Estudiante = { id: number; nombres_completos: string; cedula: string; email: string; numero_celular: string | null; cuenta_activa: boolean }

export function EstudiantesList({ base }: { base: '/admin' | '/coordinator' }) {
  const [search, setSearch] = useState('')
  const [draft, setDraft] = useState('')
  const [page, setPage] = useState(1)
  const list = useAsync(() => api<Paginated<Estudiante>>(`${base}/students`, { query: { search, page } }), [base, search, page])

  return (
    <>
      <PageHeader kicker="COMUNIDAD" title="Estudiantes" subtitle={base === '/coordinator' ? 'Estudiantes asignados a tus carreras.' : 'Estudiantes registrados en el sistema.'} />
      <Panel>
        <form className="toolbar" onSubmit={(e) => { e.preventDefault(); setSearch(draft); setPage(1) }}>
          <div className="search-input"><Search size={15} /><input placeholder="Nombre, cédula o correo" value={draft} onChange={(e) => setDraft(e.target.value)} /></div>
          <button className="btn btn-outline">Buscar</button>
        </form>
        <Alert error={list.error} onRetry={list.reload} />
        {list.loading && !list.data ? <Loading /> : list.data?.data.length ? (
          <div className="table-wrap"><table className="data-table">
            <thead><tr><th>Nombre</th><th>Cédula</th><th>Correo</th><th>Celular</th><th>Cuenta</th></tr></thead>
            <tbody>{list.data.data.map((s) => <tr key={s.id}><td><strong>{s.nombres_completos}</strong></td><td>{s.cedula}</td><td>{s.email}</td><td>{s.numero_celular ?? '—'}</td><td><span className={`badge ${s.cuenta_activa ? 'badge-ok' : 'badge-off'}`}>{s.cuenta_activa ? 'Activa' : 'Inactiva'}</span></td></tr>)}</tbody>
          </table></div>
        ) : <Empty>No se encontraron estudiantes.</Empty>}
        <Pager meta={list.data?.meta} onPage={setPage} />
      </Panel>
    </>
  )
}
