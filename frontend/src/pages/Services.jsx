import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import api from '../api/axios'
import ServiceCard from '../components/ServiceCard'

export default function Services() {
  const [services, setServices] = useState([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')

  useEffect(() => {
    api
      .get('/services')
      .then((response) => setServices(response.data.data))
      .catch(() => setError('We could not load our services. Please try again later.'))
      .finally(() => setLoading(false))
  }, [])

  return (
    <div className="min-h-screen bg-teal-50 px-4 py-8">
      <div className="mx-auto max-w-5xl">
        <Link to="/" className="text-sm text-teal-700 underline">
          &larr; Back to home
        </Link>

        <h1 className="mt-4 text-3xl font-bold text-teal-700">Our Services</h1>
        <p className="mt-1 text-gray-600">Quality dental care for the whole family.</p>

        {loading && <p className="mt-8 text-gray-600">Loading services...</p>}

        {error && (
          <p className="mt-8 rounded bg-red-50 p-3 text-red-600" role="alert">
            {error}
          </p>
        )}

        {!loading && !error && services.length === 0 && (
          <p className="mt-8 text-gray-600">No services are available right now.</p>
        )}

        <div className="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          {services.map((service) => (
            <ServiceCard key={service.id} service={service} />
          ))}
        </div>
      </div>
    </div>
  )
}