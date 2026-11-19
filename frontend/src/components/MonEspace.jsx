import { useState } from 'react'
import { updateProfile } from '../api/auth'
import { accountSections } from './accountSections'
import { ProfileFields } from './ProfileFields'
import { MyAnnouncements } from './MyAnnouncements'
import './MonEspace.css'

const sectionDescriptions = {
  annonces: 'Retrouve les créations et repas que tu publies.',
  achats: 'Consulte tes commandes et leurs conditions de retrait.',
  ventes: 'Suis les ventes liées à tes annonces.',
  profil: 'Consulte les informations de ton compte.',
}

function ProfilePanel({ user, initials, onUserChange }) {
  const [editing, setEditing] = useState(false)
  const [pending, setPending] = useState(false)
  const [error, setError] = useState('')
  const [saved, setSaved] = useState(false)

  async function save(event) {
    event.preventDefault()
    if (pending) return
    const data = Object.fromEntries(new FormData(event.currentTarget))
    setPending(true)
    setError('')
    try {
      onUserChange(await updateProfile(data))
      setEditing(false)
      setSaved(true)
    } catch (failure) {
      setError(failure.message)
    } finally {
      setPending(false)
    }
  }

  return (
    <div className="personal-panel">
      <div className="personal-profile-heading">
        <span
          className="personal-avatar"
          aria-hidden="true"
        >
          {initials}
        </span>
        <h2>Informations du compte</h2>
      </div>
      {editing ? (
        <form
          className="account-form"
          onSubmit={save}
        >
          {!user.last_name && (
            <p className="account-intro">
              Ton ancien nom d’affichage a été repris dans le prénom. Tu peux le répartir
              entre les deux champs.
            </p>
          )}
          <ProfileFields user={user} />
          {error && (
            <p
              className="form-error"
              role="alert"
            >
              {error}
            </p>
          )}
          <div className="personal-profile-actions">
            <button
              className="button button-primary"
              type="submit"
              disabled={pending}
            >
              {pending ? 'Enregistrement…' : 'Enregistrer'}
            </button>
            <button
              className="button button-secondary"
              type="button"
              disabled={pending}
              onClick={() => setEditing(false)}
            >
              Annuler
            </button>
          </div>
        </form>
      ) : (
        <>
          <dl className="personal-details">
            <div>
              <dt>Prénom</dt>
              <dd>{user.first_name || 'À compléter'}</dd>
            </div>
            <div>
              <dt>Nom</dt>
              <dd>{user.last_name || 'À compléter'}</dd>
            </div>
            <div>
              <dt>Adresse e-mail</dt>
              <dd>{user.email}</dd>
            </div>
            <div>
              <dt>Commune</dt>
              <dd>
                {user.commune
                  ? `${user.commune.nom} (${user.commune.code_postal})`
                  : 'Non renseignée'}
              </dd>
            </div>
          </dl>
          <button
            className="button button-secondary"
            type="button"
            onClick={() => {
              setError('')
              setSaved(false)
              setEditing(true)
            }}
          >
            Modifier mon profil
          </button>
        </>
      )}
      <div role="status">{saved && 'Ton profil a été mis à jour.'}</div>
    </div>
  )
}

export function MonEspace({
  user,
  section,
  onSelectSection,
  onUserChange,
  onClose,
  onSelectProduct,
  onPublish,
  onDeleted,
  announcementsRevision,
  onEdit,
}) {
  const current = accountSections.find(({ id }) => id === section)
  const initials = user.display_name
    .trim()
    .split(/\s+/)
    .slice(0, 2)
    .map((word) => Array.from(word)[0])
    .join('')
    .toLocaleUpperCase('fr')

  return (
    <section
      className="personal-space shell"
      aria-labelledby="mon-espace-title"
    >
      <aside className="personal-sidebar">
        <nav aria-label="Sections de mon espace">
          {accountSections.map(({ id, label }) => (
            <button
              key={id}
              type="button"
              className="personal-nav-link"
              aria-current={id === section ? 'page' : undefined}
              onClick={() => onSelectSection(id)}
            >
              {label}
            </button>
          ))}
        </nav>
        <select
          className="personal-section-select"
          aria-label="Sections de mon espace"
          value={section}
          onChange={(event) => onSelectSection(event.target.value)}
        >
          {accountSections.map(({ id, label }) => (
            <option
              key={id}
              value={id}
            >
              {label}
            </option>
          ))}
        </select>
        <button
          className="personal-back"
          type="button"
          onClick={onClose}
        >
          <span aria-hidden="true">←</span> Retour à l’accueil
        </button>
      </aside>

      <div className="personal-content">
        <header className="personal-heading">
          <h1 id="mon-espace-title">{current.label}</h1>
          {section === 'accueil' && (
            <p>Bonjour, {user.first_name || user.display_name} !</p>
          )}
        </header>

        {section === 'accueil' && (
          <div className="personal-grid">
            {accountSections.slice(1).map(({ id, label }) => (
              <button
                key={id}
                type="button"
                className="personal-card"
                onClick={() => onSelectSection(id)}
              >
                <strong>{label}</strong>
                <span>{sectionDescriptions[id]}</span>
                <span
                  className="personal-card-arrow"
                  aria-hidden="true"
                >
                  ↗
                </span>
              </button>
            ))}
          </div>
        )}

        {section === 'profil' && (
          <ProfilePanel
            user={user}
            initials={initials}
            onUserChange={onUserChange}
          />
        )}

        {section === 'annonces' && (
          <MyAnnouncements
            key={announcementsRevision}
            onEdit={onEdit}
            onSelect={onSelectProduct}
            onPublish={onPublish}
            onDeleted={onDeleted}
          />
        )}

        {current.empty && (
          <div className="personal-panel personal-empty">
            <p>{current.empty}</p>
          </div>
        )}
      </div>
    </section>
  )
}
