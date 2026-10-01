export function getInitials(name) {
  return name
    .replace(/^dr\.?\s+/i, '')
    .split(' ')
    .slice(0, 2)
    .map((word) => word[0])
    .join('')
    .toUpperCase()
}