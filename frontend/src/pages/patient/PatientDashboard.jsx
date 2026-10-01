import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import api from '../../api/axios'
import AppointmentCard from '../../components/AppointmentCard'
import { useAuth } from '../../context/AuthContext'

export default function PatientDashboard() {
  const { user, logout } = useAuth()

  const [upcoming, setUpcoming] = useState([])
  const [history, setHistory] = useState([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')

  useEffect(() => {
    Promise.all([
      api.get('/appointments', { params: { type: 'upcoming' } }),
      api.get('/appointments', { params: { type: 'history' } }),
    ])
      .then(([upcomingResponse, historyResponse]) => {
        setUpcoming(upcomingResponse.data.data)
        setHistory(historyResponse.data.data)
      })
      .catch(() => setError('We could not load your appointments. Please try again later.'))
      .finally(() => setLoading(false))
  }, [])

  return (
    <div className="min-h-screen bg-teal-50 px-4 py-8">
      <div className="mx-auto max-w-3xl">
        <div className="flex flex-wrap items-center justify-between gap-3">
          <div>
            <h1 className="text-2xl font-bold text-teal-700">Patient Dashboard</h1>
            <p className="text-gray-700">Welcome, {user.name}.</p>
          </div>
          <button
            onClick={logout}
            className="rounded border border-teal-600 px-4 py-2 text-teal-700 hover:bg-teal-100"
          >
            Log out
          </button>
        </div>

        <Link
          to="/patient/book"
          className="mt-6 inline-block rounded bg-teal-600 px-4 py-2 text-white hover:bg-teal-700"
        >
          Book an appointment
        </Link>

        {loading && <p className="mt-8 text-gray-600">Loading your appointments...</p>}

        {error && (
          <p className="mt-8 rounded bg-red-50 p-3 text-red-600" role="alert">
            {error}
          </p>
        )}

        {!loading && !error && (
          <>
            <section className="mt-8">
              <h2 className="text-xl font-semibold text-gray-900">Upcoming appointments</h2>
              {upcoming.length === 0 ? (
                <p className="mt-2 text-gray-600">You have no upcoming appointments.</p>
              ) : (
                <div className="mt-3 space-y-3">
                  {upcoming.map((appointment) => (
                    <AppointmentCard key={appointment.id} appointment={appointment} />
                  ))}
                </div>
              )}
            </section>

            <section className="mt-8">
              <h2 className="text-xl font-semibold text-gray-900">Appointment history</h2>
              {history.length === 0 ? (
                <p className="mt-2 text-gray-600">Your past appointments will appear here.</p>
              ) : (
                <div className="mt-3 space-y-3">
                  {history.map((appointment) => (
                    <AppointmentCard key={appointment.id} appointment={appointment} />
                  ))}
                </div>
              )}
            </section>
          </>
        )}
      </div>
    </div>
  )
}