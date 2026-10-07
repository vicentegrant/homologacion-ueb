import { test } from 'node:test'
import assert from 'node:assert/strict'
import { access, mkdtemp, readFile, rm, writeFile } from 'node:fs/promises'
import { spawn } from 'node:child_process'
import { tmpdir } from 'node:os'
import { dirname, join, resolve } from 'node:path'
import { once } from 'node:events'

const pause = ms => new Promise(resolve => setTimeout(resolve, ms))

// Probar la interfaz compilada con API simulada sin modificar cuentas ni datos reales.
test('roles: requisitos PDF, mallas, comparación automática y solicitudes de solo lectura', { timeout: 60000 }, async () => {
  const base = process.env.UEB_TEST_FRONTEND_URL ?? 'http://localhost:3000'
  const binaries = [process.env.CHROME_BIN, 'C:/Program Files/Google/Chrome/Application/chrome.exe', 'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe'].filter(Boolean)
  let executable
  for (const path of binaries) {
    if (await access(path).then(() => true, () => false)) { executable = path; break }
  }
  assert.ok(executable, 'Instala Chrome/Edge o indica CHROME_BIN para esta prueba de navegador.')
  const profile = await mkdtemp(join(tmpdir(), 'ueb-ui-'))
  const browser = spawn(executable, ['--headless=new', '--disable-gpu', '--no-first-run', '--no-default-browser-check', '--remote-debugging-port=0', `--user-data-dir=${profile}`, 'about:blank'], { windowsHide: true, stdio: 'ignore' })
  let socket
  try {
    let port
    for (let i = 0; i < 100; i++) {
      port = await readFile(join(profile, 'DevToolsActivePort'), 'utf8').then(value => value.split('\n')[0], () => null)
      if (port) break
      await pause(100)
    }
    assert.ok(port, 'El navegador no inició su conexión de pruebas.')
    const targets = await fetch(`http://127.0.0.1:${port}/json/list`).then(response => response.json())
    socket = new WebSocket(targets.find(target => target.type === 'page').webSocketDebuggerUrl)
    await new Promise((resolve, reject) => { socket.onopen = resolve; socket.onerror = reject })
    let id = 0
    const waiting = new Map()
    socket.onmessage = event => {
      const response = JSON.parse(event.data)
      const pending = waiting.get(response.id)
      if (!pending) return
      waiting.delete(response.id)
      clearTimeout(pending.timer)
      if (response.error) pending.reject(new Error(response.error.message))
      else pending.resolve(response.result)
    }
    const send = (method, params = {}) => new Promise((resolve, reject) => {
      const request = ++id
      const timer = setTimeout(() => { waiting.delete(request); reject(new Error(`Sin respuesta: ${method}`)) }, 10000)
      waiting.set(request, { resolve, reject, timer })
      socket.send(JSON.stringify({ id: request, method, params }))
    })
    const evaluate = async expression => {
      const result = await send('Runtime.evaluate', { expression, returnByValue: true, awaitPromise: true })
      if (result.exceptionDetails) throw new Error(result.exceptionDetails.text)
      return result.result.value
    }
    const until = async expression => {
      for (let i = 0; i < 100; i++) {
        if (await evaluate(`Boolean(${expression})`)) return
        await pause(100)
      }
      throw new Error(`La interfaz no mostró el estado esperado: ${expression}. Pantalla: ${await evaluate('document.body.innerText.slice(0, 1000)')}`)
    }
    await send('Page.enable')
    await send('Emulation.setDeviceMetricsOverride', { width: 1440, height: 1100, deviceScaleFactor: 1, mobile: false })
    await send('Page.addScriptToEvaluateOnNewDocument', { source: `
      if (location.origin === ${JSON.stringify(new URL(base).origin)}) {
        sessionStorage.setItem('ueb_token', 'token-simulado-de-prueba');
        const originalFetch = window.fetch.bind(window);
        const role = new URL(location.href).searchParams.get('test_role') ?? 'coordinador';
        const user = { id: 1, nombres_completos: 'Usuario de prueba', email: 'usuario@example.test', cedula: '0100000009', cuenta_activa: true, roles: [role], carreras_coordinadas: [{ id: 1, nombre: 'Software' }] };
        const requirement = { id: 10, nombre: 'Certificado de notas', descripcion: 'Usar el formato adjunto.', carrera_id: 1, tramite_proceso_id: 1, activa: true, obligatorio: true };
        const catalog = { carreras: [{ id: 1, nombre: 'Software', activa: true }], tramites: [{ id: 1, tipo_tramite: 'homologacion', tipo_proceso: 'otra_universidad' }], requisitos: [], generales: [], niveles_ciclo: ['primero'] };
        const curriculum = { id: 1, nombre: 'Malla de prueba', tipo: 'institucional', activa: true, carrera: { id: 1, nombre: 'Software' }, asignaturas: [{ id: 1, codigo_asignatura: 'MAT-1', nombre_asignatura: 'Matemática', nivel_ciclo: 'primero', numero_creditos: 4 }] };
        const origin = { ...curriculum, id: 2, nombre: 'Malla de origen', tipo: 'origen', estudiante: { id: 3, nombres_completos: 'Estudiante de prueba' } };
        const solicitud = { id: 40, estudiante: { id: 3, nombres_completos: 'Estudiante de prueba', cedula: '0200000008', email: 'estudiante@example.test' }, carrera: { id: 1, nombre: 'Software' }, procedencia_estudios: 'Universidad de Cuenca', estado_actual: { nombre: 'en_proceso' }, resultado: null, historial_estados: [], created_at: '2026-10-01T00:00:00Z' };
        const paginated = data => ({ data, meta: { current_page: 1, last_page: 1, total: data.length, per_page: 100 } });
        window.fetch = async (url, options = {}) => {
          const parsed = new URL(typeof url === 'string' ? url : url.url, location.origin);
          if (!parsed.pathname.includes('/api/v1/')) return originalFetch(url, options);
          const path = parsed.pathname.split('/api/v1')[1];
          if (path === '/me') return Response.json({ success: true, user });
          if (path === '/coordinator/catalogo' || path === '/coordinator/requirements' && options.method === 'GET') return Response.json({ data: catalog });
          if (path === '/coordinator/requirements' && options.method === 'POST') {
            const form = options.body;
            window.__uploaded = form instanceof FormData ? { filename: form.get('ejemplo')?.name, size: form.get('ejemplo')?.size, career: form.get('carrera_id'), procedure: form.get('tramite_proceso_id') } : null;
            requirement.ejemplo_download_url = '/api/v1/coordinator/requirements/10/example';
            catalog.requisitos = [requirement];
            return Response.json({ success: true, data: requirement }, { status: 201 });
          }
          if (path === '/coordinator/curricula/1') return Response.json({ data: curriculum });
          if (path === '/coordinator/curricula') return Response.json(paginated(parsed.searchParams.get('tipo') === 'origen' ? [origin] : [curriculum]));
          if (path === '/coordinator/solicitudes/40') return Response.json({ data: solicitud });
          if (path === '/coordinator/solicitudes/40/documents' || path === '/coordinator/solicitudes/40/comparisons') return Response.json({ data: [] });
          if (path === '/coordinator/solicitudes/40/compare-curricula') {
            window.__compare = JSON.parse(options.body);
            solicitud.estado_actual = { nombre: 'revisado' };
            solicitud.resultado = { conclusion_general: 'total', total_creditos_reconocidos: 4, total_creditos_destino: 4, porcentaje_cobertura: 100, informe_tecnico_disponible: false };
            return Response.json({ data: solicitud.resultado }, { status: 201 });
          }
          if (path === '/coordinator/solicitudes/40/technical-report' && options.method === 'POST') {
            solicitud.estado_actual = { nombre: 'terminado' };
            solicitud.resultado.informe_tecnico_disponible = true;
            return Response.json({ data: solicitud.resultado }, { status: 201 });
          }
          if (path === '/coordinator/curricula/1/subjects' && options.method === 'POST') {
            window.__subject = JSON.parse(options.body);
            curriculum.asignaturas.push({ id: 2, ...window.__subject });
            return Response.json({ success: true, data: curriculum.asignaturas.at(-1) }, { status: 201 });
          }
          if (path === '/coordinator/reports/solicitudes') return Response.json({ data: { total: 0, por_estado: [], registros: [] } });
          if (path === '/admin/reports/solicitudes') return Response.json({ data: { total: 0, por_estado: [], registros: [] } });
          if (path === '/admin/dashboard') return Response.json({ data: { usuarios: { activos: 3 } } });
          if (path === '/student/solicitudes') return Response.json(paginated([{ ...solicitud, estado_actual: 'terminado' }]));
          if (path === '/student/solicitudes/40') return Response.json({ data: { ...solicitud, estado_actual: 'terminado', puede_editar: false, puede_editar_procedencia: false } });
          if (path === '/student/notificaciones') return Response.json({ ...paginated([]), sin_leer: 0 });
          return Response.json({ data: [] });
        };
      }
    ` })
    await send('Page.navigate', { url: `${base}/#/requisitos` })
    await until(`document.querySelector('select option[value="1"]')`)
    await evaluate(`document.querySelector('select').value = '1'; document.querySelector('select').dispatchEvent(new Event('change', { bubbles: true })); document.querySelectorAll('select')[1].value = '1'; document.querySelectorAll('select')[1].dispatchEvent(new Event('change', { bubbles: true }));`)
    await until(`[...document.querySelectorAll('button')].some(b => b.textContent.includes('Nuevo requisito'))`)
    await evaluate(`[...document.querySelectorAll('button')].find(b => b.textContent.includes('Nuevo requisito')).click()`)
    await until(`document.querySelector('input[type="file"]')`)
    const visible = await evaluate(`(() => { const input = document.querySelector('input[type="file"]'); const bounds = input.getBoundingClientRect(); return bounds.width > 100 && bounds.height > 20 && getComputedStyle(input).display !== 'none' && !input.disabled })()`)
    assert.equal(visible, true, 'El selector PDF debe estar visible y habilitado.')
    assert.ok(await evaluate(`document.body.textContent.includes('Adjuntar PDF de ejemplo')`))
    const pdf = join(profile, 'ejemplo.pdf')
    await writeFile(pdf, '%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF\n')
    const dom = await send('DOM.getDocument')
    const fileInput = await send('DOM.querySelector', { nodeId: dom.root.nodeId, selector: 'input[type="file"]' })
    await send('DOM.setFileInputFiles', { nodeId: fileInput.nodeId, files: [pdf] })
    await evaluate(`(() => { const input = document.querySelector('input[placeholder="Ej.: Certificado de notas"]'); Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value').set.call(input, 'Certificado de notas'); input.dispatchEvent(new Event('input', { bubbles: true })); input.dispatchEvent(new Event('change', { bubbles: true })); })()`)
    const screenshot = process.env.UEB_UI_SCREENSHOT
    if (screenshot) {
      const result = await send('Page.captureScreenshot', { format: 'png', captureBeyondViewport: true })
      await writeFile(resolve(screenshot), Buffer.from(result.data, 'base64'))
    }
    await evaluate(`[...document.querySelectorAll('button')].find(b => b.textContent.includes('Guardar requisito')).click()`)
    await until(`window.__uploaded?.filename === 'ejemplo.pdf'`)
    assert.deepEqual(await evaluate('window.__uploaded'), { filename: 'ejemplo.pdf', size: (await readFile(pdf)).length, career: '1', procedure: '1' })
    await until(`document.body.textContent.includes('Requisito guardado correctamente.')`)
    await evaluate(`location.hash = '#/mallas/1'`)
    await until(`document.querySelector('.subject-form')`)
    assert.equal(await evaluate(`/Carga horaria|\\bHoras\\b/.test(document.querySelector('main').textContent)`), false)
    await evaluate(`(() => { const form = document.querySelector('.subject-form'); const inputs = form.querySelectorAll('input'); const set = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value').set; ['MAT-2', 'Matemática II', '4'].forEach((value, i) => { set.call(inputs[i], value); inputs[i].dispatchEvent(new Event('input', { bubbles: true })); }); })()`)
    await evaluate(`document.querySelector('.subject-form button').click()`)
    await until(`window.__subject?.codigo_asignatura === 'MAT-2'`)
    assert.equal(Object.hasOwn(await evaluate('window.__subject'), 'hr_carga_horaria'), false)
    await until(`document.querySelector('.data-table').textContent.includes('Matemática II')`)
    await evaluate(`window.__sameDocument = 'sin-recarga'; location.hash = '#/solicitudes/40'`)
    await until(`[...document.querySelectorAll('button')].some(b => b.textContent.includes('Comparar mallas automáticamente'))`)
    assert.equal(await evaluate(`document.body.textContent.includes('Avanzar la solicitud')`), false)
    assert.equal(await evaluate(`document.querySelector('input[name="total_creditos_reconocidos"]') !== null`), false)
    await evaluate(`(() => { const fields = [...document.querySelectorAll('select')]; ['2', '1'].forEach((value, i) => { fields[i].value = value; fields[i].dispatchEvent(new Event('change', { bubbles: true })); }); })()`)
    await evaluate(`[...document.querySelectorAll('button')].find(b => b.textContent.includes('Comparar mallas automáticamente')).click()`)
    await until(`document.querySelector('.result-summary')?.textContent.includes('Homologación total')`)
    assert.deepEqual(await evaluate('window.__compare'), { malla_origen_id: 2, malla_destino_id: 1, observacion: null })
    assert.equal(await evaluate('window.__sameDocument'), 'sin-recarga')
    await evaluate(`[...document.querySelectorAll('button')].find(b => b.textContent.includes('Generar informe académico')).click()`)
    await until(`[...document.querySelectorAll('button')].some(b => b.textContent.includes('Descargar informe'))`)
    assert.ok(await evaluate(`document.querySelector('main').textContent.includes('Terminado')`))
    await send('Page.navigate', { url: `${base}/?test_role=estudiante#/solicitudes` })
    await until(`document.querySelector('main')?.textContent.includes('Historial de solicitudes')`)
    assert.equal(await evaluate(`[...document.querySelectorAll('button')].some(b => /Nueva solicitud|Crear solicitud/.test(b.textContent))`), false)
    await evaluate(`location.hash = '#/solicitudes/40'`)
    await until(`document.body.textContent.includes('Mi checklist documental')`)
    assert.equal(await evaluate(`[...document.querySelectorAll('button')].some(b => /Editar|Enviar|Guardar/.test(b.textContent))`), false)
    await send('Page.navigate', { url: `${base}/?test_role=administrador#/` })
    await until(`document.body.textContent.includes('Control administrativo')`)
    assert.equal(await evaluate(`[...document.querySelectorAll('aside a')].some(a => a.textContent.includes('Solicitudes'))`), false)
    await evaluate(`location.hash = '#/solicitudes'`)
    await until(`document.body.textContent.includes('Sección no disponible')`)
  } finally {
    socket?.close()
    if (browser.exitCode === null) {
      const stopped = once(browser, 'exit')
      browser.kill()
      await Promise.race([stopped, pause(3000)])
    }
    // Limpiar exclusivamente el perfil temporal creado por esta prueba.
    assert.equal(dirname(resolve(profile)), resolve(tmpdir()))
    assert.ok(profile.includes('ueb-ui-'))
    await rm(profile, { recursive: true, force: true, maxRetries: 10, retryDelay: 200 })
  }
})
