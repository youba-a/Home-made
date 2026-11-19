import { useEffect, useState } from 'react'
import { flushSync } from 'react-dom'
import { getAnnonces, getZones } from './api/annonces'
import { ProductCard } from './components/ProductCard'
import { ProductDialog } from './components/ProductDialog'
import { AccountDialog } from './components/AccountDialog'
import { MonEspace } from './components/MonEspace'
import { PublishDialog } from './components/PublishDialog'
import { getCurrentUser } from './api/auth'
import './App.css'

const filters = [
  { value: 'TOUT', label: 'Tout découvrir' },
  { value: 'OBJET', label: 'Créations' },
  { value: 'REPAS', label: 'Repas maison' },
]

function Brand({ onClick }) {
  return (
    <a
      className="brand"
      href="#accueil"
      onClick={onClick}
      aria-label="Home Made, accueil"
    >
      <svg
        viewBox="0 0 40 40"
        fill="none"
        aria-hidden="true"
      >
        <path
          d="m7 19 13-11 13 11v15H7Z"
          stroke="currentColor"
          strokeWidth="2"
          strokeLinejoin="round"
        />
        <path
          d="M17 34V23h7v11M20 8c-1-5 3-7 7-6-1 4-3 6-7 6Z"
          stroke="currentColor"
          strokeWidth="2"
          strokeLinejoin="round"
        />
      </svg>
      <span>
        Home Made<small>Le talent de chez nous</small>
      </span>
    </a>
  )
}

function App() {
  const [request, setRequest] = useState({ page: 1, type: 'TOUT', q: '', zone_id: '' })
  const [catalogue, setCatalogue] = useState({
    request: null,
    data: [],
    meta: null,
    error: '',
  })
  const [zones, setZones] = useState([])
  const [selectedProduct, setSelectedProduct] = useState(null)
  const [user, setUser] = useState(null)
  // Affiche la fenêtre de connexion ou les actions du compte.
  const [accountOpen, setAccountOpen] = useState(false)
  // Mémorise la section personnelle affichée. null ferme l'espace.
  const [activeSection, setActiveSection] = useState(null)
  const showPersonalSpace = Boolean(user && activeSection)
  // Ouvre le formulaire d'annonce, indépendamment de la fenêtre de compte.
  const [publishOpen, setPublishOpen] = useState(false)
  // Affiche les liens de navigation derrière le bouton Menu sur mobile.
  const [mobileMenuOpen, setMobileMenuOpen] = useState(false)
  const [editingId, setEditingId] = useState(null)
  const [announcementsRevision, setAnnouncementsRevision] = useState(0)
  const { page, type: filter, q: search, zone_id: zone } = request
  const visibleProducts = catalogue.data
  const meta = catalogue.meta
  const status =
    catalogue.request !== request ? 'loading' : catalogue.error ? 'error' : 'ready'
  const resultCount = meta?.total ?? 0
  const hasActiveFilters = filter !== 'TOUT' || search.trim() !== '' || zone !== ''

  useEffect(() => {
    const controller = new AbortController()
    const timer = setTimeout(
      () => {
        getAnnonces(
          request.page,
          request.type,
          controller.signal,
          request.q,
          request.zone_id,
        )
          .then((data) => {
            if (!controller.signal.aborted)
              setCatalogue({ request, data: data.data, meta: data.meta, error: '' })
          })
          .catch((error) => {
            if (!controller.signal.aborted)
              setCatalogue({ request, data: [], meta: null, error: error.message })
          })
      },
      request.q ? 250 : 0,
    )
    return () => {
      clearTimeout(timer)
      controller.abort()
    }
  }, [request])

  useEffect(() => {
    const controller = new AbortController()
    let active = true
    getZones(controller.signal)
      .then(setZones)
      .catch(() => {})
    getCurrentUser()
      .then((data) => {
        if (active) setUser(data)
      })
      .catch(() => {})
    return () => {
      active = false
      controller.abort()
    }
  }, [])

  function updateRequest(changes) {
    setRequest((previous) => ({ ...previous, ...changes, page: changes.page ?? 1 }))
  }

  function resetFilters() {
    setRequest({ page: 1, type: 'TOUT', q: '', zone_id: '' })
  }

  function openAccount() {
    setMobileMenuOpen(false)
    setSelectedProduct(null)
    setAccountOpen(true)
  }

  function handlePublishClick() {
    setMobileMenuOpen(false)
    if (!user) {
      openAccount()
      return
    }

    setEditingId(null)
    setPublishOpen(true)
  }

  function handlePublished(product) {
    setPublishOpen(false)
    setAnnouncementsRevision((revision) => revision + 1)
    setRequest((previous) => ({ ...previous }))
    setSelectedProduct(product)
  }

  function updateUser(nextUser) {
    setUser(nextUser)
    if (!nextUser) {
      setActiveSection(null)
      setPublishOpen(false)
    }
  }

  function closeSpace() {
    setActiveSection(null)
  }

  function followPublicLink() {
    setMobileMenuOpen(false)
    if (showPersonalSpace) {
      // Réaffiche la cible du lien avant que le navigateur défile jusqu'à elle.
      flushSync(closeSpace)
    }
  }

  return (
    <>
      <a
        className="skip-link"
        href="#catalogue"
        onClick={followPublicLink}
      >
        Aller au catalogue
      </a>
      <div
        className="top-note"
        id="haut-de-page"
      >
        Des mains passionnées. Des découvertes à partager.
      </div>
      <header
        className="site-header shell"
        onKeyDown={(event) => {
          if (mobileMenuOpen && event.key === 'Escape') {
            setMobileMenuOpen(false)
            event.currentTarget.querySelector('.mobile-menu-toggle')?.focus()
          }
        }}
      >
        <Brand onClick={followPublicLink} />
        <button
          className="mobile-menu-toggle"
          type="button"
          aria-controls="main-navigation"
          aria-expanded={mobileMenuOpen}
          onClick={() => setMobileMenuOpen((open) => !open)}
        >
          <span aria-hidden="true">{mobileMenuOpen ? '×' : '☰'}</span>
          Menu
        </button>
        <nav
          id="main-navigation"
          className={mobileMenuOpen ? 'navigation-open' : undefined}
          aria-label="Navigation principale"
        >
          <a
            href="#accueil"
            onClick={followPublicLink}
          >
            Accueil
          </a>
          <a
            href="#catalogue"
            onClick={followPublicLink}
          >
            Explorer
          </a>
          <button
            className="publish-link"
            type="button"
            aria-haspopup="dialog"
            onClick={handlePublishClick}
          >
            Publier
          </button>
          <button
            className="mobile-account-link"
            type="button"
            aria-haspopup="dialog"
            onClick={openAccount}
          >
            {user ? 'Mon compte' : 'Se connecter'}
          </button>
        </nav>
        <button
          className="header-link account-button"
          type="button"
          onClick={openAccount}
        >
          {user ? 'Mon compte' : 'Se connecter'} <span aria-hidden="true">↗</span>
        </button>
      </header>

      {showPersonalSpace && (
        <main>
          <MonEspace
            user={user}
            section={activeSection}
            onSelectSection={setActiveSection}
            onUserChange={(profile) => {
              setUser((current) => (current?.id === profile.id ? profile : current))
            }}
            onClose={closeSpace}
            onSelectProduct={setSelectedProduct}
            onPublish={handlePublishClick}
            onDeleted={() => setRequest((previous) => ({ ...previous }))}
            announcementsRevision={announcementsRevision}
            onEdit={(id) => {
              setEditingId(id)
              setPublishOpen(true)
            }}
          />
        </main>
      )}
      <main
        id="accueil"
        hidden={showPersonalSpace}
      >
        <section
          className="hero shell"
          aria-labelledby="hero-title"
        >
          <div className="hero-copy">
            <span className="eyebrow">
              <span className="small-dot" /> Du savoir-faire, près de chez vous
            </span>
            <h1 id="hero-title">
              Du fait maison,
              <br />
              <em>tout près de vous.</em>
            </h1>
            <p>
              Une création qui a une histoire. Un repas préparé avec cœur. Découvrez les
              petits trésors faits maison, à deux pas de chez vous.
            </p>
            <a
              className="button button-primary"
              href="#catalogue"
            >
              Explorer les découvertes <span aria-hidden="true">↗</span>
            </a>
            <div className="hero-caption">
              <span aria-hidden="true">✳</span> Des objets uniques, des repas à partager.
            </div>
          </div>
          <figure className="hero-visual">
            <img
              src="/atelier.svg"
              alt="Illustration d’un sac en tissu, d’une bougie et d’un plat faits maison"
              width="620"
              height="480"
            />
            <span
              className="hero-stamp"
              aria-hidden="true"
            >
              Le fait maison
              <br />
              <strong>♡</strong>a du bon
            </span>
            <figcaption>Créé, cuisiné, partagé.</figcaption>
          </figure>
        </section>

        <section
          className="catalogue-section"
          id="catalogue"
          aria-labelledby="catalogue-title"
        >
          <div className="shell">
            <div className="section-heading">
              <div>
                <span className="eyebrow">La sélection du moment</span>
                <h2 id="catalogue-title">
                  De belles choses,
                  <br className="mobile-break" /> tout simplement.
                </h2>
              </div>
              <p>
                Le plaisir de découvrir
                <br />
                le savoir-faire de nos voisins.
              </p>
            </div>

            <div className="catalogue-toolbar">
              <div
                className="filters"
                role="group"
                aria-label="Type de produit"
              >
                {filters.map((item) => (
                  <button
                    type="button"
                    key={item.value}
                    className={filter === item.value ? 'filter active' : 'filter'}
                    aria-pressed={filter === item.value}
                    onClick={() => {
                      updateRequest({ type: item.value })
                    }}
                  >
                    {item.label}
                  </button>
                ))}
              </div>
              <label className="zone-filter">
                <span className="sr-only">Zone de retrait</span>
                <select
                  value={zone}
                  onChange={(event) => updateRequest({ zone_id: event.target.value })}
                >
                  <option value="">Toutes les zones</option>
                  {zones.map((item) => (
                    <option
                      key={item.id}
                      value={item.id}
                    >
                      {item.nom}
                    </option>
                  ))}
                </select>
              </label>
              <label className="search-box">
                <svg
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  strokeWidth="1.7"
                  aria-hidden="true"
                >
                  <circle
                    cx="10.5"
                    cy="10.5"
                    r="6.5"
                  />
                  <path d="m16 16 4 4" />
                </svg>
                <span className="sr-only">Rechercher dans tout le catalogue</span>
                <input
                  type="search"
                  maxLength={100}
                  placeholder="Une envie particulière ?"
                  value={search}
                  onChange={(event) => updateRequest({ q: event.target.value })}
                />
              </label>
            </div>

            <div
              className="catalogue-status"
              role="status"
              aria-live="polite"
            >
              {status === 'loading'
                ? 'Nos découvertes arrivent…'
                : status === 'error'
                  ? ''
                  : `${resultCount} découverte${resultCount === 1 ? '' : 's'} dans cette sélection`}
            </div>

            {status === 'loading' && (
              <div
                className="product-grid"
                aria-label="Chargement du catalogue"
                aria-busy="true"
              >
                {Array.from({ length: 4 }, (_, index) => (
                  <div
                    className="skeleton-card"
                    key={index}
                  >
                    <div />
                    <span />
                    <span />
                  </div>
                ))}
              </div>
            )}
            {status === 'error' && (
              <div
                className="empty-state"
                role="alert"
              >
                <span aria-hidden="true">↻</span>
                <h3>Une petite pause imprévue.</h3>
                <p>Le catalogue ne peut pas être chargé pour le moment.</p>
                <button
                  className="button button-primary"
                  type="button"
                  onClick={() => setRequest({ ...request })}
                >
                  Réessayer
                </button>
              </div>
            )}
            {status === 'ready' && visibleProducts.length > 0 && (
              <div className="product-grid">
                {visibleProducts.map((product) => (
                  <ProductCard
                    key={product.id}
                    product={product}
                    onSelect={setSelectedProduct}
                  />
                ))}
              </div>
            )}
            {status === 'ready' && visibleProducts.length === 0 && (
              <div className="empty-state">
                <span aria-hidden="true">✳</span>
                <h3>
                  {!hasActiveFilters
                    ? 'Les premières découvertes arrivent bientôt.'
                    : 'Pas encore de trouvaille par ici.'}
                </h3>
                <p>
                  {!hasActiveFilters
                    ? 'Revenez découvrir les prochaines créations et les prochains repas.'
                    : 'Essayez un autre mot ou explorez toute la sélection.'}
                </p>
                {hasActiveFilters && (
                  <button
                    className="button button-secondary"
                    type="button"
                    onClick={resetFilters}
                  >
                    Voir toute la sélection
                  </button>
                )}
              </div>
            )}

            {status === 'ready' && meta?.last_page > 1 && (
              <nav
                className="pagination"
                aria-label="Pages du catalogue"
              >
                <button
                  type="button"
                  disabled={page === 1}
                  onClick={() => updateRequest({ page: page - 1 })}
                >
                  ← Précédent
                </button>
                <span>
                  Page {meta.current_page} sur {meta.last_page}
                </span>
                <button
                  type="button"
                  disabled={page >= meta.last_page}
                  onClick={() => updateRequest({ page: page + 1 })}
                >
                  Suivant →
                </button>
              </nav>
            )}
          </div>
        </section>

        <section
          className="how-section shell"
          id="comment-ca-marche"
          aria-labelledby="how-title"
        >
          <div className="how-intro">
            <span className="eyebrow">L’esprit Home Made</span>
            <h2 id="how-title">
              Derrière chaque
              <br /> création, quelqu’un.
            </h2>
            <p>On aime les choses bien faites, et les personnes qui les font.</p>
          </div>
          <div className="how-items">
            <article>
              <span className="step-number">01</span>
              <div>
                <h3>Des créations singulières</h3>
                <p>
                  Des objets faits main, avec du temps, du soin et un petit supplément
                  d’âme.
                </p>
              </div>
            </article>
            <article>
              <span className="step-number">02</span>
              <div>
                <h3>Le goût du fait maison</h3>
                <p>
                  Des plats préparés par des passionnés, pour le plaisir de bien manger.
                </p>
              </div>
            </article>
            <article>
              <span className="step-number">03</span>
              <div>
                <h3>La proximité, simplement</h3>
                <p>
                  Découvrez les talents de votre région et les conditions de retrait
                  proposées.
                </p>
              </div>
            </article>
          </div>
        </section>
      </main>

      <footer className="site-footer">
        <div className="site-footer-content shell">
          <div className="site-footer-brand">
            <Brand onClick={followPublicLink} />
            <p>Des créations et des repas faits maison, à découvrir près de chez toi.</p>
          </div>
          <nav aria-label="Navigation de pied de page">
            <h2>Découvrir</h2>
            <a
              href="#catalogue"
              onClick={followPublicLink}
            >
              Explorer les annonces
            </a>
            <a
              href="#comment-ca-marche"
              onClick={followPublicLink}
            >
              L’esprit Home Made
            </a>
          </nav>
          <div className="site-footer-pickup">
            <h2>Retrait sur rendez-vous</h2>
            <p>
              La commune et les horaires sont sur la fiche. L’adresse exacte est
              communiquée après confirmation de l’achat.
            </p>
          </div>
        </div>
        <div className="site-footer-bottom shell">
          <span>Home Made · Projet scolaire</span>
          <a
            className="site-footer-top"
            href="#haut-de-page"
          >
            Retour en haut <span aria-hidden="true">↑</span>
          </a>
        </div>
      </footer>
      <ProductDialog
        product={selectedProduct}
        onClose={() => setSelectedProduct(null)}
        user={user}
        onConnect={openAccount}
      />
      <AccountDialog
        open={accountOpen}
        onClose={() => setAccountOpen(false)}
        user={user}
        onUserChange={updateUser}
        onOpenSpace={setActiveSection}
      />
      {user && publishOpen && (
        <PublishDialog
          editingId={editingId}
          onClose={() => setPublishOpen(false)}
          onPublished={handlePublished}
        />
      )}
    </>
  )
}

export default App
