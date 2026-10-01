import { useState } from 'react'

export default function DentistForm({ dentist, errors, generalError, submitting, onSubmit, onCancel }) {
  const isEditing = Boolean(dentist)

  const [form, setForm] = useState({
    name: dentist?.name ?? '',
    email: dentist?.email ?? '',
    phone: dentist?.phone ?? '',
    password: '',
    license_number: dentist?.license_number ?? '',
    specialization: dentist?.specialization ?? '',
    biography: dentist?.biography ?? '',
    is_active: dentist?.is_active ?? true,
  })

  function handleChange(event) {
    const { name, value, type, checked } = event.target
    setForm({ ...form, [name]: type === 'checkbox' ? checked : value })
  }

  function handleSubmit(event) {
    event.preventDefault()
    onSubmit(form)
  }

  function fieldError(name) {
    return errors?.[name] && (
      <p className="mt-1 text-sm text-red-600" role="alert">
        {errors[name][0]}
      </p>
    )
  }

  return (
    <form onSubmit={handleSubmit} className="space-y-4">
      {generalError && (
        <p className="rounded bg-red-50 p-2 text-sm text-red-600" role="alert">
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
          Email (used to log in)
        </label>
        <input
          id="email"
          name="email"
          type="email"
          value={form.email}
          onChange={handleChange}
          required
          className="mt-1 w-full rounded border border-gray-300 px-3 py-2"
        />
        {fieldError('email')}
      </div>

      <div>
        <label htmlFor="phone" className="block text-sm font-medium text-gray-700">
          Phone (optional)
        </label>
        <input
          id="phone"
          name="phone"
          value={form.phone}
          onChange={handleChange}
          className="mt-1 w-full rounded border border-gray-300 px-3 py-2"
        />
        {fieldError('phone')}
      </div>

      <div>
        <label htmlFor="password" className="block text-sm font-medium text-gray-700">
          {isEditing ? 'New password (leave blank to keep current)' : 'Password'}
        </label>
        <input
          id="password"
          name="password"
          type="password"
          value={form.password}
          onChange={handleChange}
          required={!isEditing}
          minLength={8}
          autoComplete="new-password"
          className="mt-1 w-full rounded border border-gray-300 px-3 py-2"
        />
        {fieldError('password')}
      </div>

      <div className="grid grid-cols-2 gap-4">
        <div>
          <label htmlFor="license_number" className="block text-sm font-medium text-gray-700">
            License number
          </label>
          <input
            id="license_number"
            name="license_number"
            value={form.license_number}
            onChange={handleChange}
            required
            className="mt-1 w-full rounded border border-gray-300 px-3 py-2"
          />
          {fieldError('license_number')}
        </div>

        <div>
          <label htmlFor="specialization" className="block text-sm font-medium text-gray-700">
            Specialization
          </label>
          <input
            id="specialization"
            name="specialization"
            value={form.specialization}
            onChange={handleChange}
            required
            className="mt-1 w-full rounded border border-gray-300 px-3 py-2"
          />
          {fieldError('specialization')}
        </div>
      </div>

      <div>
        <label htmlFor="biography" className="block text-sm font-medium text-gray-700">
          Biography (optional)
        </label>
        <textarea
          id="biography"
          name="biography"
          rows={4}
          value={form.biography}
          onChange={handleChange}
          className="mt-1 w-full rounded border border-gray-300 px-3 py-2"
        />
        {fieldError('biography')}
      </div>

      <label className="flex items-center gap-2 text-sm text-gray-700">
        <input
          type="checkbox"
          name="is_active"
          checked={form.is_active}
          onChange={handleChange}
        />
        Active (visible to patients and bookable)
      </label>

      <div className="flex justify-end gap-2 pt-2">
        <button
          type="button"
          onClick={onCancel}
          className="rounded border border-gray-300 px-4 py-2 text-gray-700 hover:bg-gray-50"
        >
          Cancel
        </button>
        <button
          type="submit"
          disabled={submitting}
          className="rounded bg-teal-600 px-4 py-2 text-white hover:bg-teal-700 disabled:opacity-60"
        >
          {submitting ? 'Saving...' : 'Save'}
        </button>
      </div>
    </form>
  )
}