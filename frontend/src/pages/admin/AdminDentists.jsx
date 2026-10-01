import { useCallback, useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import api from '../../api/axios'

export default function AdminDentists() {
  const [dentists, setDentists] = useState([])
  const [loading, setLoading] = useState(true)
  const [loadError, setLoadError] = useState('')

  const loadDentists = useCallback(async () => {
    try {
      const response = await api.get('/admin/dentists')
      setDentists(response.data.data)
      setLoadError('')
    } catch {
      setLoadError('Could not load dentists. Please try again.')
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => {
    loadDentists()
  }, [loadDentists])

  return (
    <div className="min-h-screen bg-teal-50 px-4 py-8">
      <div className="mx-auto max-w-5xl">
        <Link to="/admin" className="text-sm text-teal-700 underline">
          &larr; Back to dashboard
        </Link>

        <div className="mt-4 flex items-center justify-between">
          <h1 className="text-3xl font-bold text-teal-700">Manage Dentists</h1>
        </div>

        {loading && <p className="mt-8 text-gray-600">Loading dentists...</p>}

        {loadError && (
          <p className="mt-8 rounded bg-red-50 p-3 text-red-600" role="alert">
            {loadError}
          </p>
        )}

        {!loading && !loadError && dentists.length === 0 && (
          <p className="mt-8 text-gray-600">No dentists yet.</p>
        )}

        {dentists.length > 0 && (
          <div className="mt-6 overflow-x-auto rounded-lg bg-white shadow">
            <table className="min-w-full text-left text-sm">
              <thead className="bg-gray-50 text-gray-600">
                <tr>
                  <th className="px-4 py-3">Name</th>
                  <th className="px-4 py-3">Email</th>
                  <th className="px-4 py-3">Specialization</th>
                  <th className="px-4 py-3">License no.</th>
                  <th className="px-4 py-3">Status</th>
                </tr>
              </thead>
              <tbody>
                {dentists.map((dentist) => (
                  <tr key={dentist.id} className="border-t border-gray-100">
                    <td className="px-4 py-3 font-medium text-gray-900">{dentist.name}</td>
                    <td className="px-4 py-3">{dentist.email}</td>
                    <td className="px-4 py-3">{dentist.specialization}</td>
                    <td className="px-4 py-3">{dentist.license_number}</td>
                    <td className="px-4 py-3">
                      <span
                        className={`rounded-full px-2 py-1 text-xs font-medium ${
                          dentist.is_active
                            ? 'bg-green-100 text-green-700'
                            : 'bg-gray-200 text-gray-600'
                        }`}
                      >
                        {dentist.is_active ? 'Active' : 'Inactive'}
                      </span>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>
    </div>
  )
}