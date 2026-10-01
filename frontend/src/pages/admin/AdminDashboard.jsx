import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import api from '../../api/axios'
import StatCard from '../../components/admin/StatCard'
import StatusBadge from '../../components/StatusBadge'
import { useAuth } from '../../context/AuthContext'

export default function AdminDashboard() {
  const { user, logout } = useAuth()

  const [stats, setStats] = useState(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')

  useEffect(() => {
    api
      .get('/admin/stats')
      .then((response) => setStats(response.data.data))
      .catch(() => setError('We could not load the dashboard numbers. Please try again later.'))
      .finally(() => setLoading(false))
  }, [])

  return (
    <div className="min-h-screen bg-teal-50 px-4 py-8">
      <div className="mx-auto max-w-4xl">
        <div className="flex flex-wrap items-center justify-between gap-3">
          <div>
            <h1 className="text-2xl font-bold text-teal-700">Admin Dashboard</h1>
            <p className="text-gray-700">Welcome, {user.name}.</p>
          </div>
          <button
            onClick={logout}
            className="rounded border border-teal-600 px-4 py-2 text-teal-700 hover:bg-teal-100"
          >
            Log out
          </button>
        </div>

        <nav aria-label="Management" className="mt-6 flex flex-wrap gap-2">
          <Link
            to="/admin/appointments"
            className="rounded border border-teal-600 px-4 py-2 text-teal-700 hover:bg-teal-100"
          >
            Appointments
          </Link>
          <Link
            to="/admin/services"
            className="rounded border border-teal-600 px-4 py-2 text-teal-700 hover:bg-teal-100"
          >
            Manage services
          </Link>
          <Link
            to="/admin/dentists"
            className="rounded border border-teal-600 px-4 py-2 text-teal-700 hover:bg-teal-100"
          >
            Manage dentists
          </Link>
        </nav>

        {loading && <p className="mt-8 text-gray-600">Loading dashboard...</p>}

        {error && (
          <p className="mt-8 rounded bg-red-50 p-3 text-red-600" role="alert">
            {error}
          </p>
        )}

        {stats && (
          <>
            <section aria-label="Key numbers" className="mt-8 grid grid-cols-2 gap-4 sm:grid-cols-3">
              <StatCard label="Appointments today" value={stats.appointments_today} />
              <StatCard
                label="Pending confirmation"
                value={stats.pending_confirmation}
                highlight={stats.pending_confirmation > 0}
              />
              <StatCard label="Next 7 days" value={stats.upcoming_week} />
              <StatCard label="Patients" value={stats.total_patients} />
              <StatCard label="Active dentists" value={stats.active_dentists} />
              <StatCard label="Active services" value={stats.active_services} />
            </section>

            <section className="mt-8 rounded-lg bg-white p-5 shadow">
              <h2 className="text-lg font-semibold text-gray-900">All appointments by status</h2>
              <ul className="mt-3 divide-y divide-gray-100">
                {Object.entries(stats.appointments_by_status).map(([status, total]) => (
                  <li key={status} className="flex items-center justify-between py-2">
                    <StatusBadge status={status} />
                    <span className="text-sm font-medium text-gray-900">{total}</span>
                  </li>
                ))}
              </ul>
            </section>
          </>
        )}
      </div>
    </div>
  )
}