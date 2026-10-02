import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import api from '../../api/axios'
import PatientDetails from '../../components/admin/PatientDetails'
import Modal from '../../components/Modal'

export default function AdminPatients() {
  // searchInput follows every keystroke; search is the value actually sent to the API.
  const [searchInput, setSearchInput] = useState('')
  const [search, setSearch] = useState('')
  const [page, setPage] = useState(1)

  const [patients, setPatients] = useState([])
  const [meta, setMeta] = useState(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')

  // The patient whose details are open (null = dialog closed)
  const [selected, setSelected] = useState(null)

  // Wait until the admin stops typing for 300 ms before searching.
  useEffect(() => {
    const timer = setTimeout(() => {
      setSearch(searchInput.trim())
      setPage(1) // a new search always starts from the first page
    }, 300)

    return () => clearTimeout(timer)
  }, [searchInput])

  // Reload whenever the search or the page changes.
  useEffect(() => {
    let ignore = false
    setLoading(true)
    setError('')

    const params = { page }
    if (search) params.search = search

    api
      .get('/admin/patients', { params })
      .then((response) => {
        if (!ignore) {
          setPatients(response.data.data)
          setMeta(response.data.meta)
        }
      })
      .catch(() => {
        if (!ignore) {
          setPatients([])
          setMeta(null)
          setError('Could not load patients. Please try again.')
        }
      })
      .finally(() => {
        if (!ignore) setLoading(false)
      })

    // Cleanup: if the inputs change again before this request finishes, ignore its result.
    return () => {
      ignore = true
    }
  }, [search, page])

  return (
    <div className="min-h-screen bg-teal-50 px-4 py-8">
      <div className="mx-auto max-w-5xl">
        <Link to="/admin" className="text-sm text-teal-700 underline">
          &larr; Back to dashboard
        </Link>

        <h1 className="mt-4 text-3xl font-bold text-teal-700">Patients</h1>

        <div className="mt-6 flex flex-wrap items-end gap-3">
          <div className="w-full max-w-md">
            <label htmlFor="search" className="block text-sm font-medium text-gray-700">
              Search by name, email or phone
            </label>
            <input
              id="search"
              type="search"
              maxLength={100}
              value={searchInput}
              onChange={(event) => setSearchInput(event.target.value)}
              className="mt-1 w-full rounded border border-gray-300 px-3 py-2"
            />
          </div>
          {searchInput && (
            <button onClick={() => setSearchInput('')} className="pb-2 text-sm text-teal-700 underline">
              Clear search
            </button>
          )}
        </div>

        {error && (
          <p className="mt-6 rounded bg-red-50 p-3 text-red-600" role="alert">
            {error}
          </p>
        )}

        {loading && patients.length === 0 && !error && (
          <p className="mt-6 text-gray-600">Loading patients...</p>
        )}

        {!loading && !error && patients.length === 0 && (
          <p className="mt-6 text-gray-600">
            {search ? 'No patients match your search.' : 'No patients yet.'}
          </p>
        )}

        {patients.length > 0 && (
          <>
            <div
              className={`mt-6 overflow-x-auto rounded-lg bg-white shadow ${
                loading ? 'opacity-60' : ''
              }`}
            >
              <table className="min-w-full text-left text-sm">
                <thead className="bg-gray-50 text-gray-600">
                  <tr>
                    <th className="px-4 py-3">Name</th>
                    <th className="px-4 py-3">Email</th>
                    <th className="px-4 py-3">Phone</th>
                    <th className="px-4 py-3">Appointments</th>
                    <th className="px-4 py-3 text-right">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  {patients.map((patient) => (
                    <tr key={patient.id} className="border-t border-gray-100">
                      <td className="px-4 py-3 font-medium text-gray-900">{patient.name}</td>
                      <td className="px-4 py-3">{patient.email}</td>
                      <td className="px-4 py-3 whitespace-nowrap">
                        {patient.phone ?? 'Not provided'}
                      </td>
                      <td className="px-4 py-3">{patient.appointments_count}</td>
                      <td className="px-4 py-3 text-right">
                        <button
                          onClick={() => setSelected(patient)}
                          className="text-teal-700 underline"
                        >
                          View
                        </button>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>

            {meta && (
              <nav
                aria-label="Pagination"
                className="mt-4 flex flex-wrap items-center justify-between gap-3"
              >
                <p className="text-sm text-gray-600">
                  {meta.total} {meta.total === 1 ? 'patient' : 'patients'}
                  {meta.last_page > 1 && ` (page ${meta.current_page} of ${meta.last_page})`}
                </p>

                {meta.last_page > 1 && (
                  <div className="flex gap-2">
                    <button
                      onClick={() => setPage((current) => current - 1)}
                      disabled={meta.current_page <= 1 || loading}
                      className="rounded border border-gray-300 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50 disabled:opacity-50"
                    >
                      Previous
                    </button>
                    <button
                      onClick={() => setPage((current) => current + 1)}
                      disabled={meta.current_page >= meta.last_page || loading}
                      className="rounded border border-gray-300 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50 disabled:opacity-50"
                    >
                      Next
                    </button>
                  </div>
                )}
              </nav>
            )}
          </>
        )}
      </div>

      {selected && (
        <Modal title={selected.name} onClose={() => setSelected(null)}>
          <PatientDetails patientId={selected.id} onClose={() => setSelected(null)} />
        </Modal>
      )}
    </div>
  )
}