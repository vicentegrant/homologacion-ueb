'use client'

import {
  ArrowRight,
  FileText,
  GraduationCap,
} from 'lucide-react'
import { adminApi, ApiUser, coordinatorApi, studentApi, UserRole } from '@/lib/api'
import { initials } from '@/lib/format'
import { href, navigate } from '@/lib/router'
import { tramiteLabel } from '@/lib/requirements'
import { Alert, Empty, Loading, StatusPill, useAsync } from '@/components/app/ui'

type RecentRow = { id: number; person: string; career: string; origin: string; estado: string | null }
type DashboardData = { recent: RecentRow[]; unread?: number }

async function loadDashboard(role: UserRole, user: ApiUser): Promise<DashboardData> {
  if (role === 'estudiante') {
    const [solicitudes, notificaciones] = await Promise.all([
      studentApi.solicitudes({ per_page: 5 }),
      studentApi.notificaciones({ per_page: 1, sin_leer: 1 }),
    ])

    return {
      unread: notificaciones.sin_leer,
      recent: solicitudes.data.map((s) => ({
        id: s.id,
        person: s.tramite ? tramiteLabel(s.tramite) : `Solicitud #${s.id}`,
        career: s.carrera?.nombre ?? '—',
        origin: s.procedencia_estudios ?? '—',
        estado: s.estado_actual,
      })),
    }
  }

  const report = await (role === 'administrador'
    ? adminApi.reporteSolicitudes({ per_page: 5 })
    : coordinatorApi.reporteSolicitudes({ per_page: 5 }))

  return {
    recent: report.data.registros.map((s) => ({
      id: s.id,
      person: s.estudiante?.nombres_completos ?? `Solicitud #${s.id}`,
      career: s.carrera?.nombre ?? '—',
      origin: s.procedencia_estudios ?? '—',
      estado: s.estado_actual?.nombre ?? null,
    })),
  }
}

const roleCopy: Record<UserRole, { title: string; subtitle: string; cta: string; path: string }> = {
  estudiante: {
    title: 'Mi proceso de homologación',
    subtitle: 'Consulta tus solicitudes, antecedentes y avances del proceso académico.',
    cta: 'Ver mis solicitudes',
    path: 'solicitudes',
  },
  administrador: {
    title: 'Gestión de homologaciones',
    subtitle: 'Administra usuarios, requisitos, carreras y la información académica del sistema.',
    cta: 'Gestionar usuarios',
    path: 'usuarios',
  },
  coordinador: {
    title: 'Panel de homologación',
    subtitle: 'Revisa solicitudes y organiza la información académica de las carreras a tu cargo.',
    cta: 'Revisar solicitudes',
    path: 'solicitudes',
  },
}

export function DashboardView({ user, role }: { user: ApiUser; role: UserRole }) {
  const { data, error, loading, reload } = useAsync(() => loadDashboard(role, user), [role, user.id])
  const copy = roleCopy[role]
  const isStudent = role === 'estudiante'

  return (
    <>
      <section className="dashboard-welcome" aria-labelledby="dashboard-title">
        <div className="dashboard-welcome-copy">
          <p className="section-kicker light">GESTIÓN ACADÉMICA</p>
          <h2 id="dashboard-title">{copy.title}</h2>
          <p>{copy.subtitle}</p>
          <button className="banner-action" onClick={() => navigate(copy.path)}>
            {copy.cta} <ArrowRight size={16} />
          </button>
        </div>
        <div className="dashboard-welcome-art" aria-hidden="true">
          <GraduationCap size={52} strokeWidth={1.5} />
          <div className="welcome-ring welcome-ring-one" />
          <div className="welcome-ring welcome-ring-two" />
        </div>
      </section>

      <Alert error={error} onRetry={reload} />

      <div className="dashboard-grid">
        <section className="dashboard-section dashboard-recent" aria-labelledby="recent-title">
          <div className="dashboard-section-head">
            <div className="dashboard-section-title">
              <span className="dashboard-section-icon"><FileText size={18} /></span>
              <div>
                <p className="section-kicker">SEGUIMIENTO</p>
                <h2 id="recent-title">{isStudent ? 'Mis solicitudes' : 'Solicitudes recientes'}</h2>
              </div>
            </div>
            {role !== 'administrador' && <a className="outline-button" href={href('solicitudes')}>Ver todas <ArrowRight size={15} /></a>}
          </div>

          <div className="requests-card dashboard-requests-card">
            <div className="table-header"><span>{isStudent ? 'Trámite' : 'Solicitante'}</span><span>Carrera de destino</span><span>Institución de origen</span><span>Estado</span><span /></div>
            {data?.recent.map((request, index) => (
              <a className="request-row" key={request.id} href={role === 'administrador' ? undefined : href(`solicitudes/${request.id}`)}>
                <div className="request-person">
                  <div className={`person-avatar ${['blue', 'red', 'purple'][index % 3]}`}>{isStudent ? `#${request.id}` : initials(request.person)}</div>
                  <strong>{request.person}</strong>
                </div>
                <span>{request.career}</span>
                <span className="origin">{request.origin}</span>
                <StatusPill estado={request.estado} />
                <span className="row-arrow"><ArrowRight size={17} /></span>
              </a>
            ))}
            {data && data.recent.length === 0 && <Empty>{isStudent ? 'Aún no tienes solicitudes registradas.' : 'No hay solicitudes registradas en tu alcance.'}</Empty>}
            {!data && loading && <Loading text="Cargando solicitudes..." />}
          </div>
        </section>

        <aside className="dashboard-side" aria-label="Accesos y ayuda">
          <section className="dashboard-section dashboard-info-card">
            <div className="dashboard-section-title">
              <span className="dashboard-section-icon"><GraduationCap size={18} /></span>
              <div>
                <p className="section-kicker">HOMOLOGACIONES UEB</p>
                <h2>Todo en un solo lugar</h2>
              </div>
            </div>
            <p className="dashboard-info-text">
              {isStudent
                ? 'Desde este espacio puedes consultar tus solicitudes y mantener actualizados tus datos académicos.'
                : role === 'coordinador'
                  ? 'Desde este espacio puedes organizar estudiantes, requisitos, mallas y solicitudes de las carreras asignadas.'
                  : 'Desde este espacio puedes administrar la información necesaria para mantener el proceso de homologación actualizado.'}
            </p>
            {isStudent && (
              <a className="dashboard-notification-link" href={href('notificaciones')}>
                <span>Notificaciones pendientes</span>
                <strong>{data?.unread ?? 0}</strong>
                <ArrowRight size={15} />
              </a>
            )}
          </section>
        </aside>
      </div>
    </>
  )
}
