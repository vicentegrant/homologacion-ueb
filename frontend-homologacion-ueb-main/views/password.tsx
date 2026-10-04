'use client'
import { FormEvent, useState } from 'react'
import { api, ApiError } from '@/lib/api'
import { BrandMark } from '@/components/app/brand'

export function PasswordView({ mode, onDone }: { mode: 'change' | 'forgot' | 'reset'; onDone: () => void }) {
  const params = typeof window !== 'undefined' ? new URLSearchParams(window.location.search) : new URLSearchParams()
  const [email, setEmail] = useState(params.get('email') ?? '')
  const [current, setCurrent] = useState('')
  const [password, setPassword] = useState('')
  const [confirmation, setConfirmation] = useState('')
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState('')
  const [success, setSuccess] = useState('')
  async function submit(e: FormEvent) {
    e.preventDefault(); setBusy(true); setError('')
    try {
      const body = mode === 'forgot' ? { email } : mode === 'change'
        ? { current_password: current, password, password_confirmation: confirmation }
        : { email, token: params.get('reset_token'), password, password_confirmation: confirmation }
      const result = await api<{ message: string }>(mode === 'change' ? '/change-password' : mode === 'forgot' ? '/forgot-password' : '/reset-password', { method: 'POST', body })
      setPassword(''); setConfirmation(''); setCurrent(''); setSuccess(result.message)
    } catch (err) {
      setError(err instanceof ApiError && Object.keys(err.errors).length > 0 ? Object.values(err.errors).flat().join(' ') : err instanceof Error ? err.message : 'No se pudo completar la operación.')
    } finally { setBusy(false) }
  }
  return <main className="session-loading"><BrandMark /><section className="card" style={{ width: 'min(440px, 95vw)', padding: 24 }}>
    <h1>{mode === 'change' ? 'Cambia tu contraseña temporal' : mode === 'forgot' ? 'Recuperar contraseña' : 'Crear nueva contraseña'}</h1>
    {error && <p role="alert">{error}</p>}
    {success ? <><p role="status">{success}</p><button className="btn btn-primary" onClick={onDone}>Volver al inicio de sesión</button></> : <form onSubmit={submit} className="form-grid">
      {mode !== 'change' && <label>Correo<input required type="email" value={email} onChange={e => setEmail(e.target.value)} autoComplete="email" /></label>}
      {mode === 'change' && <label>Contraseña temporal<input required type="password" value={current} onChange={e => setCurrent(e.target.value)} autoComplete="current-password" /></label>}
      {mode !== 'forgot' && <><p>Usa al menos 12 caracteres, con mayúsculas, minúsculas y números.</p>
        <label>Nueva contraseña<input required type="password" minLength={12} value={password} onChange={e => setPassword(e.target.value)} autoComplete="new-password" /></label>
        <label>Confirmar contraseña<input required type="password" minLength={12} value={confirmation} onChange={e => setConfirmation(e.target.value)} autoComplete="new-password" /></label></>}
      <button disabled={busy} className="btn btn-primary">{busy ? 'Procesando…' : mode === 'forgot' ? 'Enviar enlace' : 'Guardar contraseña'}</button>
      <button type="button" disabled={busy} className="btn btn-outline" onClick={onDone}>{mode === 'change' ? 'Cerrar sesión' : 'Volver al inicio de sesión'}</button>
    </form>}
  </section></main>
}
