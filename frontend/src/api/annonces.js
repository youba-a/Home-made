export async function getAnnonces(page, type, signal, search = '', zone = '') {
  const params = new URLSearchParams({ page: String(page) })

  if (type !== 'TOUT') {
    params.set('type', type)
  }

  if (search.trim()) params.set('q', search.trim())
  if (zone) params.set('zone_id', zone)

  const response = await fetch(`/api/v1/annonces?${params}`, {
    headers: { Accept: 'application/json' },
    signal,
  })

  if (!response.ok) throw new Error('Le catalogue est indisponible.')

  const catalogue = await response.json()
  if (!Array.isArray(catalogue.data) || !catalogue.meta) {
    throw new Error('La réponse du catalogue est invalide.')
  }

  return catalogue
}

export async function getAnnonce(id, signal) {
  const response = await fetch(`/api/v1/annonces/${id}`, {
    headers: { Accept: 'application/json' },
    signal,
  })
  if (!response.ok) throw new Error('Cette annonce ne peut pas être chargée.')
  return (await response.json()).data
}

export async function getZones(signal) {
  const response = await fetch('/api/v1/zones', { signal })
  if (!response.ok) throw new Error('Les zones ne sont pas disponibles.')
  return (await response.json()).data
}
