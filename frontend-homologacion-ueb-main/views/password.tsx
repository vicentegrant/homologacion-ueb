'use client'

import { FormEvent, useId, useState } from 'react'
import { ArrowLeft, ArrowRight, Check, CheckCircle2, Circle, Eye, EyeOff, KeyRound, LoaderCircle, LockKeyhole, LogOut, Mail, ShieldCheck, TriangleAlert } from 'lucide-react'
import { api, ApiError, ApiUser, getCurrentUser, replaceToken } from '@/lib/api'
import { BrandMark } from '@/components/app/brand'

function PasswordField({ label, value, onChange, autoComplete, hint, placeholder, disabled, invalid = false }: {
  label: string; value: string; onChange: (value: string) => void;
  autoComplete: 'current-password' | 'new-password'; hint?: string; placeholder?: string; disabled: boolean; invalid?: boolean;
}) {
  const id = useId()
  const [visible, setVisible] = useState(false)
  return <div className="password-control">
    <label htmlFor={id}>{label}</label>
    <div className={`password-input-wrap${invalid ? ' is-invalid' : ''}`}>
      <LockKeyhole size={18} aria-hidden="true" />
      <input id={id} required type={visible ? 'text' : 'password'} value={value}
        onChange={event => onChange(event.target.value)} autoComplete={autoComplete}
        disabled={disabled} aria-invalid={invalid || undefined} aria-describedby={hint ? `${id}-hint` : undefined}
        placeholder={placeholder ?? (autoComplete === 'current-password' ? 'Ingresa tu contraseña actual' : 'Escribe tu nueva contraseña')} />
      <button type="button" className="password-visibility" disabled={disabled}
        onClick={() => setVisible(!visible)} aria-label={`${visible ? 'Ocultar' : 'Mostrar'} ${label.toLowerCase()}`} aria-pressed={visible}>
        {visible ? <EyeOff size={18} /> : <Eye size={18} />}
      </button>
    </div>
    {hint && <p id={`${id}-hint`} aria-live="polite" className={invalid ? 'password-field-feedback is-invalid' : 'password-field-feedback'}>{hint}</p>}
  </div>
}

export function PasswordView({ mode, onDone, loginEmail, onChanged }: {
  mode: 'change' | 'forgot' | 'reset'; onDone: () => void; loginEmail?: string; onChanged?: (user: ApiUser) => void;
}) {
  const params = typeof window !== 'undefined' ? new URLSearchParams(window.location.search) : new URLSearchParams()
  const emailId = useId()
  const [email, setEmail] = useState(params.get('email') ?? '')
  const [current, setCurrent] = useState('')
  const [password, setPassword] = useState('')
  const [confirmation, setConfirmation] = useState('')
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState('')
  const [success, setSuccess] = useState('')
  const isChange = mode === 'change'
  const isForgot = mode === 'forgot'
  const accountEmail = isChange ? loginEmail : email
  const requirements = [
    { label: '8 caracteres como mínimo', met: password.length >= 8 },
    { label: 'Una letra mayúscula', met: /[A-Z]/.test(password) },
    { label: 'Una letra minúscula', met: /[a-z]/.test(password) },
    { label: 'Un número', met: /[0-9]/.test(password) },
    { label: 'Un símbolo, como ! o @', met: /[^a-zA-Z0-9]/.test(password) },
  ]
  const metCount = requirements.filter(requirement => requirement.met).length
  const confirmationMatches = !!confirmation && confirmation === password
  const title = isChange ? 'Cambia tu contraseña' : isForgot ? 'Recupera tu acceso' : 'Crea tu nueva contraseña'
  const subtitle = isChange
    ? 'Antes de continuar, crea una contraseña personal para tu cuenta.'
    : isForgot ? 'Te enviaremos un enlace para que puedas volver a ingresar.'
      : 'Elige una contraseña personal para volver a acceder a tu cuenta.'

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setBusy(true)
    setError('')
    try {
      if (!isForgot) {
        if (password !== confirmation) throw new Error('La confirmación de contraseña no coincide.')
        if (isChange && password === current) throw new Error('La nueva contraseña debe ser distinta a la actual.')
        if (metCount !== requirements.length) throw new Error('Completa los cinco requisitos de la nueva contraseña.')
      }
      const body = isForgot ? { email } : isChange
        ? { current_password: current, password, password_confirmation: confirmation }
        : { email, token: params.get('token') ?? params.get('reset_token'), password, password_confirmation: confirmation }
      const result = await api<{ message: string; token?: string }>(isChange ? '/change-password' : isForgot ? '/forgot-password' : '/reset-password', { method: 'POST', body })
      setSuccess(result.message)
      if (isChange && result.token) {
        // Se conserva la rotación del token y el acceso directo al panel del rol.
        replaceToken(result.token)
      }
      if (isChange && onChanged) {
        try { onChanged(await getCurrentUser()) }
        catch { setError('La contraseña se cambió. Vuelve a iniciar sesión con tu nueva contraseña.') }
      }
      setPassword('')
      setConfirmation('')
      setCurrent('')
    } catch (err) {
      setError(err instanceof ApiError && Object.keys(err.errors).length > 0
        ? Object.values(err.errors).flat().join(' ')
        : err instanceof Error ? err.message : 'No se pudo completar la operación.')
    } finally { setBusy(false) }
  }

  return <main className="login-shell password-shell">
    <aside className="login-visual password-visual" aria-label="Portal de homologación UEB">
      <div className="login-visual-image" />
      <div className="image-tint" />
      <div className="mountain-badge"><ShieldCheck size={16} aria-hidden="true" /> Plataforma segura UEB</div>
      <div className="visual-copy">
        <div className="eyebrow"><span /> PORTAL DE HOMOLOGACIÓN</div>
        <h2>Tu siguiente paso,<br /><em>con confianza.</em></h2>
        <p>Protege tu cuenta y continúa tu trayectoria académica con el respaldo de la Universidad Estatal de Bolívar.</p>
        <div className="password-visual-note"><ShieldCheck size={22} aria-hidden="true" /><div><strong>Un acceso personal y seguro</strong><span>Tu contraseña protege tus datos y solicitudes.</span></div></div>
        <div className="visual-footer"><span className="flag-dot blue-dot" /><span className="flag-dot white-dot" /><span className="flag-dot red-dot" /> Guaranda · Ecuador</div>
      </div>
    </aside>

    <section className="login-panel password-panel" aria-labelledby="password-title">
      <div className="login-top"><BrandMark /><span className="password-top-label"><ShieldCheck size={14} aria-hidden="true" /> Tu cuenta</span></div>
      <div className="password-content">
        <div className="password-heading-icon" aria-hidden="true">{isForgot ? <Mail size={25} /> : <KeyRound size={25} />}</div>
        <p className="section-kicker">{isChange ? 'PRIMER INGRESO' : 'SEGURIDAD DE TU CUENTA'}</p>
        <h1 id="password-title">{title}</h1>
        <p className="password-subtitle">{subtitle}</p>
        {isChange && accountEmail && <div className="password-account"><Mail size={16} aria-hidden="true" /><span>{accountEmail}</span><span className="password-account-badge">Tu cuenta</span></div>}

        {error && <div className="password-alert" role="alert"><TriangleAlert size={18} aria-hidden="true" /><p>{error}</p></div>}
        {success ? <div className="password-success" role="status">
          <div className="password-success-icon"><CheckCircle2 size={30} aria-hidden="true" /></div>
          <h2>{isForgot ? 'Revisa tu correo' : 'Contraseña actualizada'}</h2>
          <p>{isForgot ? 'Si el correo corresponde a una cuenta activa, recibirás un enlace para recuperar tu acceso. Revisa también la carpeta de spam.' : success}</p>
          <button className="primary-button" type="button" disabled={busy} onClick={onDone}>Volver al inicio de sesión <ArrowRight size={17} aria-hidden="true" /></button>
        </div> : <form className="password-form" onSubmit={submit} aria-busy={busy}>
          {!isChange && <div className="password-control">
            <label htmlFor={emailId}>Correo electrónico</label>
            <div className="password-input-wrap"><Mail size={18} aria-hidden="true" /><input id={emailId} required type="email" value={email} onChange={event => { setEmail(event.target.value); setError('') }} autoComplete="email" placeholder="nombre@correo.com" disabled={busy} /></div>
          </div>}
          {isChange && <PasswordField label="Contraseña actual" value={current} onChange={value => { setCurrent(value); setError('') }} autoComplete="current-password" hint="En tu primer ingreso, utiliza tu número de identificación." disabled={busy} />}
          {!isForgot && <>
            <PasswordField label="Nueva contraseña" value={password} onChange={value => { setPassword(value); setError('') }} autoComplete="new-password" disabled={busy} />
            <div className="password-requirements" aria-label="Requisitos de la nueva contraseña">
              <div className="password-requirements-heading"><span>Tu nueva contraseña debe incluir</span><span>{metCount}/5</span></div>
              <div className="password-progress" role="progressbar" aria-label="Requisitos cumplidos" aria-valuemin={0} aria-valuemax={5} aria-valuenow={metCount}><span style={{ width: `${metCount * 20}%` }} /></div>
              <ul>{requirements.map(requirement => <li key={requirement.label} className={requirement.met ? 'is-met' : ''}>
                {requirement.met ? <Check size={14} aria-hidden="true" /> : <Circle size={11} aria-hidden="true" />}<span>{requirement.label}</span><span className="sr-only">{requirement.met ? ': cumplido' : ': pendiente'}</span>
              </li>)}</ul>
            </div>
            <PasswordField label="Confirmar nueva contraseña" value={confirmation} onChange={value => { setConfirmation(value); setError('') }} autoComplete="new-password" placeholder="Escribe nuevamente tu contraseña" disabled={busy}
              invalid={!!confirmation && !confirmationMatches} hint={confirmation ? (confirmationMatches ? 'Las contraseñas coinciden.' : 'Las contraseñas todavía no coinciden.') : undefined} />
            <p className="password-personal-note"><ShieldCheck size={15} aria-hidden="true" /> Usa una contraseña distinta a tu identificación y a la actual.</p>
          </>}
          <button disabled={busy} className="primary-button" type="submit">
            {busy ? <><LoaderCircle size={18} className="password-spinner" aria-hidden="true" /> {isForgot ? 'Enviando enlace…' : 'Guardando cambios…'}</>
              : <>{isForgot ? 'Enviar enlace de recuperación' : isChange ? 'Guardar y continuar' : 'Guardar nueva contraseña'} <ArrowRight size={17} aria-hidden="true" /></>}
          </button>
          <button type="button" disabled={busy} className="password-back-button" onClick={onDone}>
            {isChange ? <LogOut size={16} aria-hidden="true" /> : <ArrowLeft size={16} aria-hidden="true" />}{isChange ? 'Cerrar sesión' : 'Volver al inicio de sesión'}
          </button>
        </form>}
      </div>
      <footer className="password-footer"><LockKeyhole size={13} aria-hidden="true" /><span>Sistema de Reconocimiento y Homologación · UEB</span></footer>
    </section>
  </main>
}
