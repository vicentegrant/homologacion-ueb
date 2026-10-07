'use client'

import dynamic from 'next/dynamic'
import { FormEvent, useEffect, useState } from 'react'
import { ArrowRight, Eye, EyeOff, ShieldCheck } from 'lucide-react'
import { ApiUser, getCurrentUser, getToken, loginRequest, logoutRequest, onUnauthorized, primaryRole, UserRole } from '@/lib/api'
import { useHashRoute } from '@/lib/router'
import { BrandMark } from '@/components/app/brand'
import { AppShell } from '@/components/app/shell'
import { Loading, PageHeader } from '@/components/app/ui'

// Descargar cada secci?n cuando se abre, evitando cargar todos los paneles al iniciar.
const AdminCatalogos = dynamic(() => import('@/views/admin/catalogos').then((module) => module.AdminCatalogos), { loading: () => <Loading text="Cargando secci?n?" /> })
const CoordinatorStudents = dynamic(() => import('@/views/coordinator/estudiantes').then((module) => module.CoordinatorStudents), { loading: () => <Loading text="Cargando secci?n?" /> })
const AdminRequirements = dynamic(() => import('@/views/coordinator/requisitos').then(module => module.AdminRequirements))
const CoordinatorRequirements = dynamic(() => import('@/views/coordinator/requisitos').then((module) => module.CoordinatorRequirements), { loading: () => <Loading text="Cargando secci?n?" /> })
const PasswordView = dynamic(() => import('@/views/password').then((module) => module.PasswordView), { loading: () => <Loading text="Cargando secci?n?" /> })
const DashboardView = dynamic(() => import('@/views/dashboard').then((module) => module.DashboardView), { loading: () => <Loading text="Cargando secci?n?" /> })
const EstudiantesList = dynamic(() => import('@/views/shared').then((module) => module.EstudiantesList), { loading: () => <Loading text="Cargando secci?n?" /> })
const SolicitudesList = dynamic(() => import('@/views/shared').then((module) => module.SolicitudesList), { loading: () => <Loading text="Cargando secci?n?" /> })
const StudentSolicitudes = dynamic(() => import('@/views/student/solicitudes').then((module) => module.StudentSolicitudes), { loading: () => <Loading text="Cargando secci?n?" /> })
const StudentSolicitudDetalle = dynamic(() => import('@/views/student/solicitud-detalle').then((module) => module.StudentSolicitudDetalle), { loading: () => <Loading text="Cargando secci?n?" /> })
const StudentPerfil = dynamic(() => import('@/views/student/perfil').then((module) => module.StudentPerfil), { loading: () => <Loading text="Cargando secci?n?" /> })
const StudentNotificaciones = dynamic(() => import('@/views/student/notificaciones').then((module) => module.StudentNotificaciones), { loading: () => <Loading text="Cargando secci?n?" /> })
const CoordinatorSolicitudDetalle = dynamic(() => import('@/views/coordinator/solicitud-detalle').then((module) => module.CoordinatorSolicitudDetalle), { loading: () => <Loading text="Cargando secci?n?" /> })
const MallaDetalle = dynamic(() => import('@/views/coordinator/mallas').then((module) => module.MallaDetalle), { loading: () => <Loading text="Cargando secci?n?" /> })
const MallasList = dynamic(() => import('@/views/coordinator/mallas').then((module) => module.MallasList), { loading: () => <Loading text="Cargando secci?n?" /> })
const AdminUsuarios = dynamic(() => import('@/views/admin/usuarios').then((module) => module.AdminUsuarios), { loading: () => <Loading text="Cargando secci?n?" /> })

// ---------------------------------------------------------------------------
// Login
// ---------------------------------------------------------------------------

function LoginView({ onLogin, notice, onRecover }: { onLogin: (user: ApiUser) => void; notice?: string; onRecover: () => void }) {
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [remember, setRemember] = useState(false)
  const [showPassword, setShowPassword] = useState(false)
  const [error, setError] = useState('')
  const [loading, setLoading] = useState(false)

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setError('')
    setLoading(true)
    try {
      const user = await loginRequest(email, password, remember)
      onLogin(user)
    } catch (loginError) {
      setError(loginError instanceof Error ? loginError.message : 'No se pudo iniciar sesión.')
    } finally {
      setLoading(false)
    }
  }

  return (
    <main className="login-shell">
      <div className="login-visual">
        <div className="login-visual-image" />
        <div className="image-tint" />
        <div className="visual-copy">
          <div className="eyebrow"><span /> SISTEMA ACADÉMICO INTEGRAL</div>
          <h1>Tu trayectoria<br /><em>también cuenta.</em></h1>
          <p>Gestiona la homologación de tus estudios con transparencia, agilidad y el respaldo de la Universidad Estatal de Bolívar.</p>
          <div className="visual-footer"><span className="flag-dot blue-dot" /><span className="flag-dot white-dot" /><span className="flag-dot red-dot" /> Guaranda · Ecuador</div>
        </div>
        <div className="mountain-badge"><ShieldCheck size={16} /> Plataforma segura UEB</div>
      </div>
      <section className="login-panel">
        <div className="login-content">
          <div className="login-top"><BrandMark /><div className="login-brand-actions"><div className="mascot-wrap"><img src="/images/foxi-login.png" alt="Foxi, mascota de la UEB" /></div><button className="help-button" aria-label="Ayuda">?</button></div></div>
          <p className="section-kicker">PORTAL DE HOMOLOGACIÓN</p>
          <h2>Bienvenido de nuevo</h2>
          <p className="login-subtitle">Ingresa tus credenciales para continuar con tu solicitud.</p>
          {notice && <p className="form-notice" role="status">{notice}</p>}
          <form onSubmit={handleSubmit}>
            <label>Correo electrónico<input type="email" placeholder="nombre@ejemplo.com" value={email} onChange={(event) => setEmail(event.target.value)} autoComplete="username" required /></label>
            <label>Contraseña<div className="password-field"><input type={showPassword ? 'text' : 'password'} placeholder="••••••••" value={password} onChange={(event) => setPassword(event.target.value)} autoComplete="current-password" required /><button type="button" className="password-toggle" aria-label={showPassword ? 'Ocultar contraseña' : 'Mostrar contraseña'} title={showPassword ? 'Ocultar contraseña' : 'Mostrar contraseña'} aria-pressed={showPassword} onClick={() => setShowPassword((value) => !value)}>{showPassword ? <EyeOff size={19} aria-hidden="true" /> : <Eye size={19} aria-hidden="true" />}</button></div></label>
            <div className="form-row"><label className="remember"><input type="checkbox" checked={remember} onChange={(event) => setRemember(event.target.checked)} /> <span>Recordarme</span></label><button type="button" className="link-button" onClick={onRecover}>¿Olvidaste tu contraseña?</button></div>
            {error && <p className="form-error" role="alert">{error}</p>}<button className="primary-button" type="submit" disabled={loading}>{loading ? 'Validando...' : 'Ingresar al sistema'} {!loading && <ArrowRight size={17} />}</button>
          </form>
          <div className="login-note"><ShieldCheck size={15} /> Tus datos están protegidos por la infraestructura institucional.</div>
        </div>
        <footer className="login-footer"><span>© {new Date().getFullYear()} UEB</span><span>Soporte técnico</span><span>Política de privacidad</span></footer>
      </section>
    </main>
  )
}

// ---------------------------------------------------------------------------
// Rutas por rol (#/seccion/id)
// ---------------------------------------------------------------------------

function NotFound() {
  return <PageHeader title="Sección no disponible" subtitle="Esta página no existe o no corresponde a tu rol." back="" />
}

function RoleRoutes({ user, role, segments }: { user: ApiUser; role: UserRole; segments: string[] }) {
  const [section, param] = segments
  const id = param ? Number(param) : NaN

  if (!section) return <DashboardView user={user} role={role} />

  if (role === 'estudiante') {
    if (section === 'solicitudes') return Number.isFinite(id) ? <StudentSolicitudDetalle id={id} /> : <StudentSolicitudes />
    if (section === 'perfil') return <StudentPerfil />
    if (section === 'notificaciones') return <StudentNotificaciones />
  }

  if (role === 'coordinador') {
    if (section === 'solicitudes') return Number.isFinite(id) ? <CoordinatorSolicitudDetalle id={id} /> : <SolicitudesList base="/coordinator" careers={user.carreras_coordinadas} />
    if (section === 'estudiantes') return <CoordinatorStudents me={user} />
    if (section === 'requisitos') return <CoordinatorRequirements />
    if (section === 'mallas') return Number.isFinite(id) ? <MallaDetalle id={id} /> : <MallasList />
  }

  if (role === 'administrador') {
    if (section === 'requisitos') return <AdminRequirements />
    if (section === 'catalogos') return <AdminCatalogos />
    if (section === 'usuarios') return <AdminUsuarios me={user} />
    if (section === 'estudiantes') return <EstudiantesList base="/admin" />
  }

  return <NotFound />
}

function AuthenticatedApp({ user, onLogout }: { user: ApiUser; onLogout: () => void }) {
  const segments = useHashRoute()
  const role = primaryRole(user)

  if (!user.roles.length) {
    return (
      <main className="session-loading">
        <BrandMark />
        <p>Tu cuenta no tiene un rol asignado. Solicita al Administrador que te asigne uno.</p>
        <button className="btn btn-outline" onClick={onLogout}>Cerrar sesión</button>
      </main>
    )
  }

  return (
    <AppShell user={user} role={role} section={segments[0] ?? ''} onLogout={onLogout}>
      <RoleRoutes key={segments.join('/')} user={user} role={role} segments={segments} />
    </AppShell>
  )
}

// ---------------------------------------------------------------------------
// Raíz: restaura la sesión con /me, maneja 401 y logout
// ---------------------------------------------------------------------------

export default function Page() {
  const [user, setUser] = useState<ApiUser | null>(null)
  const [checking, setChecking] = useState(true)
  const [notice, setNotice] = useState('')
  const [passwordMode, setPasswordMode] = useState<'forgot' | 'reset' | null>(null)

  useEffect(() => {
    const params = new URLSearchParams(window.location.search)
    if (params.has('reset_token') || params.has('token')) setPasswordMode('reset')
    onUnauthorized(() => {
      setUser(null)
      setNotice('Tu sesión expiró o fue cerrada. Inicia sesión nuevamente.')
    })
    if (!getToken()) {
      setChecking(false)
      return () => onUnauthorized(null)
    }
    getCurrentUser()
      .then(setUser)
      .catch(() => setUser(null))
      .finally(() => setChecking(false))
    return () => onUnauthorized(null)
  }, [])

  async function handleLogout() {
    await logoutRequest().catch(() => undefined)
    setNotice('')
    setUser(null)
    window.location.hash = ''
    window.history.replaceState({}, '', '/')
    setPasswordMode(null)
  }

  function handleLogin(loggedUser: ApiUser) {
    setNotice('')
    window.location.hash = ''
    setUser(loggedUser)
  }

  if (checking) return <main className="session-loading"><BrandMark /><p>Verificando sesión...</p></main>
  if (passwordMode) return <PasswordView mode={passwordMode} onDone={handleLogout} />
  if (user?.password_temporal || user?.must_change_password) return <PasswordView mode="change" loginEmail={user.email} onChanged={handleLogin} onDone={handleLogout} />
  if (!user) return <LoginView notice={notice} onLogin={handleLogin} onRecover={() => setPasswordMode('forgot')} />
  return <AuthenticatedApp key={user.id} user={user} onLogout={handleLogout} />
}
