import { useEffect, useState } from 'react'
import api from '../../api/axios'
import { formatLongDate, formatTime, getTodayString } from '../../utils/time'

export default function RescheduleForm({ appointment, onSubmit, onCancel }) {
  const [date, setDate] = useState('')
  const [slots, setSlots] = useState([])
  const [slotsLoading, setSlotsLoading] = useState(false)
  const [slotsError, setSlotsError] = useState('')
  const [selectedSlot, setSelectedSlot] = useState(null)
  // Increasing this number reloads the times (used after a failed attempt).
  const [refreshKey, setRefreshKey] = useState(0)

  const [submitting, setSubmitting] = useState(false)
  const [error, setError] = useState('')

  // Whenever the chosen date changes (or a refresh is requested), ask Laravel for the free times.
  useEffect(() => {
    if (!date) {
      return
    }

    let ignore = false
    setSlotsLoading(true)
    setSlotsError('')

    api
      .get(`/admin/appointments/${appointment.id}/slots`, { params: { date } })
      .then((response) => {
        if (!ignore) setSlots(response.data.data)
      })
      .catch((err) => {
        if (!ignore) {
          setSlots([])
          setSlotsError(
            err.response?.data?.errors?.date?.[0] ??
              err.response?.data?.message ??
              'We could not load the available times.'
          )
        }
      })
      .finally(() => {
        if (!ignore) setSlotsLoading(false)
      })

    // Cleanup: if the date changes again before this request finishes, ignore its result.
    return () => {
      ignore = true
    }
  }, [appointment.id, date, refreshKey])

  function handleDateChange(event) {
    setDate(event.target.value)
    setSelectedSlot(null)
    setError('')
  }

  async function handleSubmit(event) {
    event.preventDefault()
    setSubmitting(true)
    setError('')

    // onSubmit returns null when the move worked (the parent then closes this dialog),
    // or a message explaining why it did not.
    const message = await onSubmit({
      appointment_date: date,
      start_time: selectedSlot.start_time,
    })

    if (message) {
      setError(message)
      setSelectedSlot(null)
      setRefreshKey((key) => key + 1) // the times were probably out of date, so reload them
      setSubmitting(false)
    }
  }

  return (
    <form onSubmit={handleSubmit} className="space-y-4">
      <div className="rounded bg-gray-50 p-3 text-sm text-gray-700">
        <p className="font-medium text-gray-900">{appointment.patient.name}</p>
        <p>
          {appointment.service.name} with {appointment.dentist.name}
        </p>
        <p className="mt-1 text-gray-500">
          Currently: {formatLongDate(appointment.appointment_date)},{' '}
          {formatTime(appointment.start_time)} to {formatTime(appointment.end_time)}
        </p>
      </div>

      {error && (
        <p className="rounded bg-red-50 p-2 text-sm text-red-600" role="alert">
          {error}
        </p>
      )}

      <div>
        <label htmlFor="reschedule_date" className="block text-sm font-medium text-gray-700">
          New date
        </label>
        <input
          id="reschedule_date"
          type="date"
          min={getTodayString()}
          value={date}
          onChange={handleDateChange}
          className="mt-1 w-full rounded border border-gray-300 px-3 py-2"
        />
      </div>

      {date && (
        <section aria-live="polite">
          <h3 className="text-sm font-medium text-gray-700">Available times</h3>

          {slotsLoading && <p className="mt-2 text-sm text-gray-600">Checking availability...</p>}

          {slotsError && (
            <p className="mt-2 rounded bg-red-50 p-2 text-sm text-red-600" role="alert">
              {slotsError}
            </p>
          )}

          {!slotsLoading && !slotsError && slots.length === 0 && (
            <p className="mt-2 text-sm text-gray-600">
              No times are available on this day. Please try another date.
            </p>
          )}

          {!slotsLoading && slots.length > 0 && (
            <div className="mt-2 grid grid-cols-3 gap-2">
              {slots.map((slot) => {
                const isSelected = selectedSlot?.start_time === slot.start_time

                return (
                  <button
                    key={slot.start_time}
                    type="button"
                    aria-pressed={isSelected}
                    onClick={() => setSelectedSlot(slot)}
                    className={`rounded border px-2 py-1.5 text-sm ${
                      isSelected
                        ? 'border-teal-600 bg-teal-600 text-white'
                        : 'border-gray-300 text-gray-700 hover:bg-teal-50'
                    }`}
                  >
                    {formatTime(slot.start_time)}
                  </button>
                )
              })}
            </div>
          )}
        </section>
      )}

      {selectedSlot && (
        <p className="rounded bg-teal-50 p-3 text-sm text-teal-800">
          New time: {formatLongDate(date)}, {formatTime(selectedSlot.start_time)} to{' '}
          {formatTime(selectedSlot.end_time)}
        </p>
      )}

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
          disabled={!selectedSlot || submitting}
          className="rounded bg-teal-600 px-4 py-2 text-white hover:bg-teal-700 disabled:opacity-60"
        >
          {submitting ? 'Moving...' : 'Reschedule'}
        </button>
      </div>
    </form>
  )
}