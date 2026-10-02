'use client'

import { ReactNode, useEffect, useState } from 'react'
import { Bell, BookOpen, ClipboardCheck, GraduationCap, LayoutDashboard, LogOut, Menu, UserRound, Users, X } from 'lucide-react'
import { ApiUser, studentApi, UserRole } from '@/lib/api'
import { firstName, greeting, initials, todayLabel } from '@/lib/format'
import { href } from '@/lib/router'
import { BrandMark } from './brand'

export const roleLabels: Record<UserRole, string> = {
  estudiante: 'Estudiante',
  administrador: 'Administrador',
  coordinador: 'Coordinador académico',
}

type NavItem = { path: string; label: string; icon: typeof LayoutDashboard }

const navByRole: Record<UserRole, NavItem[]> = {
  estudiante: [
    { path: '', label: 'Panel principal', icon: LayoutDashboard },
    { path: 'solicitudes', label: 'Mis solicitudes', icon: ClipboardCheck },
    { path: 'perfil', label: 'Mi perfil y antecedentes', icon: UserRound },
    { path: 'notificaciones', label: 'Notificaciones', icon: Bell },
  ],
  coordinador: [
    { path: '', label: 'Panel principal', icon: LayoutDashboard },
    { path: 'solicitudes', label: 'Solicitudes', icon: ClipboardCheck },
    { path: 'estudiantes', label: 'Estudiantes', icon: GraduationCap },
    { path: 'mallas', label: 'Mallas curriculares', icon: BookOpen },
  ],
  administrador: [
    { path: '', label: 'Panel principal', icon: LayoutDashboard },
    { path: 'usuarios', label: 'Usuarios', icon: Users },
    { path: 'solicitudes', label: 'Solicitudes', icon: ClipboardCheck },
    { path: 'estudiantes', label: 'Estudiantes', icon: GraduationCap },
  ],
}

export function AppShell({ user, role, section, onLogout, children }: { user: ApiUser; role: UserRole; section: string; onLogout: () => void; children: ReactNode }) {
  const [menuOpen, setMenuOpen] = useState(false)
  const [unread, setUnread] = useState(0)

  useEffect(() => { setMenuOpen(false) }, [section])

  useEffect(() => {
    if (role !== 'estudiante') return
    studentApi.notificaciones({ per_page: 1, sin_leer: 1 }).then((r) => setUnread(r.sin_leer)).catch(() => undefined)
  }, [role, section])

  return (
    <main className="dashboard-shell">
      <aside className={menuOpen ? 'sidebar open' : 'sidebar'}>
        <div className="sidebar-brand"><BrandMark /><button className="mobile-close" onClick={() => setMenuOpen(false)} aria-label="Cerrar menú"><X size={20} /></button></div>
        <div className="workspace-label">ESPACIO DE TRABAJO</div>
        <nav>
          {navByRole[role].map(({ path, label, icon: Icon }) => (
            <a key={path || 'home'} className={section === path ? 'nav-item active' : 'nav-item'} href={href(path)}>
              <Icon size={18} /> {label}
              {path === 'notificaciones' && unread > 0 && <b>{unread}</b>}
            </a>
          ))}
        </nav>
        <div className="sidebar-bottom">
          <button className="nav-item logout" onClick={onLogout}><LogOut size={18} /> Cerrar sesión</button>
          <div className="profile"><div className="avatar">{initials(user.nombres_completos)}</div><div><strong>{user.nombres_completos}</strong><span>{roleLabels[role]}</span></div><span className="online-dot" /></div>
        </div>
      </aside>
      {menuOpen && <button className="sidebar-backdrop" onClick={() => setMenuOpen(false)} aria-label="Cerrar menú" />}
      <section className="dashboard-main">
        <header className="dashboard-header">
          <div className="header-title"><button className="menu-button" onClick={() => setMenuOpen(true)} aria-label="Abrir menú"><Menu size={21} /></button><div><p>{todayLabel()}</p><h1>{greeting()}, {firstName(user.nombres_completos)} <span>✦</span></h1></div></div>
          <div className="header-actions">
            <span className={`role-chip role-${role}`}>{roleLabels[role]}</span>
            {role === 'estudiante' && <a className="icon-button notification" href={href('notificaciones')} aria-label={unread ? `${unread} notificaciones sin leer` : 'Notificaciones'}><Bell size={19} />{unread > 0 && <i />}</a>}
            <div className="header-avatar">{initials(user.nombres_completos)}</div>
          </div>
        </header>
        <div className="dashboard-content">{children}</div>
        <footer className="dashboard-footer"><span><span className="footer-logo">UEB</span> Sistema de Homologación Académica</span><span>Versión 1.0.0 · Ayuda y soporte</span></footer>
      </section>
    </main>
  )
}
