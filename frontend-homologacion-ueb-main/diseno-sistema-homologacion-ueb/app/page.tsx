'use client'

import { FormEvent, useEffect, useState } from 'react'
import { ArrowRight, ShieldCheck } from 'lucide-react'
import { ApiUser, getCurrentUser, getToken, loginRequest, logoutRequest, onUnauthorized, primaryRole, UserRole } from '@/lib/api'
import { useHashRoute } from '@/lib/router'
import { BrandMark } from '@/components/app/brand'
import { AppShell } from '@/components/app/shell'
import { PageHeader } from '@/components/app/ui'
import { DashboardView } from '@/views/dashboard'
import { EstudiantesList, SolicitudesList } from '@/views/shared'
import { StudentSolicitudes } from '@/views/student/solicitudes'
import { StudentSolicitudDetalle } from '@/views/student/solicitud-detalle'
import { StudentPerfil } from '@/views/student/perfil'
import { StudentNotificaciones } from '@/views/student/notificaciones'
import { CoordinatorSolicitudDetalle } from '@/views/coordinator/solicitud-detalle'
import { MallaDetalle, MallasList } from '@/views/coordinator/mallas'
import { AdminUsuarios } from '@/views/admin/usuarios'
import { AdminSolicitudDetalle } from '@/views/admin/solicitud-detalle'

// ---------------------------------------------------------------------------
// Login
// ---------------------------------------------------------------------------

function LoginView({ onLogin, notice }: { onLogin: (user: ApiUser) => void; notice?: string }) {
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
        <div className="login-top"><BrandMark /><button className="help-button" aria-label="Ayuda">?</button></div>
        <div className="login-content">
          <div className="mascot-wrap"><img src="/images/foxi-login.png" alt="Foxi, el zorro mascota de la Universidad Estatal de Bolívar" /></div>
          <p className="section-kicker">PORTAL DE HOMOLOGACIÓN</p>
          <h2>Bienvenido de nuevo</h2>
          <p className="login-subtitle">Ingresa tus credenciales para continuar con tu solicitud.</p>
          {notice && <p className="form-notice" role="status">{notice}</p>}
          <form onSubmit={handleSubmit}>
            <label>Correo institucional<input type="email" placeholder="nombre@ueb.edu.ec" value={email} onChange={(event) => setEmail(event.target.value)} autoComplete="username" required /></label>
            <label>Contraseña<div className="password-field"><input type={showPassword ? 'text' : 'password'} placeholder="••••••••" value={password} onChange={(event) => setPassword(event.target.value)} autoComplete="current-password" required /><button type="button" className="password-toggle" onClick={() => setShowPassword((value) => !value)}>{showPassword ? 'Ocultar' : 'Mostrar'}</button></div></label>
            <div className="form-row"><label className="remember"><input type="checkbox" checked={remember} onChange={(event) => setRemember(event.target.checked)} /> <span>Recordarme</span></label><span className="forgot-hint" title="Las cuentas las gestiona el Administrador">¿Olvidaste tu contraseña? Contacta al Administrador</span></div>
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
    if (section === 'estudiantes') return <EstudiantesList base="/coordinator" />
    if (section === 'mallas') return Number.isFinite(id) ? <MallaDetalle id={id} /> : <MallasList />
  }

  if (role === 'administrador') {
    if (section === 'usuarios') return <AdminUsuarios me={user} />
    if (section === 'solicitudes') return Number.isFinite(id) ? <AdminSolicitudDetalle id={id} /> : <SolicitudesList base="/admin" />
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

  useEffect(() => {
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
  }

  function handleLogin(loggedUser: ApiUser) {
    setNotice('')
    window.location.hash = ''
    setUser(loggedUser)
  }

  if (checking) return <main className="session-loading"><BrandMark /><p>Verificando sesión...</p></main>
  if (!user) return <LoginView notice={notice} onLogin={handleLogin} />
  return <AuthenticatedApp key={user.id} user={user} onLogout={handleLogout} />
}
