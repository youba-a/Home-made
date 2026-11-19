import { useEffect, useState } from 'react'
import { getCommunes } from '../api/auth'

export function CommuneSelect({ current, required = false }) {
  const [choices, setChoices] = useState(null)
  const [error, setError] = useState('')

  useEffect(() => {
    const controller = new AbortController()
    getCommunes(controller.signal)
      .then(setChoices)
      .catch(() => {
        if (!controller.signal.aborted)
          setError('La liste des communes est indisponible.')
      })
    return () => controller.abort()
  }, [])

  const communes = choices ?? (current ? [current] : [])
  const placeholder = required ? 'Choisir une commune' : 'Aucune commune'

  return (
    <label>
      {required ? 'Commune de retrait' : 'Commune (facultative)'}
      <select
        name="commune_id"
        defaultValue={String(current?.id ?? '')}
        required={required}
        disabled={!required && choices === null}
      >
        <option
          value=""
          disabled={required}
        >
          {choices === null && !current && !error
            ? 'Chargement des communes…'
            : placeholder}
        </option>
        {communes.map(({ id, nom, code_postal }) => (
          <option
            key={id}
            value={id}
          >
            {nom} ({code_postal})
          </option>
        ))}
      </select>
      {error && <span role="status">{error}</span>}
    </label>
  )
}
