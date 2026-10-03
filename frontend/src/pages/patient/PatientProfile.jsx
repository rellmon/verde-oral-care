import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import api from '../../api/axios'
import { useAuth } from '../../context/AuthContext'

// The API sends null for empty fields, but form inputs need strings.
function toFormValues(profile) {
  return {
    name: profile.name ?? '',
    email: profile.email ?? '',
    phone: profile.phone ?? '',
    date_of_birth: profile.date_of_birth ?? '',
    gender: profile.gender ?? '',
    address: profile.address ?? '',
    emergency_contact_name: profile.emergency_contact_name ?? '',
    emergency_contact_phone: profile.emergency_contact_phone ?? '',
  }
}

function getTodayString() {
  return new Date().toLocaleDateString('en-CA')
}

export default function PatientProfile() {
  const { updateUser } = useAuth()

  const [form, setForm] = useState(null)
  const [loading, setLoading] = useState(true)
  const [loadError, setLoadError] = useState('')

  const [saving, setSaving] = useState(false)
  const [errors, setErrors] = useState({})
  const [generalError, setGeneralError] = useState('')
  const [notice, setNotice] = useState('')

  useEffect(() => {
    api
      .get('/patient/profile')
      .then((response) => setForm(toFormValues(response.data.data)))
      .catch(() => setLoadError('We could not load your profile. Please try again later.'))
      .finally(() => setLoading(false))
  }, [])

  function handleChange(event) {
    const { name, value } = event.target
    setForm({ ...form, [name]: value })
    setNotice('')
  }

  async function handleSubmit(event) {
    event.preventDefault()
    setSaving(true)
    setErrors({})
    setGeneralError('')
    setNotice('')

    try {
      // The email is shown but not sent: it cannot be changed here.
      const editableFields = { ...form }
      delete editableFields.email
      const response = await api.put('/patient/profile', editableFields)
      const saved = response.data.data

      setForm(toFormValues(saved))
      updateUser({ name: saved.name, phone: saved.phone })
      setNotice('Your profile was updated.')
    } catch (err) {
      if (err.response?.status === 422) {
        setErrors(err.response.data.errors)
      } else {
        setGeneralError('Something went wrong. Please try again.')
      }
    } finally {
      setSaving(false)
    }
  }

  function fieldError(name) {
    return errors[name] && (
      <p className="mt-1 text-sm text-red-600" role="alert">
        {errors[name][0]}
      </p>
    )
  }

  return (
    <div className="min-h-screen bg-teal-50 px-4 py-8">
      <div className="mx-auto max-w-2xl">
        <Link to="/patient" className="text-sm text-teal-700 underline">
          &larr; Back to dashboard
        </Link>

        <h1 className="mt-4 text-3xl font-bold text-teal-700">My Profile</h1>
        <p className="mt-1 text-gray-600">Keep your details up to date so the clinic can reach you.</p>

        {loading && <p className="mt-8 text-gray-600">Loading your profile...</p>}

        {loadError && (
          <p className="mt-8 rounded bg-red-50 p-3 text-red-600" role="alert">
            {loadError}
          </p>
        )}

        {form && (
          <form onSubmit={handleSubmit} className="mt-6 space-y-4 rounded-lg bg-white p-6 shadow">
            {notice && (
              <p role="status" className="rounded bg-green-50 p-3 text-sm text-green-700">
                {notice}
              </p>
            )}

            {generalError && (
              <p className="rounded bg-red-50 p-3 text-sm text-red-600" role="alert">
                {generalError}
              </p>
            )}

            <div>
              <label htmlFor="name" className="block text-sm font-medium text-gray-700">
                Full name
              </label>
              <input
                id="name"
                name="name"
                value={form.name}
                onChange={handleChange}
                required
                className="mt-1 w-full rounded border border-gray-300 px-3 py-2"
              />
              {fieldError('name')}
            </div>

            <div>
              <label htmlFor="email" className="block text-sm font-medium text-gray-700">
                Email (cannot be changed here)
              </label>
              <input
                id="email"
                value={form.email}
                disabled
                className="mt-1 w-full rounded border border-gray-200 bg-gray-50 px-3 py-2 text-gray-500"
              />
            </div>

            <div>
              <label htmlFor="phone" className="block text-sm font-medium text-gray-700">
                Phone
              </label>
              <input
                id="phone"
                name="phone"
                maxLength={20}
                value={form.phone}
                onChange={handleChange}
                className="mt-1 w-full rounded border border-gray-300 px-3 py-2"
              />
              {fieldError('phone')}
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
              <div>
                <label htmlFor="date_of_birth" className="block text-sm font-medium text-gray-700">
                  Date of birth
                </label>
                <input
                  id="date_of_birth"
                  name="date_of_birth"
                  type="date"
                  max={getTodayString()}
                  value={form.date_of_birth}
                  onChange={handleChange}
                  className="mt-1 w-full rounded border border-gray-300 px-3 py-2"
                />
                {fieldError('date_of_birth')}
              </div>

              <div>
                <label htmlFor="gender" className="block text-sm font-medium text-gray-700">
                  Gender
                </label>
                <select
                  id="gender"
                  name="gender"
                  value={form.gender}
                  onChange={handleChange}
                  className="mt-1 w-full rounded border border-gray-300 px-3 py-2"
                >
                  <option value="">Prefer not to say</option>
                  <option value="female">Female</option>
                  <option value="male">Male</option>
                  <option value="other">Other</option>
                </select>
                {fieldError('gender')}
              </div>
            </div>

            <div>
              <label htmlFor="address" className="block text-sm font-medium text-gray-700">
                Address
              </label>
              <textarea
                id="address"
                name="address"
                rows={2}
                maxLength={255}
                value={form.address}
                onChange={handleChange}
                className="mt-1 w-full rounded border border-gray-300 px-3 py-2"
              />
              {fieldError('address')}
            </div>

            <fieldset className="rounded border border-gray-200 p-4">
              <legend className="px-1 text-sm font-medium text-gray-700">Emergency contact</legend>

              <div className="grid gap-4 sm:grid-cols-2">
                <div>
                  <label
                    htmlFor="emergency_contact_name"
                    className="block text-sm font-medium text-gray-700"
                  >
                    Name
                  </label>
                  <input
                    id="emergency_contact_name"
                    name="emergency_contact_name"
                    value={form.emergency_contact_name}
                    onChange={handleChange}
                    className="mt-1 w-full rounded border border-gray-300 px-3 py-2"
                  />
                  {fieldError('emergency_contact_name')}
                </div>

                <div>
                  <label
                    htmlFor="emergency_contact_phone"
                    className="block text-sm font-medium text-gray-700"
                  >
                    Phone
                  </label>
                  <input
                    id="emergency_contact_phone"
                    name="emergency_contact_phone"
                    maxLength={20}
                    value={form.emergency_contact_phone}
                    onChange={handleChange}
                    className="mt-1 w-full rounded border border-gray-300 px-3 py-2"
                  />
                  {fieldError('emergency_contact_phone')}
                </div>
              </div>
            </fieldset>

            <div className="flex justify-end">
              <button
                type="submit"
                disabled={saving}
                className="rounded bg-teal-600 px-4 py-2 text-white hover:bg-teal-700 disabled:opacity-60"
              >
                {saving ? 'Saving...' : 'Save changes'}
              </button>
            </div>
          </form>
        )}
      </div>
    </div>
  )
}