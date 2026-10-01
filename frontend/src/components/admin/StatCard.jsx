export default function StatCard({ label, value, highlight = false }) {
  return (
    <div
      className={`rounded-lg p-5 shadow ${
        highlight ? 'bg-amber-50 ring-1 ring-amber-200' : 'bg-white'
      }`}
    >
      <p className="text-sm text-gray-600">{label}</p>
      <p className={`mt-1 text-3xl font-bold ${highlight ? 'text-amber-700' : 'text-teal-700'}`}>
        {value}
      </p>
    </div>
  )
}