function getInitials(name) {
  return name
    .replace(/^dr\.?\s+/i, '')
    .split(' ')
    .slice(0, 2)
    .map((word) => word[0])
    .join('')
    .toUpperCase()
}

export default function DentistCard({ dentist }) {
  return (
    <article className="rounded-lg bg-white p-5 shadow">
      <div className="flex items-center gap-4">
        <div
          className="flex h-14 w-14 shrink-0 items-center justify-center rounded-full bg-teal-100 text-lg font-semibold text-teal-700"
          aria-hidden="true"
        >
          {getInitials(dentist.name)}
        </div>

        <div>
          <h2 className="text-lg font-semibold text-teal-700">{dentist.name}</h2>
          <p className="text-sm text-gray-500">{dentist.specialization}</p>
        </div>
      </div>

      {dentist.biography && (
        <p className="mt-4 line-clamp-3 text-sm text-gray-600">{dentist.biography}</p>
      )}
    </article>
  )
}