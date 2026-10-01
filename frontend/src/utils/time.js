// Turns "13:00" into "1:00 PM"
export function formatTime(time) {
  const [hours, minutes] = time.split(':').map(Number)
  const period = hours >= 12 ? 'PM' : 'AM'
  const displayHours = hours % 12 === 0 ? 12 : hours % 12
  return `${displayHours}:${String(minutes).padStart(2, '0')} ${period}`
}

// Turns "2026-10-05" into "Monday, October 5, 2026"
export function formatLongDate(dateString) {
  return new Date(`${dateString}T00:00`).toLocaleDateString('en-PH', {
    weekday: 'long',
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  })
}