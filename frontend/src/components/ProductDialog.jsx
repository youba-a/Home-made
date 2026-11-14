import { useEffect, useRef, useState } from 'react'
import { getAnnonce } from '../api/annonces'
import { formatPrice } from '../utils/formatPrice'
import { formatDate } from '../utils/formatDate'
import { ProductImage } from './ProductCard'

function Pickup({ place, start, end }) {
  return (
    <dl className="pickup-details">
      <div>
        <dt>Retrait</dt>
        <dd>
          {place.commune.nom} ({place.commune.code_postal}) · {place.zone.nom}
        </dd>
      </div>
      <div>
        <dt>Lieu</dt>
        <dd>{place.type === 'DOMICILE' ? 'Chez le vendeur' : 'Point de retrait'}</dd>
      </div>
      <div>
        <dt>Créneau</dt>
        <dd>
          Du {formatDate(start)} au {formatDate(end)}
        </dd>
      </div>
    </dl>
  )
}

export function ProductDialog({ product, onClose, user, onConnect }) {
  const dialogRef = useRef(null)
  const [result, setResult] = useState({ id: null, data: null, error: '' })
  const productId = product?.id
  const ready = result.id === productId

  useEffect(() => {
    const dialog = dialogRef.current
    if (product && !dialog.open) dialog.showModal()
    if (!product && dialog.open) dialog.close()
  }, [product])

  useEffect(() => {
    if (!productId) return
    const controller = new AbortController()
    getAnnonce(productId, controller.signal)
      .then((data) => {
        if (!controller.signal.aborted) setResult({ id: productId, data, error: '' })
      })
      .catch((error) => {
        if (!controller.signal.aborted)
          setResult({ id: productId, data: null, error: error.message })
      })
    return () => controller.abort()
  }, [productId])

  const detail = ready && result.data ? result.data : product

  return (
    <dialog
      className="product-dialog"
      ref={dialogRef}
      aria-labelledby="product-dialog-title"
      onCancel={(event) => {
        event.preventDefault()
        onClose()
      }}
      onClick={(event) => {
        if (event.target === event.currentTarget) onClose()
      }}
    >
      {detail && (
        <>
          <button
            className="dialog-close"
            type="button"
            onClick={onClose}
            aria-label="Fermer la fiche"
          >
            ×
          </button>
          <ProductImage product={detail} />
          <div className="dialog-content">
            <span className="eyebrow">
              {detail.type === 'REPAS' ? 'Un repas fait maison' : 'Une création unique'}
            </span>
            <h2 id="product-dialog-title">{detail.titre}</h2>
            <p className="dialog-price">
              {formatPrice(detail.prix)}
              {detail.type === 'REPAS' && <small> / portion</small>}
            </p>
            <p>{detail.description}</p>
            {!ready && <p role="status">Chargement des conditions de retrait…</p>}
            {ready && result.error && (
              <p
                className="form-error"
                role="alert"
              >
                {result.error}
              </p>
            )}
            {ready && result.data && (
              <>
                <p className="seller-name">
                  Proposé par <strong>{detail.vendeur?.nom}</strong>
                </p>
                <span
                  className={`availability-chip ${detail.disponible ? 'available' : 'unavailable'}`}
                >
                  {detail.disponible ? 'Disponible' : 'Indisponible actuellement'}
                </span>
                {detail.type === 'OBJET' && detail.retrait && (
                  <Pickup
                    place={detail.retrait.lieu}
                    start={detail.retrait.debut_retrait}
                    end={detail.retrait.fin_retrait}
                  />
                )}
                {detail.type === 'REPAS' &&
                  detail.offres_repas.map((offer) => (
                    <div
                      className="meal-offer"
                      key={offer.id}
                    >
                      <h3>
                        {offer.portions_restantes} portion
                        {offer.portions_restantes === 1 ? '' : 's'} restante
                        {offer.portions_restantes === 1 ? '' : 's'}
                      </h3>
                      <Pickup
                        place={offer.lieu_retrait}
                        start={offer.debut_retrait}
                        end={offer.fin_retrait}
                      />
                      <p className="purchase-limit">
                        Achat possible avant le {formatDate(offer.limite_achat)}.
                      </p>
                    </div>
                  ))}
                {detail.type === 'REPAS' && detail.offres_repas.length === 0 && (
                  <p>Aucune offre publiée pour ce plat.</p>
                )}
                <p className="privacy-note">
                  L’adresse exacte sera communiquée après confirmation de l’achat.
                  Horaires affichés en heure belge.
                </p>
              </>
            )}
            <div className="dialog-actions">
              {!user && (
                <button
                  className="button button-primary"
                  type="button"
                  onClick={onConnect}
                >
                  Se connecter
                </button>
              )}
              <button
                className="button button-secondary"
                type="button"
                onClick={onClose}
              >
                Retour au catalogue
              </button>
            </div>
          </div>
        </>
      )}
    </dialog>
  )
}
