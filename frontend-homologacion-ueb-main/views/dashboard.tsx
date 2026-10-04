'use client'

import { ArrowRight, BookOpen, CheckCircle2, ClipboardCheck, GraduationCap } from 'lucide-react'
import { adminApi, ApiUser, coordinatorApi, EstadoConteo, studentApi, UserRole } from '@/lib/api'
import { initials } from '@/lib/format'
import { href, navigate } from '@/lib/router'
import { Alert, Empty, Loading, StatusPill, useAsync } from '@/components/app/ui'

type Metric = { label: string; value: number; detail: string; icon: typeof ClipboardCheck; tone: 'blue' | 'red' | 'green' }
type RecentRow = { id: number; person: string; career: string; origin: string; estado: string | null }
type DashboardData = { metrics: Metric[]; recent: RecentRow[] }

function countStates(porEstado: EstadoConteo[], estados: string[]) {
  return porEstado.filter((item) => estados.includes(item.estado)).reduce((sum, item) => sum + item.total, 0)
}

async function loadDashboard(role: UserRole, user: ApiUser): Promise<DashboardData> {
  if (role === 'estudiante') {
    const [solicitudes, notificaciones] = await Promise.all([
      studentApi.solicitudes({ per_page: 100 }),
      studentApi.notificaciones({ per_page: 1, sin_leer: 1 }),
    ])
    const items = solicitudes.data
    const enCurso = items.filter((s) => !['listo', 'rechazado'].includes(s.estado_actual ?? '')).length
    const observadas = items.filter((s) => s.estado_actual === 'observado').length
    const finalizadas = items.filter((s) => s.estado_actual === 'listo').length
    return {
      metrics: [
        { label: 'Mis solicitudes', value: solicitudes.meta.total, detail: `${enCurso} en curso`, icon: ClipboardCheck, tone: 'blue' },
        { label: 'Requieren corrección', value: observadas, detail: observadas ? 'Revisa las observaciones' : 'Sin observaciones', icon: BookOpen, tone: 'red' },
        { label: 'Homologaciones finalizadas', value: finalizadas, detail: `${notificaciones.sin_leer} avisos sin leer`, icon: CheckCircle2, tone: 'green' },
      ],
      recent: items.slice(0, 5).map((s) => ({
        id: s.id,
        person: s.tramite ? `${s.tramite.tipo_tramite} · ${s.tramite.tipo_proceso}` : `Solicitud #${s.id}`,
        career: s.carrera?.nombre ?? '—',
        origin: s.procedencia_estudios ?? '—',
        estado: s.estado_actual,
      })),
    }
  }

  const [report, dashboard] = await Promise.all([
    role === 'administrador' ? adminApi.reporteSolicitudes({ per_page: 5 }) : coordinatorApi.reporteSolicitudes({ per_page: 5 }),
    role === 'administrador' ? adminApi.dashboard() : Promise.resolve(null),
  ])
  const { total, por_estado, registros } = report.data

  let firstDetail = `${countStates(por_estado, ['pendiente'])} pendientes de recepción`
  if (dashboard) {
    firstDetail = `${dashboard.data.usuarios.activos} usuarios activos`
  } else if (user.carreras_coordinadas?.length) {
    firstDetail = `${user.carreras_coordinadas.length} carrera(s) asignada(s)`
  }

  return {
    metrics: [
      { label: 'Solicitudes registradas', value: total, detail: firstDetail, icon: ClipboardCheck, tone: 'blue' },
      { label: 'En revisión documental', value: countStates(por_estado, ['en_revision', 'observado']), detail: `${countStates(por_estado, ['observado'])} observadas`, icon: BookOpen, tone: 'red' },
      { label: 'Homologaciones finalizadas', value: countStates(por_estado, ['listo']), detail: `${countStates(por_estado, ['aprobado', 'en_consejo'])} aprobadas o en Consejo`, icon: CheckCircle2, tone: 'green' },
    ],
    recent: registros.map((s) => ({
      id: s.id,
      person: s.estudiante?.nombres_completos ?? `Solicitud #${s.id}`,
      career: s.carrera?.nombre ?? '—',
      origin: s.procedencia_estudios ?? '—',
      estado: s.estado_actual?.nombre ?? null,
    })),
  }
}

const roleCopy: Record<UserRole, { title: string; subtitle: string; cta: string; path: string }> = {
  estudiante: { title: 'Mi proceso de homologación', subtitle: 'Consulta tus avances, documentos y equivalencias académicas.', cta: 'Nueva solicitud', path: 'solicitudes' },
  administrador: { title: 'Control administrativo', subtitle: 'Supervisa usuarios, permisos y el flujo completo de homologaciones.', cta: 'Gestionar usuarios', path: 'usuarios' },
  coordinador: { title: 'Panel de homologación', subtitle: 'Revisa el estado de los trámites y mantén el proceso académico en movimiento.', cta: 'Revisar solicitudes', path: 'solicitudes' },
}

const avatarColors = ['blue', 'red', 'purple']

export function DashboardView({ user, role }: { user: ApiUser; role: UserRole }) {
  const { data, error, loading, reload } = useAsync(() => loadDashboard(role, user), [role, user.id])
  const copy = roleCopy[role]
  const isStudent = role === 'estudiante'

  return (
    <>
      <div className="welcome-banner"><div><span className="section-kicker light">GESTIÓN ACADÉMICA</span><h2>{copy.title}</h2><p>{copy.subtitle}</p></div><div className="banner-decoration"><div className="ring ring-one" /><div className="ring ring-two" /><GraduationCap size={42} /></div><button onClick={() => navigate(copy.path)}>{copy.cta} <ArrowRight size={16} /></button></div>
      <Alert error={error} onRetry={reload} />
      <div className="metric-grid">
        {(data?.metrics ?? []).map(({ label, value, detail, icon: Icon, tone }) => <article className="metric-card" key={label}><div className={`metric-icon ${tone}`}><Icon size={19} /></div><div><p>{label}</p><strong>{String(value).padStart(2, '0')}</strong><span className={tone === 'red' && value > 0 ? 'negative' : ''}>{detail}</span></div><div className="sparkline" aria-hidden="true"><span /><span /><span /><span /><span /></div></article>)}
        {!data && loading && [0, 1, 2].map((i) => <article className="metric-card skeleton" key={i} aria-hidden="true" />)}
      </div>
      <div className="section-heading"><div><p className="section-kicker">SEGUIMIENTO</p><h2>{isStudent ? 'Mis solicitudes' : 'Solicitudes recientes'}</h2></div><a className="outline-button" href={href('solicitudes')}>Ver todas <ArrowRight size={15} /></a></div>
      <div className="requests-card">
        <div className="table-header"><span>{isStudent ? 'Trámite' : 'Solicitante'}</span><span>Carrera de destino</span><span>Institución de origen</span><span>Estado</span><span /></div>
        {data?.recent.map((request, index) => (
          <a className="request-row" key={request.id} href={href(`solicitudes/${request.id}`)}>
            <div className="request-person"><div className={`person-avatar ${avatarColors[index % avatarColors.length]}`}>{isStudent ? `#${request.id}` : initials(request.person)}</div><strong>{request.person}</strong></div>
            <span>{request.career}</span><span className="origin">{request.origin}</span><StatusPill estado={request.estado} /><span className="row-arrow"><ArrowRight size={17} /></span>
          </a>
        ))}
        {data && data.recent.length === 0 && <Empty>{isStudent ? 'Aún no has creado solicitudes de homologación.' : 'No hay solicitudes registradas en tu alcance.'}</Empty>}
        {!data && loading && <Loading text="Cargando solicitudes..." />}
      </div>
    </>
  )
}
