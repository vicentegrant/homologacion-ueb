import { RequestCache } from './request-cache'

/**
 * Cliente de la API de homologación (Laravel + Sanctum, tokens Bearer).
 * Contrato: backend/docs/api.md y backend/docs/frontend-integration.md
 */

const API_URL = (process.env.NEXT_PUBLIC_API_URL || 'http://127.0.0.1:8000/api/v1').replace(/\/$/, '')
const reads = new RequestCache()
export function invalidateApiCache() { reads.clear() }

const TOKEN_KEY = 'ueb_token'
const PENDING_USER_KEY = 'ueb_pending_password_user'

// ---------------------------------------------------------------------------
// Tipos del contrato
// ---------------------------------------------------------------------------

export type UserRole = 'administrador' | 'coordinador' | 'estudiante'

export type ApiUser = {
  tipo_identificacion?: string;
  must_change_password?: boolean;
  password_temporal?: boolean;
  password_changed_at?: string | null;
  id: number
  nombres_completos: string
  cedula: string | null
  email: string
  numero_celular: string | null
  cuenta_activa: boolean
  roles: UserRole[]
  carreras_coordinadas?: { id: number; nombre: string }[]
}

export type Paginated<T> = {
  success: boolean
  data: T[]
  links: { first: string | null; last: string | null; prev: string | null; next: string | null }
  meta: { current_page: number; last_page: number; per_page: number; total: number }
}

export type EstadoConteo = { estado: string; total: number }

/** Solicitud tal como la devuelven /admin y /coordinator (estado_actual es objeto). */
export type SolicitudResumen = {
  id: number
  procedencia_estudios: string | null
  carrera?: { id: number; nombre: string } | null
  estudiante?: { id: number; nombres_completos: string; cedula: string | null; email: string }
  coordinador?: { id: number; nombres_completos: string; email: string } | null
  estado_actual?: { id: number; nombre: string } | null
  created_at: string
}

/** Solicitud tal como la devuelve /student (estado_actual es texto). */
export type SolicitudEstudiante = {
  id: number
  procedencia_estudios: string | null
  carrera?: { id: number; nombre: string } | null
  coordinador?: { id: number; nombres_completos: string } | null
  tramite?: { id: number; tipo_tramite: string; tipo_proceso: string }
  estado_actual: string | null
  puede_editar: boolean
  puede_enviar: boolean
  created_at: string
}

export type ReporteSolicitudes = {
  success: boolean
  data: {
    filtros_aplicados: Record<string, unknown>
    total: number
    por_estado: EstadoConteo[]
    registros: SolicitudResumen[]
    paginacion: { current_page: number; last_page: number; per_page: number; total: number }
  }
}

export type AdminDashboard = {
  success: boolean
  data: {
    usuarios: { total: number; por_rol: { rol: string; total: number }[]; activos: number; inactivos: number }
    solicitudes: { total: number; por_estado: EstadoConteo[] }
  }
}

// ---------------------------------------------------------------------------
// Token
// ---------------------------------------------------------------------------

function storage(): Storage[] {
  if (typeof window === 'undefined') return []
  return [window.sessionStorage, window.localStorage]
}

export function getToken(): string | null {
  for (const s of storage()) {
    const value = s.getItem(TOKEN_KEY)
    if (value) return value
  }
  return null
}

/** remember=true lo conserva al cerrar el navegador (localStorage); si no, solo en la pestaña. */
export function setToken(token: string, remember: boolean) {
  clearToken()
  if (typeof window === 'undefined') return
  ;(remember ? window.localStorage : window.sessionStorage).setItem(TOKEN_KEY, token)
}

export function clearToken() {
  invalidateApiCache()
  for (const s of storage()) {
    s.removeItem(TOKEN_KEY)
    s.removeItem(PENDING_USER_KEY)
  }
}

/** Conserva la preferencia de sesión y sustituye el token revocado tras el cambio. */
export function replaceToken(token: string) {
  const remember = typeof window !== 'undefined' && !!window.localStorage.getItem(TOKEN_KEY)
  setToken(token, remember)
}

// Permite a la app reaccionar a un 401 en cualquier petición (volver al login).
let unauthorizedHandler: (() => void) | null = null
export function onUnauthorized(handler: (() => void) | null) {
  unauthorizedHandler = handler
}

// ---------------------------------------------------------------------------
// Errores
// ---------------------------------------------------------------------------

export class ApiError extends Error {
  status: number
  errors: Record<string, string[]>
  code?: string
  constructor(message: string, status: number, errors: Record<string, string[]> = {}, code?: string) {
    super(message)
    this.name = 'ApiError'
    this.status = status
    this.errors = errors
    this.code = code
  }
}

const fallbackMessages: Record<number, string> = {
  401: 'Tu sesión no es válida. Inicia sesión nuevamente.',
  403: 'No tienes permiso para esta acción o tu cuenta está inactiva.',
  404: 'El recurso solicitado no está disponible.',
  409: 'La solicitud cambió de estado. Actualiza e inténtalo de nuevo.',
  422: 'Revisa los datos ingresados.',
  429: 'Demasiadas peticiones. Espera un momento antes de reintentar.',
  500: 'Error interno del servidor. Informa al equipo de Backend.',
}

async function parseError(response: Response): Promise<ApiError> {
  const data = await response.json().catch(() => ({}))
  const message = data.message || fallbackMessages[response.status] || 'La operación falló.'
  return new ApiError(message, response.status, data.errors ?? {}, data.code)
}

// ---------------------------------------------------------------------------
// Petición base
// ---------------------------------------------------------------------------

type Query = Record<string, string | number | boolean | undefined | null>

type RequestOptions = {
  method?: 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE'
  body?: unknown
  query?: Query
  /** No redirigir al login ante un 401 (p. ej. credenciales incorrectas en /login). */
  skipAuthRedirect?: boolean
  fresh?: boolean
}

function buildUrl(path: string, query?: Query) {
  const url = new URL(`${API_URL}${path.startsWith('/') ? path : `/${path}`}`)
  if (query) {
    for (const [key, value] of Object.entries(query)) {
      if (value !== undefined && value !== null && value !== '') url.searchParams.set(key, String(value))
    }
  }
  url.searchParams.sort()
  return url.toString()
}

function authHeaders(): Record<string, string> {
  const headers: Record<string, string> = { Accept: 'application/json' }
  const token = getToken()
  if (token) headers.Authorization = `Bearer ${token}`
  return headers
}

async function handleFailure(response: Response, skipAuthRedirect?: boolean, requestToken = getToken()): Promise<never> {
  const error = await parseError(response)
  if (response.status === 401 && !skipAuthRedirect && requestToken === getToken()) {
    clearToken()
    unauthorizedHandler?.()
  }
  throw error
}

export async function api<T = unknown>(path: string, options: RequestOptions = {}): Promise<T> {
  const { method = 'GET', body, query, skipAuthRedirect, fresh } = options
  const token = getToken()
  const url = buildUrl(path, query)
  const headers = authHeaders()
  if (body !== undefined) headers['Content-Type'] = 'application/json'
  const request = async (): Promise<T> => {
    let response: Response
    try {
      response = await fetch(url, { method, headers, cache: 'no-store', body: body === undefined ? undefined : JSON.stringify(body) })
    } catch {
      throw new ApiError('No se pudo conectar con la API. Verifica que el backend est? encendido.', 0)
    }
    if (!response.ok) return handleFailure(response, skipAuthRedirect, token)
    if (method !== 'GET') invalidateApiCache()
    return response.status === 204 ? undefined as T : await response.json() as T
  }
  // Verificar autenticaci?n con el servidor; conservar lecturas solo en esta pesta?a.
  const cacheable = method === 'GET' && !!token && !skipAuthRedirect && !/^\/(?:me|auth|login|logout)(?:[/?]|$)/.test(path)
  if (fresh) invalidateApiCache()
  return cacheable ? reads.read(token + ':' + url, request) : request()
}

// ---------------------------------------------------------------------------
// Archivos
// ---------------------------------------------------------------------------

/** Sube un PDF en el campo indicado (multipart). No fijar Content-Type: lo pone el navegador. */
export async function upload<T = unknown>(path: string, fields: Record<string, string | Blob>): Promise<T> {
  const token = getToken()
  const form = new FormData()
  for (const [key, value] of Object.entries(fields)) form.append(key, value)
  const response = await fetch(buildUrl(path), { method: 'POST', headers: authHeaders(), body: form })
  if (!response.ok) return handleFailure(response, false, token)
  invalidateApiCache()
  return (await response.json()) as T
}

/**
 * Descarga un archivo protegido. Acepta un download_url relativo del backend
 * ("/api/v1/...") o una ruta relativa a la API ("/student/...").
 */
export async function download(pathOrUrl: string, filename = 'documento.pdf') {
  const origin = new URL(API_URL).origin
  const url = pathOrUrl.startsWith('/api/') ? new URL(pathOrUrl, origin).toString() : buildUrl(pathOrUrl)
  if (new URL(url).origin !== origin) throw new ApiError('Destino de descarga no permitido.', 0)

  const response = await fetch(url, { headers: authHeaders() })
  if (!response.ok) return handleFailure(response)

  const blob = await response.blob()
  const objectUrl = URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = objectUrl
  link.download = filename
  document.body.appendChild(link)
  link.click()
  link.remove()
  URL.revokeObjectURL(objectUrl)
}

// ---------------------------------------------------------------------------
// Autenticación
// ---------------------------------------------------------------------------

export async function loginRequest(email: string, password: string, remember = false) {
  const data = await api<{ success: boolean; user: ApiUser; token: string; token_type: string }>('/login', {
    method: 'POST',
    body: { email, password },
    skipAuthRedirect: true,
  })
  setToken(data.token, remember)
  // Este dato solo restaura el formulario obligatorio; los permisos se validan en el backend.
  if (typeof window !== 'undefined' && (data.user.must_change_password || data.user.password_temporal)) {
    ;(remember ? window.localStorage : window.sessionStorage).setItem(PENDING_USER_KEY, JSON.stringify(data.user))
  }
  return data.user
}

export async function getCurrentUser() {
  try {
    const data = await api<{ success: boolean; user: ApiUser }>('/me')
    for (const s of storage()) s.removeItem(PENDING_USER_KEY)
    return data.user
  } catch (error) {
    // /me también está bloqueado hasta cambiar la contraseña; nunca se abre el panel desde la caché.
    if (error instanceof ApiError && error.code === 'PASSWORD_CHANGE_REQUIRED') {
      for (const s of storage()) {
        const cached = s.getItem(PENDING_USER_KEY)
        if (cached) {
          try {
            const pending = JSON.parse(cached) as ApiUser
            return { ...pending, must_change_password: true, password_temporal: true }
          } catch { s.removeItem(PENDING_USER_KEY) }
        }
      }
    }
    throw error
  }
}

export async function logoutRequest() {
  try {
    if (getToken()) await api('/logout', { method: 'POST', skipAuthRedirect: true })
  } finally {
    clearToken()
  }
}

export function primaryRole(user: ApiUser): UserRole {
  if (user.roles.includes('administrador')) return 'administrador'
  if (user.roles.includes('coordinador')) return 'coordinador'
  return 'estudiante'
}

export function getApiUrl() {
  return API_URL
}

// ---------------------------------------------------------------------------
// Endpoints usados por el panel
// ---------------------------------------------------------------------------

export const adminApi = {
  dashboard: () => api<AdminDashboard>('/admin/dashboard'),
  reporteSolicitudes: (query: Query = {}) => api<ReporteSolicitudes>('/admin/reports/solicitudes', { query }),
}

export const coordinatorApi = {
  reporteSolicitudes: (query: Query = {}) => api<ReporteSolicitudes>('/coordinator/reports/solicitudes', { query }),
}

export const studentApi = {
  solicitudes: (query: Query = {}) => api<Paginated<SolicitudEstudiante>>('/student/solicitudes', { query }),
  notificaciones: (query: Query = {}) =>
    api<Paginated<{ id: string; read_at: string | null }> & { sin_leer: number }>('/student/notificaciones', { query }),
}
