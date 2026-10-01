const STATUS_STYLES = {
  pending: { label: 'Pending', classes: 'bg-amber-100 text-amber-800' },
  confirmed: { label: 'Confirmed', classes: 'bg-green-100 text-green-700' },
  completed: { label: 'Completed', classes: 'bg-teal-100 text-teal-800' },
  cancelled: { label: 'Cancelled', classes: 'bg-gray-200 text-gray-600' },
  no_show: { label: 'No-show', classes: 'bg-red-100 text-red-700' },
}

export default function StatusBadge({ status }) {
  const style = STATUS_STYLES[status] ?? { label: status, classes: 'bg-gray-200 text-gray-600' }

  return (
    <span className={`rounded-full px-2 py-1 text-xs font-medium ${style.classes}`}>
      {style.label}
    </span>
  )
}