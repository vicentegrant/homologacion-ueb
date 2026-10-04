/** Caché breve en memoria: comparte lecturas y descarta resultados anteriores a una escritura. */
export class RequestCache {
  private values = new Map<string, { value: unknown; expires: number }>()
  private pending = new Map<string, Promise<unknown>>()
  private generation = 0

  clear() {
    this.generation++
    this.values.clear()
    this.pending.clear()
  }

  read<T>(key: string, loader: () => Promise<T>, ttl = 15000): Promise<T> {
    const cached = this.values.get(key)
    if (cached && cached.expires > Date.now()) return Promise.resolve(cached.value as T)
    const pending = this.pending.get(key)
    if (pending) return pending as Promise<T>
    const generation = this.generation
    const request = Promise.resolve().then(loader).then((value) => {
      if (generation === this.generation) {
        if (this.values.size >= 200) this.values.delete(this.values.keys().next().value!)
        this.values.set(key, { value, expires: Date.now() + ttl })
      }
      return value
    }).finally(() => {
      if (this.pending.get(key) === request) this.pending.delete(key)
    })
    this.pending.set(key, request)
    return request
  }
}
