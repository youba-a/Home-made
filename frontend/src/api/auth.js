export async function getCsrfToken() {
  const response = await fetch('/api/v1/auth/csrf', {
    credentials: 'same-origin',
    headers: { Accept: 'application/json' },
  })
  if (!response.ok) throw new Error('La connexion au serveur est indisponible.')
  return (await response.json()).token
}

async function request(path, data, method = data === undefined ? 'GET' : 'POST') {
  const headers = { Accept: 'application/json' }

  if (data !== undefined) {
    headers['X-CSRF-TOKEN'] = await getCsrfToken()
    headers['Content-Type'] = 'application/json'
  }

  const response = await fetch(`/api/v1/auth/${path}`, {
    method,
    credentials: 'same-origin',
    headers,
    body: data === undefined ? undefined : JSON.stringify(data),
  })
  const body = await response.json()
  if (!response.ok) {
    throw new Error(
      body.errors
        ? Object.values(body.errors).flat().join(' ')
        : 'La demande a échoué. Réessaie.',
    )
  }
  return body.data
}

export const getCurrentUser = () => request('me')
export const login = (data) => request('connexion', data)
export const register = (data) => request('inscription', data)
export const logout = () => request('deconnexion', {})
export const updateProfile = (data) => request('profil', data, 'PATCH')

export async function getCommunes(signal) {
  const response = await fetch('/api/v1/communes', {
    signal,
    headers: { Accept: 'application/json' },
  })
  if (!response.ok) throw new Error('La liste des communes est indisponible.')
  return (await response.json()).data
}
