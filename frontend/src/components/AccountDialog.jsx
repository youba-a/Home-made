import { useEffect, useRef, useState } from 'react'
import { login, register, logout } from '../api/auth'

export function AccountDialog({ open, onClose, user, onUserChange }) {
  const ref = useRef(null)
  const [mode, setMode] = useState('connexion')
  const [pending, setPending] = useState(false)
  const [error, setError] = useState('')

  useEffect(() => {
    if (open && !ref.current.open) ref.current.showModal()
    if (!open && ref.current.open) ref.current.close()
  }, [open])

  function close() {
    setError('')
    onClose()
  }

  async function submit(event) {
    event.preventDefault()
    const data = Object.fromEntries(new FormData(event.currentTarget))
    setPending(true)
    setError('')
    try {
      onUserChange(await (mode === 'connexion' ? login(data) : register(data)))
      event.target.reset()
      close()
    } catch (failure) {
      setError(failure.message)
    } finally {
      setPending(false)
    }
  }

  async function disconnect() {
    setPending(true)
    setError('')
    try {
      await logout()
      onUserChange(null)
      close()
    } catch (failure) {
      setError(failure.message)
    } finally {
      setPending(false)
    }
  }

  return (
    <dialog
      ref={ref}
      className="account-dialog"
      aria-labelledby="account-title"
      onCancel={(event) => {
        event.preventDefault()
        close()
      }}
      onClick={(event) => {
        if (event.target === event.currentTarget) close()
      }}
    >
      <button
        type="button"
        className="dialog-close"
        onClick={close}
        aria-label="Fermer"
      >
        ×
      </button>
      <span className="eyebrow">Bienvenue chez Home Made</span>
      <h2 id="account-title">
        {user
          ? 'Mon compte'
          : mode === 'connexion'
            ? 'Heureux de vous retrouver.'
            : 'Rejoignez le fait maison.'}
      </h2>
      {error && (
        <p
          className="form-error"
          role="alert"
        >
          {error}
        </p>
      )}
      {user ? (
        <div className="account-summary">
          <p>
            Bonjour <strong>{user.name}</strong>.
          </p>
          <p>{user.email}</p>
          <p>Un seul compte pour acheter et vendre sur Home Made.</p>
          <button
            className="button button-secondary"
            type="button"
            disabled={pending}
            onClick={disconnect}
          >
            Se déconnecter
          </button>
        </div>
      ) : (
        <>
          <p className="account-intro">
            Consultez librement les découvertes. Votre compte vous accompagnera pour
            acheter et vendre.
          </p>
          <form
            onSubmit={submit}
            key={mode}
            className="account-form"
          >
            {mode === 'inscription' && (
              <label>
                Votre nom
                <input
                  name="name"
                  autoComplete="name"
                  required
                  maxLength={100}
                />
              </label>
            )}
            <label>
              Adresse email
              <input
                type="email"
                name="email"
                autoComplete="email"
                required
                maxLength={255}
              />
            </label>
            <label>
              Mot de passe
              <input
                type="password"
                name="password"
                autoComplete={mode === 'connexion' ? 'current-password' : 'new-password'}
                required
                minLength={mode === 'inscription' ? 8 : undefined}
                maxLength={128}
              />
            </label>
            {mode === 'inscription' && (
              <label>
                Confirmer le mot de passe
                <input
                  type="password"
                  name="password_confirmation"
                  autoComplete="new-password"
                  required
                  minLength={8}
                  maxLength={128}
                />
              </label>
            )}
            <button
              className="button button-primary"
              disabled={pending}
            >
              {pending
                ? 'Un instant…'
                : mode === 'connexion'
                  ? 'Se connecter'
                  : 'Créer mon compte'}
            </button>
          </form>
          <button
            type="button"
            className="account-switch"
            onClick={() => {
              setMode(mode === 'connexion' ? 'inscription' : 'connexion')
              setError('')
            }}
            disabled={pending}
          >
            {mode === 'connexion'
              ? 'Pas encore de compte ? S’inscrire'
              : 'Déjà un compte ? Se connecter'}
          </button>
        </>
      )}
    </dialog>
  )
}
