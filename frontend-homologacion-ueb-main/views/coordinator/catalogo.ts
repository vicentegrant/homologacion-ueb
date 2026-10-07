import { api } from '@/lib/api'

export type CoordinatorCatalogo = {
  carreras: { id: number; nombre: string }[]
  estados_solicitud: { id: number; nombre: string }[]
  tramites: { id: number; tipo_tramite: string; tipo_proceso: string }[]
  niveles_ciclo: string[]
  transiciones: Record<string, string[]>
}

export function getCoordinatorCatalogo(refresh = false) {
  return api<{ data: CoordinatorCatalogo }>('/coordinator/catalogo', { fresh: refresh }).then((r) => r.data)
}

export type Asignatura = { id: number; codigo_asignatura: string; nombre_asignatura: string; numero_creditos: number; nivel_ciclo: string; malla_curricular_id: number }
export type Malla = { id: number; nombre: string; tipo: 'institucional' | 'origen'; activa: boolean; carrera?: { id: number; nombre: string } | null; estudiante?: { id: number; nombres_completos: string } | null; asignaturas?: Asignatura[] }
