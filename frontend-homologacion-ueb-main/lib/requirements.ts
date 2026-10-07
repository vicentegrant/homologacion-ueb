export type Requirement = {
  id: number; nombre: string; descripcion: string | null; activa: boolean; obligatorio: boolean
  carrera_id: number | null; tramite_proceso_id: number
  origen_configuracion?: string
}

export type Procedure = { id: number; tipo_tramite: string; tipo_proceso: string }

// Etiquetas comunes para evitar mostrar códigos internos en los formularios.
export function procedureLabel(procedure: Procedure) {
  const labels: Record<string, string> = {
    malla_a_malla: 'Reconocimiento de malla a malla',
    carreras_facultad: 'Homologación entre carreras de la UEB',
    otra_universidad: 'Homologación desde otra institución',
  }
  return labels[procedure.tipo_proceso] ?? `${procedure.tipo_tramite} · ${procedure.tipo_proceso}`
}

type Named = string | { nombre: string }
const rawValue = (v: Named) => (typeof v === 'string' ? v : v.nombre)
const humanize = (v: string) => {
  const t = v.replaceAll('_', ' ').trim()
  return t.charAt(0).toUpperCase() + t.slice(1)
}

// Convierte los códigos del backend en un texto legible para el usuario.
export function tramiteLabel(tramite: { tipo_tramite: Named; tipo_proceso: Named }) {
  const proceso = rawValue(tramite.tipo_proceso)
  const tipo = rawValue(tramite.tipo_tramite)
  const procesos: Record<string, string> = {
    malla_a_malla: 'Reconocimiento de malla a malla',
    carreras_facultad: 'Homologación entre carreras de la UEB',
    otra_universidad: 'Homologación desde otra institución',
  }
  const tipos: Record<string, string> = { homologacion: 'Homologación', reconocimiento: 'Reconocimiento' }
  return procesos[proceso] ?? `${tipos[tipo] ?? humanize(tipo)} · ${humanize(proceso)}`
}

export function requirementsFor(requirements: Requirement[], careerId: number, procedureId: number) {
  return requirements.filter(requirement => requirement.activa && requirement.tramite_proceso_id === procedureId && (requirement.carrera_id === null || requirement.carrera_id === careerId))
}
