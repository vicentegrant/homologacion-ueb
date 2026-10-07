// Misma regla módulo 10 que el backend; este siempre vuelve a validar.
export function cedulaEcuatorianaError(value: string): string | null {
  if (value.length !== 10 || !/^[0-9]{10}$/.test(value)) return 'La cédula debe contener exactamente 10 dígitos numéricos, sin letras ni espacios.'
  const province = Number(value.slice(0, 2))
  if (!((province >= 1 && province <= 24) || province === 30)) return 'La cédula debe tener un código de provincia entre 01 y 24, o 30 para registros en el exterior.'
  if (Number(value[2]) > 5) return 'El tercer dígito de la cédula debe estar entre 0 y 5.'
  const sum = [...value.slice(0, 9)].reduce((total, digit, index) => {
    const product = Number(digit) * (index % 2 === 0 ? 2 : 1)
    return total + (product > 9 ? product - 9 : product)
  }, 0)
  return (10 - sum % 10) % 10 === Number(value[9]) ? null : 'El dígito verificador de la cédula es incorrecto. Revise el número ingresado.'
}
export function identificationError(type: string, value: string): string | null {
  if (!value) return type === 'pasaporte' ? 'Ingrese el número de pasaporte.' : 'Ingrese el número de cédula.'
  if (type === 'cedula') return cedulaEcuatorianaError(value)
  return value.length >= 5 && value.length <= 20 && !/[^A-Z0-9]/.test(value) ? null : 'El pasaporte debe contener entre 5 y 20 letras o números, sin espacios ni símbolos.'
}
