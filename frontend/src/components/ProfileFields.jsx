import { CommuneSelect } from './CommuneSelect'

export function ProfileFields({ user }) {
  return (
    <>
      <label>
        Prénom
        <input
          name="first_name"
          autoComplete="given-name"
          defaultValue={user?.first_name ?? ''}
          maxLength={100}
          required
        />
      </label>
      <label>
        Nom
        <input
          name="last_name"
          autoComplete="family-name"
          defaultValue={user?.last_name ?? ''}
          maxLength={100}
          required
        />
      </label>
      <CommuneSelect current={user?.commune} />
    </>
  )
}
