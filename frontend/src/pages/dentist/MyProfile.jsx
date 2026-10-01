import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import api from '../../api/axios'
import WeeklyHours from '../../components/WeeklyHours'
import { getInitials } from '../../utils/names'

export default function MyProfile() {
  const [profile, setProfile] = useState(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')

  useEffect(() => {
    api
      .get('/dentist/profile')
      .then((response) => setProfile(response.data.data))
      .catch(() => setError('We could not load your profile. Please try again later.'))
      .finally(() => setLoading(false))
  }, [])

  return (
    <div className="min-h-screen bg-teal-50 px-4 py-8">
      <div className="mx-auto max-w-3xl">
        <Link to="/dentist" className="text-sm text-teal-700 underline">
          &larr; Back to dashboard
        </Link>

        {loading && <p className="mt-8 text-gray-600">Loading your profile...</p>}

        {error && (
          <p className="mt-8 rounded bg-red-50 p-3 text-red-600" role="alert">
            {error}
          </p>
        )}

        {profile && (
          <div className="mt-6 rounded-lg bg-white p-6 shadow">
            {!profile.is_active && (
              <p className="mb-4 rounded bg-amber-50 p-3 text-sm text-amber-800" role="status">
                Your profile is currently hidden from patients, so they cannot book with you.
                Please contact the clinic administrator.
              </p>
            )}

            <div className="flex items-center gap-4">
              <div
                className="flex h-20 w-20 shrink-0 items-center justify-center rounded-full bg-teal-100 text-2xl font-semibold text-teal-700"
                aria-hidden="true"
              >
                {getInitials(profile.name)}
              </div>

              <div>
                <h1 className="text-2xl font-bold text-teal-700">{profile.name}</h1>
                <p className="text-gray-500">{profile.specialization}</p>
              </div>
            </div>

            <dl className="mt-6 divide-y divide-gray-100 text-sm">
              <div className="flex justify-between py-2">
                <dt className="font-medium text-gray-700">Email</dt>
                <dd className="text-gray-900">{profile.email}</dd>
              </div>
              <div className="flex justify-between py-2">
                <dt className="font-medium text-gray-700">Phone</dt>
                <dd className="text-gray-900">{profile.phone ?? 'Not provided'}</dd>
              </div>
              <div className="flex justify-between py-2">
                <dt className="font-medium text-gray-700">License number</dt>
                <dd className="text-gray-900">{profile.license_number}</dd>
              </div>
            </dl>

            {profile.biography && (
              <section className="mt-6">
                <h2 className="text-lg font-semibold text-gray-900">About</h2>
                <p className="mt-2 text-gray-600">{profile.biography}</p>
              </section>
            )}

            <section className="mt-6">
              <h2 className="text-lg font-semibold text-gray-900">My weekly hours</h2>
              <WeeklyHours schedules={profile.schedules} />
            </section>

            <p className="mt-6 text-sm text-gray-500">
              To change these details or your working hours, please contact the clinic administrator.
            </p>
          </div>
        )}
      </div>
    </div>
  )
}