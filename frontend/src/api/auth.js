async function request(path, data) {
  const headers = { Accept: 'application/json' }

  if (data !== undefined) {
    const csrf = await fetch('/api/v1/auth/csrf', {
      credentials: 'same-origin',
      headers,
    })
    if (!csrf.ok) throw new Error('La connexion au serveur est indisponible.')
    headers['X-CSRF-TOKEN'] = (await csrf.json()).token
    headers['Content-Type'] = 'application/json'
  }

  const response = await fetch(`/api/v1/auth/${path}`, {
    method: data === undefined ? 'GET' : 'POST',
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
