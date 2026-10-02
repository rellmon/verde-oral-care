import { useEffect, useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import api from '../api/axios'
import WeeklyHours from '../components/WeeklyHours'
import { getInitials } from '../utils/names'

export default function DentistProfile() {
  const { id } = useParams()
  const [dentist, setDentist] = useState(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')

  useEffect(() => {
    api
      .get(`/dentists/${id}`)
      .then((response) => setDentist(response.data.data))
      .catch((err) => {
        if (err.response?.status === 404) {
          setError('This dentist could not be found.')
        } else {
          setError('We could not load this dentist. Please try again later.')
        }
      })
      .finally(() => setLoading(false))
  }, [id])

  return (
    <div className="px-4 py-8">
      <div className="mx-auto max-w-3xl">
        <Link to="/dentists" className="text-sm text-teal-700 underline">
          &larr; Back to dentists
        </Link>

        {loading && <p className="mt-8 text-gray-600">Loading dentist...</p>}

        {error && (
          <p className="mt-8 rounded bg-red-50 p-3 text-red-600" role="alert">
            {error}
          </p>
        )}

        {dentist && (
          <div className="mt-6 rounded-lg bg-white p-6 shadow">
            <div className="flex items-center gap-4">
              <div
                className="flex h-20 w-20 shrink-0 items-center justify-center rounded-full bg-teal-100 text-2xl font-semibold text-teal-700"
                aria-hidden="true"
              >
                {getInitials(dentist.name)}
              </div>

              <div>
                <h1 className="text-2xl font-bold text-teal-700">{dentist.name}</h1>
                <p className="text-gray-500">{dentist.specialization}</p>
              </div>
            </div>

            {dentist.biography && (
              <section className="mt-6">
                <h2 className="text-lg font-semibold text-gray-900">About</h2>
                <p className="mt-2 text-gray-600">{dentist.biography}</p>
              </section>
            )}

            <section className="mt-6">
              <h2 className="text-lg font-semibold text-gray-900">Weekly hours</h2>
              <WeeklyHours schedules={dentist.schedules ?? []} />
            </section>
          </div>
        )}
      </div>
    </div>
  )
}