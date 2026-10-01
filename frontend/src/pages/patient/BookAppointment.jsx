import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import api from '../../api/axios'
import { formatLongDate, formatTime } from '../../utils/time'

// Today's date as YYYY-MM-DD in the browser's local time, used as the earliest pickable date.
function getTodayString() {
  return new Date().toLocaleDateString('en-CA')
}

export default function BookAppointment() {
  const [services, setServices] = useState([])
  const [dentists, setDentists] = useState([])
  const [loading, setLoading] = useState(true)
  const [loadError, setLoadError] = useState('')

  const [serviceId, setServiceId] = useState('')
  const [dentistId, setDentistId] = useState('')
  const [date, setDate] = useState('')

  const [slots, setSlots] = useState([])
  const [slotsLoading, setSlotsLoading] = useState(false)
  const [slotsError, setSlotsError] = useState('')
  const [selectedSlot, setSelectedSlot] = useState(null)
  // Increasing this number makes the slots effect run again (used after a failed booking).
  const [slotsRefreshKey, setSlotsRefreshKey] = useState(0)

  const [reason, setReason] = useState('')
  const [submitting, setSubmitting] = useState(false)
  const [bookingErrors, setBookingErrors] = useState({})
  const [bookingGeneralError, setBookingGeneralError] = useState('')
  const [booked, setBooked] = useState(null)

  const choicesComplete = Boolean(serviceId && dentistId && date)

  // Every error except the reason one is shown together in a banner above the button.
  const otherBookingErrors = Object.entries(bookingErrors)
    .filter(([field]) => field !== 'reason')
    .flatMap(([, messages]) => messages)

  // Load the services and dentists for the dropdowns.
  useEffect(() => {
    Promise.all([api.get('/services'), api.get('/dentists')])
      .then(([servicesResponse, dentistsResponse]) => {
        setServices(servicesResponse.data.data)
        setDentists(dentistsResponse.data.data)
      })
      .catch(() => setLoadError('We could not load the booking form. Please try again later.'))
      .finally(() => setLoading(false))
  }, [])

  // Whenever service, dentist or date changes (or a refresh is requested), ask Laravel for the slots.
  useEffect(() => {
    if (!serviceId || !dentistId || !date) {
      return
    }

    let ignore = false
    setSlotsLoading(true)
    setSlotsError('')

    api
      .get(`/dentists/${dentistId}/availability`, {
        params: { service_id: serviceId, date },
      })
      .then((response) => {
        if (!ignore) setSlots(response.data.data)
      })
      .catch((err) => {
        if (!ignore) {
          setSlots([])
          setSlotsError(
            err.response?.data?.errors?.date?.[0] ?? 'We could not load the available times.'
          )
        }
      })
      .finally(() => {
        if (!ignore) setSlotsLoading(false)
      })

    // Cleanup: if the inputs change again before this request finishes, ignore its result.
    return () => {
      ignore = true
    }
  }, [serviceId, dentistId, date, slotsRefreshKey])

  // Changing any choice clears the selected slot and old booking errors.
  function handleChange(setter) {
    return (event) => {
      setter(event.target.value)
      setSelectedSlot(null)
      setBookingErrors({})
      setBookingGeneralError('')
    }
  }

  async function handleBook(event) {
    event.preventDefault()
    setSubmitting(true)
    setBookingErrors({})
    setBookingGeneralError('')

    try {
      const response = await api.post('/appointments', {
        dentist_id: Number(dentistId),
        service_id: Number(serviceId),
        appointment_date: date,
        start_time: selectedSlot.start_time,
        reason,
      })
      setBooked(response.data.data)
    } catch (err) {
      if (err.response?.status === 422) {
        const errors = err.response.data.errors
        setBookingErrors(errors)

        // The slot was taken (or became invalid): drop the selection and reload the times.
        if (errors.start_time) {
          setSelectedSlot(null)
          setSlotsRefreshKey((key) => key + 1)
        }
      } else {
        setBookingGeneralError('Something went wrong. Please try again.')
      }
    } finally {
      setSubmitting(false)
    }
  }

  function handleBookAnother() {
    setBooked(null)
    setSelectedSlot(null)
    setReason('')
    setBookingErrors({})
    setBookingGeneralError('')
    setSlotsRefreshKey((key) => key + 1) // reload so the slot we just booked disappears
  }

  if (booked) {
    return (
      <div className="min-h-screen bg-teal-50 px-4 py-8">
        <div className="mx-auto max-w-2xl rounded-lg bg-white p-6 shadow">
          <h1 className="text-2xl font-bold text-teal-700">Appointment requested</h1>
          <p className="mt-2 text-gray-600">
            Your appointment has been booked and is waiting for the clinic to confirm it.
          </p>

          <dl className="mt-6 divide-y divide-gray-100 text-sm">
            <div className="flex justify-between py-2">
              <dt className="font-medium text-gray-700">Service</dt>
              <dd className="text-gray-900">{booked.service.name}</dd>
            </div>
            <div className="flex justify-between py-2">
              <dt className="font-medium text-gray-700">Dentist</dt>
              <dd className="text-gray-900">{booked.dentist.name}</dd>
            </div>
            <div className="flex justify-between py-2">
              <dt className="font-medium text-gray-700">Date</dt>
              <dd className="text-gray-900">{formatLongDate(booked.appointment_date)}</dd>
            </div>
            <div className="flex justify-between py-2">
              <dt className="font-medium text-gray-700">Time</dt>
              <dd className="text-gray-900">
                {formatTime(booked.start_time)} to {formatTime(booked.end_time)}
              </dd>
            </div>
            <div className="flex justify-between py-2">
              <dt className="font-medium text-gray-700">Status</dt>
              <dd className="capitalize text-gray-900">{booked.status}</dd>
            </div>
          </dl>

          <div className="mt-6 flex flex-wrap gap-3">
            <button
              onClick={handleBookAnother}
              className="rounded bg-teal-600 px-4 py-2 text-white hover:bg-teal-700"
            >
              Book another appointment
            </button>
            <Link
              to="/patient"
              className="rounded border border-teal-600 px-4 py-2 text-teal-700 hover:bg-teal-50"
            >
              Back to dashboard
            </Link>
          </div>
        </div>
      </div>
    )
  }

  return (
    <div className="min-h-screen bg-teal-50 px-4 py-8">
      <div className="mx-auto max-w-2xl">
        <Link to="/patient" className="text-sm text-teal-700 underline">
          &larr; Back to dashboard
        </Link>

        <h1 className="mt-4 text-3xl font-bold text-teal-700">Book an Appointment</h1>
        <p className="mt-1 text-gray-600">Choose a service, a dentist and a date to see open times.</p>

        {loading && <p className="mt-8 text-gray-600">Loading...</p>}

        {loadError && (
          <p className="mt-8 rounded bg-red-50 p-3 text-red-600" role="alert">
            {loadError}
          </p>
        )}

        {!loading && !loadError && (
          <div className="mt-6 space-y-4 rounded-lg bg-white p-6 shadow">
            <div>
              <label htmlFor="service" className="block text-sm font-medium text-gray-700">
                Service
              </label>
              <select
                id="service"
                value={serviceId}
                onChange={handleChange(setServiceId)}
                className="mt-1 w-full rounded border border-gray-300 px-3 py-2"
              >
                <option value="">Select a service</option>
                {services.map((service) => (
                  <option key={service.id} value={service.id}>
                    {service.name} ({service.duration_minutes} min)
                  </option>
                ))}
              </select>
            </div>

            <div>
              <label htmlFor="dentist" className="block text-sm font-medium text-gray-700">
                Dentist
              </label>
              <select
                id="dentist"
                value={dentistId}
                onChange={handleChange(setDentistId)}
                className="mt-1 w-full rounded border border-gray-300 px-3 py-2"
              >
                <option value="">Select a dentist</option>
                {dentists.map((dentist) => (
                  <option key={dentist.id} value={dentist.id}>
                    {dentist.name} - {dentist.specialization}
                  </option>
                ))}
              </select>
            </div>

            <div>
              <label htmlFor="date" className="block text-sm font-medium text-gray-700">
                Date
              </label>
              <input
                id="date"
                type="date"
                min={getTodayString()}
                value={date}
                onChange={handleChange(setDate)}
                className="mt-1 w-full rounded border border-gray-300 px-3 py-2"
              />
            </div>

            {choicesComplete && (
              <section aria-live="polite">
                <h2 className="text-lg font-semibold text-gray-900">Available times</h2>

                {slotsLoading && <p className="mt-2 text-gray-600">Checking availability...</p>}

                {slotsError && (
                  <p className="mt-2 rounded bg-red-50 p-3 text-sm text-red-600" role="alert">
                    {slotsError}
                  </p>
                )}

                {!slotsLoading && !slotsError && slots.length === 0 && (
                  <p className="mt-2 text-gray-600">
                    No times are available on this day. Please try another date or dentist.
                  </p>
                )}

                {!slotsLoading && slots.length > 0 && (
                  <div className="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-3 md:grid-cols-4">
                    {slots.map((slot) => {
                      const isSelected = selectedSlot?.start_time === slot.start_time

                      return (
                        <button
                          key={slot.start_time}
                          type="button"
                          aria-pressed={isSelected}
                          onClick={() => setSelectedSlot(slot)}
                          className={`rounded border px-3 py-2 text-sm ${
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

            {otherBookingErrors.length > 0 && (
              <div className="rounded bg-red-50 p-3 text-sm text-red-600" role="alert">
                {otherBookingErrors.map((message) => (
                  <p key={message}>{message}</p>
                ))}
              </div>
            )}

            {bookingGeneralError && (
              <p className="rounded bg-red-50 p-3 text-sm text-red-600" role="alert">
                {bookingGeneralError}
              </p>
            )}

            {selectedSlot && (
              <form onSubmit={handleBook} className="space-y-4 border-t border-gray-100 pt-4">
                <p className="rounded bg-teal-50 p-3 text-sm text-teal-800">
                  Selected: {formatLongDate(date)}, {formatTime(selectedSlot.start_time)} to{' '}
                  {formatTime(selectedSlot.end_time)}
                </p>

                <div>
                  <label htmlFor="reason" className="block text-sm font-medium text-gray-700">
                    Reason for your visit
                  </label>
                  <textarea
                    id="reason"
                    rows={3}
                    maxLength={1000}
                    value={reason}
                    onChange={(event) => setReason(event.target.value)}
                    required
                    className="mt-1 w-full rounded border border-gray-300 px-3 py-2"
                  />
                  {bookingErrors.reason && (
                    <p className="mt-1 text-sm text-red-600" role="alert">
                      {bookingErrors.reason[0]}
                    </p>
                  )}
                </div>

                <button
                  type="submit"
                  disabled={submitting}
                  className="w-full rounded bg-teal-600 px-4 py-2 text-white hover:bg-teal-700 disabled:opacity-60"
                >
                  {submitting ? 'Booking...' : 'Book appointment'}
                </button>
              </form>
            )}
          </div>
        )}
      </div>
    </div>
  )
}