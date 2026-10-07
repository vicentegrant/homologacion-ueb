import { test } from 'node:test'
import assert from 'node:assert/strict'
import { readFile } from 'node:fs/promises'
import ts from 'typescript'

// Ejecutar el cliente real con un servidor simulado, sin dependencias adicionales.
const source = (await readFile(new URL('./api.ts', import.meta.url), 'utf8'))
  .replace("'./request-cache'", JSON.stringify(new URL('./request-cache.ts', import.meta.url).href))
const compiled = ts.transpileModule(source, { compilerOptions: { module: ts.ModuleKind.ESNext, target: ts.ScriptTarget.ES2022 } }).outputText
const { api, setToken, clearToken, upload, onUnauthorized } = await import(`data:text/javascript;base64,${Buffer.from(compiled).toString('base64')}`)
const makeStorage = () => {
  const values = new Map()
  return { getItem: key => values.get(key) ?? null, setItem: (key, value) => values.set(key, value), removeItem: key => values.delete(key) }
}

test('cliente: deduplicación, escrituras, recarga explícita, archivos y cambio de sesión', async (t) => {
  const previousWindow = globalThis.window
  const previousFetch = globalThis.fetch
  globalThis.window = { sessionStorage: makeStorage(), localStorage: makeStorage() }
  t.after(() => { clearToken(); globalThis.window = previousWindow; globalThis.fetch = previousFetch })
  let calls = 0
  globalThis.fetch = async () => Response.json({ revision: ++calls })
  setToken('primera-sesion', false)
  await Promise.all([api('/catalog', { query: { b: 2, a: 1 } }), api('/catalog', { query: { a: 1, b: 2 } })])
  assert.equal(calls, 1)
  await api('/catalog', { query: { a: 1, b: 2 } })
  assert.equal(calls, 1)
  await api('/catalog', { method: 'POST', body: {} })
  await api('/catalog', { query: { a: 1, b: 2 } })
  assert.equal(calls, 3)
  await api('/catalog', { fresh: true, query: { a: 1, b: 2 } })
  assert.equal(calls, 4)
  await upload('/documents', { nombre: 'Documento' })
  await api('/catalog', { query: { a: 1, b: 2 } })
  assert.equal(calls, 6)
  setToken('segunda-sesion', false)
  await api('/catalog', { query: { a: 1, b: 2 } })
  assert.equal(calls, 7)
  await api('/me'); await api('/me')
  assert.equal(calls, 9)
})

test('un 401 de una sesión anterior no cierra la sesión nueva', async (t) => {
  const previousWindow = globalThis.window
  const previousFetch = globalThis.fetch
  globalThis.window = { sessionStorage: makeStorage(), localStorage: makeStorage() }
  t.after(() => { clearToken(); onUnauthorized(null); globalThis.window = previousWindow; globalThis.fetch = previousFetch })
  let finish
  let redirects = 0
  onUnauthorized(() => { redirects++ })
  globalThis.fetch = () => new Promise(resolve => { finish = resolve })
  setToken('anterior', false)
  const pending = api('/me')
  setToken('actual', false)
  finish(Response.json({ message: 'Sesión vencida' }, { status: 401 }))
  await assert.rejects(pending, { status: 401 })
  assert.equal(redirects, 0)
  const current = api('/me')
  finish(Response.json({ message: 'Sesión vencida' }, { status: 401 }))
  await assert.rejects(current, { status: 401 })
  assert.equal(redirects, 1)
})
