const dateFormat = new Intl.DateTimeFormat('fr-BE', {
  dateStyle: 'medium',
  timeStyle: 'short',
  timeZone: 'Europe/Brussels',
})

export function formatDate(value) {
  return value ? dateFormat.format(new Date(value)) : 'À préciser'
}
