import { useEffect, useRef, useState } from 'react'
import { getAnnouncementForEdit, publishAnnouncement } from '../api/annonces'
import { CommuneSelect } from './CommuneSelect'

const announcementTypes = [
  { id: 'OBJET', label: 'Un objet ou une création' },
  { id: 'REPAS', label: 'Un repas fait maison' },
]

function PickupFields({ initial }) {
  return (
    <>
      <label>
        Type de lieu
        <select
          name="type_lieu"
          defaultValue={initial?.type_lieu ?? ''}
          required
        >
          <option
            value=""
            disabled
          >
            Choisir un lieu
          </option>
          <option value="DOMICILE">Domicile</option>
          <option value="POINT">Point de retrait</option>
        </select>
      </label>
      <label>
        Adresse exacte de retrait
        <input
          name="adresse"
          defaultValue={initial?.adresse ?? ''}
          maxLength={255}
          required
        />
      </label>
      <p className="account-intro">
        L’adresse exacte sera communiquée après confirmation de l’achat.
      </p>
      <CommuneSelect
        current={
          initial?.commune_id
            ? {
                id: initial.commune_id,
                nom: initial.commune,
                code_postal: initial.code_postal,
              }
            : undefined
        }
        required
      />
      <label>
        Début du retrait
        <input
          name="debut_retrait"
          defaultValue={initial?.debut_retrait ?? ''}
          type="datetime-local"
          required
        />
      </label>
      <label>
        Fin du retrait
        <input
          name="fin_retrait"
          defaultValue={initial?.fin_retrait ?? ''}
          type="datetime-local"
          required
        />
      </label>
    </>
  )
}

function AnnouncementForm({ type, initial, pending, onPendingChange, onPublished }) {
  const isMeal = type === 'REPAS'
  const [feedback, setFeedback] = useState(null)
  const [photoPreview, setPhotoPreview] = useState(null)

  useEffect(() => {
    // Libère l’aperçu précédent quand la photo change ou que la fenêtre se ferme.
    return () => {
      if (photoPreview) URL.revokeObjectURL(photoPreview)
    }
  }, [photoPreview])

  function previewPhoto(event) {
    const file = event.currentTarget.files[0]
    const canPreview =
      file && file.size <= 2 * 1024 * 1024 && file.type.startsWith('image/')
    setPhotoPreview(canPreview ? URL.createObjectURL(file) : null)
  }

  const imageUrl = photoPreview ?? initial?.image_url

  async function checkFields(event) {
    event.preventDefault()
    if (pending) return
    const data = new FormData(event.currentTarget)
    const start = data.get('debut_retrait')
    const end = data.get('fin_retrait')

    if (new Date(start) >= new Date(end)) {
      setFeedback({ error: 'Le début du retrait doit précéder sa fin.' })
      return
    }

    if (isMeal && new Date(data.get('limite_achat')) >= new Date(start)) {
      setFeedback({ error: 'La limite d’achat doit précéder le début du retrait.' })
      return
    }

    if (data.get('image').size > 2 * 1024 * 1024) {
      setFeedback({ error: 'Choisis une photo de 2 Mo maximum.' })
      return
    }

    // FormData transporte le fichier photo avec les champs du formulaire.
    data.set('type', type)
    onPendingChange(true)
    setFeedback(null)
    try {
      onPublished(await publishAnnouncement(data, initial?.id))
    } catch (error) {
      setFeedback({ error: error.message })
    } finally {
      onPendingChange(false)
    }
  }

  return (
    <form
      className="account-form"
      onSubmit={checkFields}
      onChange={() => setFeedback(null)}
    >
      <p className="account-intro">Les horaires de retrait sont en heure belge.</p>
      <fieldset
        className="publish-fields"
        disabled={pending}
      >
        <legend>{isMeal ? 'Fiche du repas' : 'Détails de l’objet unique'}</legend>
        <label>
          {isMeal ? 'Nom du plat' : 'Titre de l’annonce'}
          <input
            name="titre"
            defaultValue={initial?.titre ?? ''}
            maxLength={255}
            required
          />
        </label>
        <label>
          Description
          <textarea
            name="description"
            defaultValue={initial?.description ?? ''}
            maxLength={10000}
            rows={4}
            required
          />
        </label>
        <label>
          Catégorie
          <input
            name="categorie"
            defaultValue={initial?.categorie ?? ''}
            maxLength={100}
            required
          />
        </label>
        <label>
          {isMeal ? 'Prix par portion (€)' : 'Prix de l’objet (€)'}
          <input
            name="prix"
            defaultValue={initial?.prix ?? ''}
            type="number"
            min="0.01"
            max="99999999.99"
            step="0.01"
            required
          />
        </label>
        <label>
          {initial ? 'Remplacer la photo (facultatif)' : 'Photo obligatoire'} (JPEG, PNG
          ou WebP, 2 Mo max.)
          <input
            name="image"
            type="file"
            accept="image/jpeg,image/png,image/webp"
            required={!initial}
            onChange={previewPhoto}
          />
        </label>
        {imageUrl && (
          <figure className="publish-photo-preview">
            <img
              src={imageUrl}
              alt={
                photoPreview
                  ? 'Aperçu de la photo sélectionnée'
                  : 'Photo actuelle de l’annonce'
              }
            />
            <figcaption>
              {photoPreview ? 'Photo sélectionnée' : 'Photo actuelle'}
            </figcaption>
          </figure>
        )}
      </fieldset>
      <fieldset
        className="publish-fields"
        disabled={pending}
      >
        <legend>{isMeal ? 'Offre de repas' : 'Retrait de l’objet'}</legend>
        <PickupFields initial={initial} />
        {isMeal && (
          <>
            <label>
              Limite d’achat
              <input
                name="limite_achat"
                defaultValue={initial?.limite_achat ?? ''}
                type="datetime-local"
                required
              />
            </label>
            <label>
              Nombre de portions
              <input
                name="portions_restantes"
                defaultValue={initial?.portions_restantes ?? ''}
                type="number"
                min="1"
                max="2147483647"
                step="1"
                required
              />
            </label>
          </>
        )}
      </fieldset>
      <button
        className="button button-primary"
        type="submit"
        disabled={pending}
      >
        {pending
          ? 'Enregistrement…'
          : initial
            ? 'Enregistrer les modifications'
            : isMeal
              ? 'Publier le repas'
              : 'Publier l’objet'}
      </button>
      {feedback?.error && (
        <p
          className="form-error"
          role="alert"
        >
          {feedback.error}
        </p>
      )}
    </form>
  )
}

export function PublishDialog({ editingId, onClose, onPublished }) {
  const dialogRef = useRef(null)
  const titleRef = useRef(null)
  // null affiche le choix, les autres valeurs affichent le formulaire correspondant.
  const [selectedType, setType] = useState(null)
  const [pending, setPending] = useState(false)
  const [initial, setInitial] = useState(null)
  const [loadError, setLoadError] = useState('')
  const type = editingId ? initial?.type : selectedType

  useEffect(() => {
    if (!editingId) return
    const controller = new AbortController()
    getAnnouncementForEdit(editingId, controller.signal)
      .then(setInitial)
      .catch((error) => {
        if (!controller.signal.aborted) setLoadError(error.message)
      })
    return () => controller.abort()
  }, [editingId])

  useEffect(() => {
    const dialog = dialogRef.current
    if (!dialog.open) dialog.showModal()
    titleRef.current.focus()
  }, [type])

  const title = editingId
    ? 'Modifier l’annonce'
    : type === 'OBJET'
      ? 'Publier un objet'
      : type === 'REPAS'
        ? 'Publier un repas'
        : 'Que veux-tu publier ?'

  return (
    <dialog
      ref={dialogRef}
      className="account-dialog"
      aria-labelledby="publish-title"
      onClose={onClose}
      onCancel={(event) => {
        if (pending) event.preventDefault()
      }}
    >
      <button
        className="dialog-close"
        type="button"
        aria-label="Fermer"
        disabled={pending}
        onClick={() => dialogRef.current.close()}
      >
        ×
      </button>
      <h2
        ref={titleRef}
        id="publish-title"
        tabIndex={-1}
      >
        {title}
      </h2>
      {type ? (
        <>
          {!editingId && (
            <button
              className="account-switch"
              type="button"
              disabled={pending}
              onClick={() => setType(null)}
            >
              Changer de type d’annonce
            </button>
          )}
          <AnnouncementForm
            key={type}
            type={type}
            initial={initial}
            pending={pending}
            onPendingChange={setPending}
            onPublished={(product) => {
              dialogRef.current.close()
              onPublished(product)
            }}
          />
        </>
      ) : editingId ? (
        <p role={loadError ? 'alert' : 'status'}>
          {loadError || 'Chargement de l’annonce…'}
        </p>
      ) : (
        <div className="account-summary">
          {announcementTypes.map(({ id, label }) => (
            <button
              key={id}
              className="button button-secondary"
              type="button"
              onClick={() => setType(id)}
            >
              {label}
            </button>
          ))}
        </div>
      )}
    </dialog>
  )
}
