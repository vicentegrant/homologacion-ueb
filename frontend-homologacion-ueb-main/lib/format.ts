// Se utiliza la misma lista en los formularios del coordinador y del estudiante.
export const academicCycles = ['Primer', 'Segundo', 'Tercer', 'Cuarto', 'Quinto', 'Sexto', 'Séptimo', 'Octavo', 'Noveno', 'Décimo'].map(cycle => `${cycle} ciclo / semestre`)

export const estadoLabels: Record<string, string> = {
  pendiente: 'Pendiente',
  en_revision: 'En revisión',
  observado: 'Observado',
  en_proceso: 'Análisis académico',
  aprobado: 'Aprobado',
  en_consejo: 'En Consejo',
  listo: 'Finalizado',
  rechazado: 'Rechazado',
}

export const docEstadoLabels: Record<string, string> = {
  pendiente: 'Pendiente de entrega',
  presentado: 'Recibido',
  aprobado: 'Validado',
  observado: 'Observado',
}

export const conclusionLabels: Record<string, string> = {
  total: 'Homologación total',
  parcial: 'Homologación parcial',
  rechazada: 'Rechazada',
}

export function estadoLabel(estado?: string | null) {
  if (!estado) return 'Sin estado'
  return estadoLabels[estado] ?? docEstadoLabels[estado] ?? estado
}

export function estadoClass(estado?: string | null) {
  if (estado === 'aprobado' || estado === 'listo') return 'approved'
  if (estado === 'pendiente' || estado === 'en_revision' || estado === 'presentado') return 'received'
  if (estado === 'rechazado') return 'rejected'
  return ''
}

export function initials(name?: string | null) {
  if (!name) return '?'
  const parts = name.trim().split(/\s+/)
  return ((parts[0]?.[0] ?? '') + (parts.length > 2 ? parts[2][0] : parts[1]?.[0] ?? '')).toUpperCase()
}

export function firstName(name?: string | null) {
  return name?.trim().split(/\s+/)[0] ?? ''
}

export function formatDate(value?: string | null, withTime = false) {
  if (!value) return '—'
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return value
  return date.toLocaleDateString('es-EC', {
    day: '2-digit', month: 'short', year: 'numeric',
    ...(withTime ? { hour: '2-digit', minute: '2-digit' } : {}),
  })
}

export function todayLabel() {
  const text = new Date().toLocaleDateString('es-EC', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })
  return text.charAt(0).toUpperCase() + text.slice(1)
}

export function greeting() {
  const hour = new Date().getHours()
  if (hour < 12) return 'Buenos días'
  if (hour < 19) return 'Buenas tardes'
  return 'Buenas noches'
}
