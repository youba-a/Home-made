import { getCsrfToken } from './auth'

export async function publishAnnouncement(data, id) {
  const token = await getCsrfToken()
  const url = id ? `/api/v1/mes-annonces/${id}` : '/api/v1/annonces'
  // PHP lit la photo du formulaire multipart avec POST, Laravel applique PATCH.
  if (id) data.set('_method', 'PATCH')
  const response = await fetch(url, {
    method: 'POST',
    credentials: 'same-origin',
    headers: { Accept: 'application/json', 'X-CSRF-TOKEN': token },
    body: data,
  })

  if (response.status === 401 || response.status === 419) {
    throw new Error('Ta session a expiré. Reconnecte-toi pour enregistrer l’annonce.')
  }
  if (response.status === 413) throw new Error('La photo est trop volumineuse.')
  if (!response.headers.get('content-type')?.includes('application/json')) {
    throw new Error(
      'Le serveur a renvoyé une réponse inattendue. Vérifie dans Explorer si ton annonce a été créée.',
    )
  }
  const result = await response.json().catch(() => {
    throw new Error(
      'La réponse du serveur est illisible. Vérifie dans Explorer si ton annonce a été créée.',
    )
  })
  if (response.status === 422) {
    throw new Error(Object.values(result.errors).flat()[0])
  }
  if (response.status === 409) throw new Error(result.message)
  if (!response.ok) throw new Error('L’enregistrement a échoué. Réessaie.')

  return result.data
}

export async function getAnnouncementForEdit(id, signal) {
  const response = await fetch(`/api/v1/mes-annonces/${id}`, {
    signal,
    headers: { Accept: 'application/json' },
  })
  if (response.status === 409)
    throw new Error('Cette annonce a déjà une commande en cours ou confirmée.')
  if (!response.ok) throw new Error('Impossible d’ouvrir cette annonce pour la modifier.')
  return (await response.json()).data
}

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

export async function deleteMyAnnouncement(id) {
  const token = await getCsrfToken()
  const response = await fetch(`/api/v1/mes-annonces/${id}`, {
    method: 'DELETE',
    headers: { Accept: 'application/json', 'X-CSRF-TOKEN': token },
  })
  if (response.status === 401 || response.status === 419)
    throw new Error('Reconnecte-toi pour supprimer cette annonce.')
  if (response.status === 409)
    throw new Error('Cette annonce a déjà une commande en cours ou confirmée.')
  if (response.status === 404) throw new Error('Cette annonce est introuvable.')
  if (!response.ok)
    throw new Error('Impossible de supprimer cette annonce pour le moment.')
}

export async function getMyAnnouncements(page, signal) {
  const response = await fetch(`/api/v1/mes-annonces?page=${page}`, {
    headers: { Accept: 'application/json' },
    signal,
  })
  if (response.status === 401)
    throw new Error('Reconnecte-toi pour consulter tes annonces.')
  if (!response.ok) throw new Error('Impossible de charger tes annonces pour le moment.')
  return response.json()
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
