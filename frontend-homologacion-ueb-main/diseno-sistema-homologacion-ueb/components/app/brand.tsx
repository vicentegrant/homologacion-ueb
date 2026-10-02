import { GraduationCap } from 'lucide-react'

export function BrandMark({ compact = false }: { compact?: boolean }) {
  return (
    <div className="brand-mark">
      <div className="brand-shield" aria-hidden="true"><GraduationCap size={20} strokeWidth={2.2} /></div>
      {!compact && <div><strong>UEB</strong><span>Universidad Estatal de Bolívar</span></div>}
    </div>
  )
}
