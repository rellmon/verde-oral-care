import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import api from '../api/axios'
import DentistCard from '../components/DentistCard'

export default function Dentists() {
  const [dentists, setDentists] = useState([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')

  useEffect(() => {
    api
      .get('/dentists')
      .then((response) => setDentists(response.data.data))
      .catch(() => setError('We could not load our dentists. Please try again later.'))
      .finally(() => setLoading(false))
  }, [])

  return (
    <div className="min-h-screen bg-teal-50 px-4 py-8">
      <div className="mx-auto max-w-5xl">
        <Link to="/" className="text-sm text-teal-700 underline">
          &larr; Back to home
        </Link>

        <h1 className="mt-4 text-3xl font-bold text-teal-700">Our Dentists</h1>
        <p className="mt-1 text-gray-600">Meet the team taking care of your smile.</p>

        {loading && <p className="mt-8 text-gray-600">Loading dentists...</p>}

        {error && (
          <p className="mt-8 rounded bg-red-50 p-3 text-red-600" role="alert">
            {error}
          </p>
        )}

        {!loading && !error && dentists.length === 0 && (
          <p className="mt-8 text-gray-600">No dentists are available right now.</p>
        )}

        <div className="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          {dentists.map((dentist) => (
            <DentistCard key={dentist.id} dentist={dentist} />
          ))}
        </div>
      </div>
    </div>
  )
}