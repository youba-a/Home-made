import { useEffect, useState } from 'react'
import { deleteMyAnnouncement, getMyAnnouncements } from '../api/annonces'
import { ProductCard } from './ProductCard'

export function MyAnnouncements({ onSelect, onPublish, onDeleted, onEdit }) {
  // Une nouvelle requête recharge la page après un clic ou une erreur.
  const [request, setRequest] = useState({ page: 1 })
  const [result, setResult] = useState(null)
  const [deletingId, setDeletingId] = useState(null)
  const [deleteError, setDeleteError] = useState('')

  useEffect(() => {
    const controller = new AbortController()
    getMyAnnouncements(request.page, controller.signal)
      .then(({ data, meta }) => {
        if (!controller.signal.aborted) setResult({ request, data, meta })
      })
      .catch((error) => {
        if (!controller.signal.aborted) setResult({ request, error: error.message })
      })
    return () => controller.abort()
  }, [request])

  async function removeAnnouncement(product) {
    if (
      deletingId !== null ||
      !window.confirm(`Supprimer « ${product.titre} » définitivement ?`)
    )
      return

    setDeleteError('')
    setDeletingId(product.id)
    try {
      await deleteMyAnnouncement(product.id)
      const page = result.data.length === 1 ? Math.max(1, request.page - 1) : request.page
      setRequest({ page })
      onDeleted()
    } catch (error) {
      setDeleteError(error.message)
    } finally {
      setDeletingId(null)
    }
  }

  if (result?.request !== request) {
    return (
      <p
        className="personal-panel"
        role="status"
      >
        Chargement de tes annonces…
      </p>
    )
  }

  if (result.error) {
    return (
      <div className="personal-panel">
        <p role="alert">{result.error}</p>
        <button
          className="button button-secondary"
          type="button"
          onClick={() => setRequest({ page: request.page })}
        >
          Réessayer
        </button>
      </div>
    )
  }

  if (result.data.length === 0) {
    return (
      <div className="personal-panel personal-empty">
        <p>Tu n’as encore publié aucune annonce.</p>
        <button
          className="button button-primary"
          type="button"
          onClick={onPublish}
        >
          Publier une annonce
        </button>
      </div>
    )
  }

  return (
    <>
      {deleteError && (
        <p
          className="form-error"
          role="alert"
        >
          {deleteError}
        </p>
      )}
      <div className="personal-announcements">
        {result.data.map((product) => (
          <div
            className="personal-announcement"
            key={product.id}
          >
            <ProductCard
              product={product}
              onSelect={onSelect}
            />
            {product.etat_objet !== 'VENDU' && (
              <details className="personal-manage">
                <summary aria-label={`Gérer ${product.titre}`}>Gérer</summary>
                <div className="personal-manage-actions">
                  <button
                    type="button"
                    disabled={deletingId !== null}
                    onClick={() => onEdit(product.id)}
                  >
                    Modifier
                  </button>
                  <button
                    className="personal-delete"
                    type="button"
                    disabled={deletingId !== null}
                    onClick={() => removeAnnouncement(product)}
                  >
                    {deletingId === product.id ? 'Suppression…' : 'Supprimer'}
                  </button>
                </div>
              </details>
            )}
          </div>
        ))}
      </div>
      {result.meta.last_page > 1 && (
        <nav
          className="pagination"
          aria-label="Pages de mes annonces"
        >
          <button
            type="button"
            disabled={request.page === 1 || deletingId !== null}
            onClick={() => setRequest({ page: request.page - 1 })}
          >
            ← Précédent
          </button>
          <span>
            Page {request.page} sur {result.meta.last_page}
          </span>
          <button
            type="button"
            disabled={request.page === result.meta.last_page || deletingId !== null}
            onClick={() => setRequest({ page: request.page + 1 })}
          >
            Suivant →
          </button>
        </nav>
      )}
    </>
  )
}
