export function formatDateTime(dateStr?: string | null): string {
  if (!dateStr) return 'Just now'
  const date = new Date(dateStr)
  if (isNaN(date.getTime())) return dateStr

  const pad = (n: number) => String(n).padStart(2, '0')
  const hours = pad(date.getHours())
  const minutes = pad(date.getMinutes())
  const day = pad(date.getDate())
  const month = pad(date.getMonth() + 1)
  const year = date.getFullYear()

  return `${hours}:${minutes} ${day}/${month}/${year}`
}

export function formatDate(dateStr?: string | null, fallback = 'Unknown'): string {
  if (!dateStr) return fallback
  const date = new Date(dateStr)
  if (isNaN(date.getTime())) return dateStr

  const pad = (n: number) => String(n).padStart(2, '0')
  const day = pad(date.getDate())
  const month = pad(date.getMonth() + 1)
  const year = date.getFullYear()

  return `${day}/${month}/${year}`
}
