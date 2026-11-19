import { formatPrice } from '../utils/formatPrice'

export function ProductImage({ product }) {
  return (
    <img
      key={product.image_url}
      className="product-image"
      src={product.image_url}
      alt={product.titre}
      loading="lazy"
      width="600"
      height="400"
      onError={(event) => {
        if (event.currentTarget.dataset.fallback) {
          return
        }

        event.currentTarget.dataset.fallback = 'true'
        event.currentTarget.src = '/visuel-attente.svg'
      }}
    />
  )
}

function availabilityLabel(product) {
  if (product.type === 'OBJET' && product.etat_objet === 'VENDU') {
    return 'Vendu'
  }

  if (!product.disponible) {
    return 'Indisponible'
  }

  if (product.type === 'REPAS') {
    const portions = product.offres_repas?.[0]?.portions_restantes ?? 0
    return `${portions} portion${portions === 1 ? '' : 's'}`
  }

  return 'Pièce unique'
}

export function ProductCard({ product, onSelect }) {
  const isMeal = product.type === 'REPAS'

  return (
    <article className="product-card">
      <div className={`product-image-wrap ${isMeal ? 'meal' : 'creation'}`}>
        <ProductImage product={product} />
        <span className={`product-badge ${isMeal ? 'meal-badge' : ''}`}>
          {isMeal ? 'Repas maison' : 'Création'}
        </span>
      </div>
      <div className="product-content">
        <span className="product-category">{product.categorie}</span>
        <div className="product-title-row">
          <h3>{product.titre}</h3>
          <span className="product-price">{formatPrice(product.prix)}</span>
        </div>
        <p>{product.description}</p>
        {product.commune && (
          <p className="product-location">Retrait à {product.commune}</p>
        )}
        <div className="product-bottom">
          <span>{availabilityLabel(product)}</span>
          <button
            className="product-open"
            type="button"
            onClick={() => onSelect(product)}
            aria-label={`Découvrir ${product.titre}`}
          >
            Découvrir <span aria-hidden="true">↗</span>
          </button>
        </div>
      </div>
    </article>
  )
}
