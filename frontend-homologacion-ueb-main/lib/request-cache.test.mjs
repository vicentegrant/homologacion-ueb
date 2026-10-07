import { test } from 'node:test'
import assert from 'node:assert/strict'
import { RequestCache } from './request-cache.ts'

test('comparte peticiones simultáneas y lecturas recientes', async () => {
  const cache = new RequestCache()
  let calls = 0
  const load = async () => { calls++; return { id: 1 } }
  const [first, second] = await Promise.all([cache.read('session:catalog', load), cache.read('session:catalog', load)])
  assert.equal(first, second)
  await cache.read('session:catalog', load)
  assert.equal(calls, 1)
})

test('separa usuarios y vuelve a consultar lecturas vencidas', async () => {
  const cache = new RequestCache()
  let calls = 0
  const load = async () => ++calls
  assert.equal(await cache.read('user-a:catalog', load, 0), 1)
  assert.equal(await cache.read('user-a:catalog', load, 0), 2)
  assert.equal(await cache.read('user-b:catalog', load), 3)
})

test('una escritura o cierre de sesión descarta lecturas anteriores pendientes', async () => {
  const cache = new RequestCache()
  let finish
  const old = cache.read('catalog', () => new Promise(resolve => { finish = resolve }))
  await Promise.resolve()
  cache.clear()
  assert.equal(await cache.read('catalog', async () => 'nuevo'), 'nuevo')
  finish('anterior')
  await old
  assert.equal(await cache.read('catalog', async () => 'incorrecto'), 'nuevo')
  cache.clear()
  assert.equal(await cache.read('catalog', async () => 'otra sesión'), 'otra sesión')
})

test('los errores no se conservan y permiten reintentar', async () => {
  const cache = new RequestCache()
  await assert.rejects(cache.read('catalog', async () => { throw new Error('sin conexión') }))
  assert.equal(await cache.read('catalog', async () => 'recuperado'), 'recuperado')
})
