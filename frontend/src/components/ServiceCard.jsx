const pesoFormatter = new Intl.NumberFormat('en-PH', {
  style: 'currency',
  currency: 'PHP',
})

export default function ServiceCard({ service }) {
  return (
    <article className="rounded-lg bg-white p-5 shadow">
      <h2 className="text-lg font-semibold text-teal-700">{service.name}</h2>

      {service.description && (
        <p className="mt-2 text-sm text-gray-600">{service.description}</p>
      )}

      <div className="mt-4 flex items-center justify-between text-sm">
        <span className="font-medium text-gray-900">
          {pesoFormatter.format(Number(service.price))}
        </span>
        <span className="text-gray-500">{service.duration_minutes} min</span>
      </div>
    </article>
  )
}