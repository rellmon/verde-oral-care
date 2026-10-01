import { useState } from 'react'

export default function NotesForm({ appointment, error, submitting, onSubmit, onCancel }) {
  const [notes, setNotes] = useState(appointment.dentist_notes ?? '')

  function handleSubmit(event) {
    event.preventDefault()
    onSubmit(notes)
  }

  return (
    <form onSubmit={handleSubmit} className="space-y-4">
      <p className="text-sm text-gray-600">
        These notes are private to the clinic. Patients cannot see them.
      </p>

      {error && (
        <p className="rounded bg-red-50 p-2 text-sm text-red-600" role="alert">
          {error}
        </p>
      )}

      <div>
        <label htmlFor="dentist_notes" className="block text-sm font-medium text-gray-700">
          Notes
        </label>
        <textarea
          id="dentist_notes"
          rows={6}
          maxLength={2000}
          value={notes}
          onChange={(event) => setNotes(event.target.value)}
          className="mt-1 w-full rounded border border-gray-300 px-3 py-2"
        />
      </div>

      <div className="flex justify-end gap-2">
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
          {submitting ? 'Saving...' : 'Save notes'}
        </button>
      </div>
    </form>
  )
}