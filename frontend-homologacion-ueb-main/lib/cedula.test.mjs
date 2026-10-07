import { test } from 'node:test'
import assert from 'node:assert/strict'
import { cedulaEcuatorianaError, identificationError } from './cedula.ts'

test('la identificación aplica reglas distintas para cédula y pasaporte', () => {
  assert.equal(identificationError('pasaporte', 'AB12345'), null)
  assert.equal(identificationError('cedula', '0100000009'), null)
  assert.match(identificationError('cedula', 'AB12345'), /10 dígitos/)
  for (const value of ['AB12', 'A'.repeat(21), 'AB 123', 'AB-123', 'ABCDE\n']) {
    assert.match(identificationError('pasaporte', value), /pasaporte/)
  }
  assert.equal(identificationError('pasaporte', ''), 'Ingrese el número de pasaporte.')
})

test('cédulas válidas, provincia exterior y conservación de ceros iniciales', () => {
  for (const cedula of ['0100000009', '0200000008', '0300000007', '3000000004', '0100000058', '0100000017']) assert.equal(cedulaEcuatorianaError(cedula), null, cedula)
})

test('provincia, tercer dígito, verificador, longitud, letras y espacios inválidos', () => {
  for (const [value, message] of [['0000000000', 'provincia'], ['2500000000', 'provincia'], ['3100000000', 'provincia'], ['0160000000', 'tercer dígito'], ['0190000000', 'tercer dígito'], ['0100000000', 'verificador'], ['010000000', '10 dígitos'], ['01000000099', '10 dígitos'], ['01000000A9', '10 dígitos'], ['0100000009\n', '10 dígitos']]) assert.ok(cedulaEcuatorianaError(value)?.includes(message), value)
})
