export type Requirement = {
  id: number; nombre: string; descripcion: string | null; activa: boolean; obligatorio: boolean
  carrera_id: number | null; tramite_proceso_id: number
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

export function requirementsFor(requirements: Requirement[], careerId: number, procedureId: number) {
  return requirements.filter(requirement => requirement.activa && requirement.tramite_proceso_id === procedureId && (requirement.carrera_id === null || requirement.carrera_id === careerId))
}
