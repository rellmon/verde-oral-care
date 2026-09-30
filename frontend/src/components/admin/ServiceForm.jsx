import { useState } from 'react'

export default function ServiceForm({ service, errors, generalError, submitting, onSubmit, onCancel }) {
  const [form, setForm] = useState({
    name: service?.name ?? '',
    description: service?.description ?? '',
    price: service?.price ?? '',
    duration_minutes: service?.duration_minutes ?? '',
    is_active: service?.is_active ?? true,
  })

  function handleChange(event) {
    const { name, value, type, checked } = event.target
    setForm({ ...form, [name]: type === 'checkbox' ? checked : value })
  }

  function handleSubmit(event) {
    event.preventDefault()
    onSubmit({
      ...form,
      duration_minutes: Number(form.duration_minutes),
    })
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
          Service name
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
        <label htmlFor="description" className="block text-sm font-medium text-gray-700">
          Description (optional)
        </label>
        <textarea
          id="description"
          name="description"
          rows={3}
          value={form.description}
          onChange={handleChange}
          className="mt-1 w-full rounded border border-gray-300 px-3 py-2"
        />
        {fieldError('description')}
      </div>

      <div className="grid grid-cols-2 gap-4">
        <div>
          <label htmlFor="price" className="block text-sm font-medium text-gray-700">
            Price (PHP)
          </label>
          <input
            id="price"
            name="price"
            type="number"
            step="0.01"
            min="0"
            value={form.price}
            onChange={handleChange}
            required
            className="mt-1 w-full rounded border border-gray-300 px-3 py-2"
          />
          {fieldError('price')}
        </div>

        <div>
          <label htmlFor="duration_minutes" className="block text-sm font-medium text-gray-700">
            Duration (minutes)
          </label>
          <input
            id="duration_minutes"
            name="duration_minutes"
            type="number"
            min="5"
            step="5"
            value={form.duration_minutes}
            onChange={handleChange}
            required
            className="mt-1 w-full rounded border border-gray-300 px-3 py-2"
          />
          {fieldError('duration_minutes')}
        </div>
      </div>

      <label className="flex items-center gap-2 text-sm text-gray-700">
        <input
          type="checkbox"
          name="is_active"
          checked={form.is_active}
          onChange={handleChange}
        />
        Active (available for booking)
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