import { useEffect, useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import api from '../api/axios'
import { getInitials } from '../utils/names'
import { formatTime } from '../utils/time'

// Display order: Monday first, Sunday last. Numbers match day_of_week in the database.
const WEEK_DAYS = [
  { number: 1, label: 'Monday' },
  { number: 2, label: 'Tuesday' },
  { number: 3, label: 'Wednesday' },
  { number: 4, label: 'Thursday' },
  { number: 5, label: 'Friday' },
  { number: 6, label: 'Saturday' },
  { number: 0, label: 'Sunday' },
]

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

  const scheduleByDay = {}
  dentist?.schedules?.forEach((schedule) => {
    scheduleByDay[schedule.day_of_week] = schedule
  })

  return (
    <div className="min-h-screen bg-teal-50 px-4 py-8">
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
              <ul className="mt-2 divide-y divide-gray-100">
                {WEEK_DAYS.map((day) => {
                  const schedule = scheduleByDay[day.number]

                  return (
                    <li key={day.number} className="flex justify-between py-2 text-sm">
                      <span className="font-medium text-gray-700">{day.label}</span>
                      <span className={schedule ? 'text-gray-900' : 'text-gray-400'}>
                        {schedule
                          ? `${formatTime(schedule.start_time)} - ${formatTime(schedule.end_time)}`
                          : 'Closed'}
                      </span>
                    </li>
                  )
                })}
              </ul>
            </section>
          </div>
        )}
      </div>
    </div>
  )
}