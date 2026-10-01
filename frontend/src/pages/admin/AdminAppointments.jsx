import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import api from '../../api/axios'
import ConfirmDialog from '../../components/ConfirmDialog'
import RescheduleForm from '../../components/admin/RescheduleForm'
import Modal from '../../components/Modal'
import StatusBadge from '../../components/StatusBadge'
import { CHANGE_DIALOGS } from '../../utils/appointmentStatus'
import { formatLongDate, formatShortDate, formatTime } from '../../utils/time'

const STATUS_OPTIONS = [
  { value: 'pending', label: 'Pending' },
  { value: 'confirmed', label: 'Confirmed' },
  { value: 'completed', label: 'Completed' },
  { value: 'cancelled', label: 'Cancelled' },
  { value: 'no_show', label: 'No-show' },
]

// Label and look of each action button. Which ones appear comes from the API (available_statuses).
const ACTION_BUTTONS = {
  confirmed: { label: 'Confirm', classes: 'bg-teal-600 text-white hover:bg-teal-700' },
  completed: { label: 'Complete', classes: 'bg-teal-600 text-white hover:bg-teal-700' },
  no_show: { label: 'No-show', classes: 'border border-gray-300 text-gray-700 hover:bg-gray-50' },
  cancelled: { label: 'Cancel', classes: 'border border-red-300 text-red-600 hover:bg-red-50' },
}

// The filters that are sent to the API only when they have a value.
const FILTER_KEYS = ['status', 'dentist_id', 'date_from', 'date_to']

const EMPTY_FILTERS = { status: '', dentist_id: '', date_from: '', date_to: '', sort: 'desc' }

export default function AdminAppointments() {
  const [filters, setFilters] = useState(EMPTY_FILTERS)
  const [page, setPage] = useState(1)
  // Increasing this number makes the list reload (used after a status change).
  const [refreshKey, setRefreshKey] = useState(0)

  const [appointments, setAppointments] = useState([])
  const [meta, setMeta] = useState(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [notice, setNotice] = useState(null)

  const [dentists, setDentists] = useState([])

  // A status change waiting for confirmation: { appointment, status } (null = dialog closed)
  const [pendingChange, setPendingChange] = useState(null)
  const [changing, setChanging] = useState(false)
  const [changeError, setChangeError] = useState('')

    // The appointment being moved to a new time (null = modal closed)
  const [rescheduleTarget, setRescheduleTarget] = useState(null)

  const hasActiveFilters = FILTER_KEYS.some((key) => filters[key])

  // The dentist dropdown. If this fails the page still works, the dropdown just has no names.
  useEffect(() => {
    api
      .get('/admin/dentists')
      .then((response) => setDentists(response.data.data))
      .catch(() => {})
  }, [])

  // Reload whenever a filter, the page, or the refresh counter changes.
  useEffect(() => {
    let ignore = false
    setLoading(true)
    setError('')

    const params = { page, sort: filters.sort }
    FILTER_KEYS.forEach((key) => {
      if (filters[key]) params[key] = filters[key]
    })

    api
      .get('/admin/appointments', { params })
      .then((response) => {
        if (ignore) return

        const { data, meta: responseMeta } = response.data

        // The last row of the last page changed status, so that page no longer exists. Go back one.
        if (data.length === 0 && responseMeta.current_page > responseMeta.last_page) {
          setPage(responseMeta.last_page)
          return
        }

        setAppointments(data)
        setMeta(responseMeta)
      })
      .catch((err) => {
        if (!ignore) {
          setAppointments([])
          setMeta(null)
          setError(
            err.response?.status === 422
              ? Object.values(err.response.data.errors)[0][0]
              : 'Could not load appointments. Please try again.'
          )
        }
      })
      .finally(() => {
        if (!ignore) setLoading(false)
      })

    // Cleanup: if the inputs change again before this request finishes, ignore its result.
    return () => {
      ignore = true
    }
  }, [filters, page, refreshKey])

  function handleFilterChange(event) {
    const { name, value } = event.target
    setFilters({ ...filters, [name]: value })
    setPage(1) // a new filter always starts from the first page
  }

  function clearFilters() {
    setFilters(EMPTY_FILTERS)
    setPage(1)
  }

  async function changeStatus(appointment, status) {
    setChanging(true)
    setChangeError('')

    try {
      await api.patch(`/admin/appointments/${appointment.id}/status`, { status })
      setNotice({ type: 'success', text: 'The appointment was updated.' })
      setPendingChange(null)
    } catch (err) {
      // Laravel explains why (for example "has not started yet"). Show it where the admin is looking.
      const message =
        err.response?.data?.errors?.status?.[0] ??
        err.response?.data?.message ??
        'Something went wrong. Please try again.'

      if (pendingChange) {
        setChangeError(message)
      } else {
        setNotice({ type: 'error', text: message })
      }
    } finally {
      // Success or failure, reload: after an error the page was probably out of date.
      setRefreshKey((key) => key + 1)
      setChanging(false)
    }
  }

  function handleChangeStatus(appointment, status) {
    setNotice(null)

    // Confirming is routine, so it happens immediately. The other changes are final and ask first.
    if (status === 'confirmed') {
      changeStatus(appointment, status)
      return
    }

    setChangeError('')
    setPendingChange({ appointment, status })
  }

    function openReschedule(appointment) {
    setNotice(null)
    setRescheduleTarget(appointment)
  }

  // Returns null when the move worked, or an error message for the dialog to show.
  async function handleReschedule(payload) {
    try {
      await api.patch(`/admin/appointments/${rescheduleTarget.id}/reschedule`, payload)
      setNotice({ type: 'success', text: 'The appointment was rescheduled.' })
      setRescheduleTarget(null)
      setRefreshKey((key) => key + 1)
      return null
    } catch (err) {
      // After a failure the list may be out of date too, so reload it.
      setRefreshKey((key) => key + 1)

      const errors = err.response?.data?.errors
      return (
        errors?.start_time?.[0] ??
        errors?.appointment_date?.[0] ??
        errors?.status?.[0] ??
        err.response?.data?.message ??
        'Something went wrong. Please try again.'
      )
    }
  }

  const inputClasses = 'mt-1 w-full rounded border border-gray-300 px-3 py-2 text-sm'
  const dialogInfo = pendingChange ? CHANGE_DIALOGS[pendingChange.status] : null

  return (
    <div className="min-h-screen bg-teal-50 px-4 py-8">
      <div className="mx-auto max-w-6xl">
        <Link to="/admin" className="text-sm text-teal-700 underline">
          &larr; Back to dashboard
        </Link>

        <h1 className="mt-4 text-3xl font-bold text-teal-700">Appointments</h1>

        <section
          aria-label="Filters"
          className="mt-6 grid gap-4 rounded-lg bg-white p-4 shadow sm:grid-cols-2 lg:grid-cols-5"
        >
          <div>
            <label htmlFor="status" className="block text-sm font-medium text-gray-700">
              Status
            </label>
            <select
              id="status"
              name="status"
              value={filters.status}
              onChange={handleFilterChange}
              className={inputClasses}
            >
              <option value="">All statuses</option>
              {STATUS_OPTIONS.map((option) => (
                <option key={option.value} value={option.value}>
                  {option.label}
                </option>
              ))}
            </select>
          </div>

          <div>
            <label htmlFor="dentist_id" className="block text-sm font-medium text-gray-700">
              Dentist
            </label>
            <select
              id="dentist_id"
              name="dentist_id"
              value={filters.dentist_id}
              onChange={handleFilterChange}
              className={inputClasses}
            >
              <option value="">All dentists</option>
              {dentists.map((dentist) => (
                <option key={dentist.id} value={dentist.id}>
                  {dentist.name}
                </option>
              ))}
            </select>
          </div>

          <div>
            <label htmlFor="date_from" className="block text-sm font-medium text-gray-700">
              From
            </label>
            <input
              id="date_from"
              name="date_from"
              type="date"
              value={filters.date_from}
              onChange={handleFilterChange}
              className={inputClasses}
            />
          </div>

          <div>
            <label htmlFor="date_to" className="block text-sm font-medium text-gray-700">
              To
            </label>
            <input
              id="date_to"
              name="date_to"
              type="date"
              min={filters.date_from}
              value={filters.date_to}
              onChange={handleFilterChange}
              className={inputClasses}
            />
          </div>

          <div>
            <label htmlFor="sort" className="block text-sm font-medium text-gray-700">
              Order
            </label>
            <select
              id="sort"
              name="sort"
              value={filters.sort}
              onChange={handleFilterChange}
              className={inputClasses}
            >
              <option value="desc">Newest first</option>
              <option value="asc">Oldest first</option>
            </select>
          </div>
        </section>

        {hasActiveFilters && (
          <button onClick={clearFilters} className="mt-3 text-sm text-teal-700 underline">
            Clear filters
          </button>
        )}

        {notice && (
          <p
            role="status"
            className={`mt-4 rounded p-3 text-sm ${
              notice.type === 'success' ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-600'
            }`}
          >
            {notice.text}
          </p>
        )}

        {error && (
          <p className="mt-6 rounded bg-red-50 p-3 text-red-600" role="alert">
            {error}
          </p>
        )}

        {loading && appointments.length === 0 && !error && (
          <p className="mt-6 text-gray-600">Loading appointments...</p>
        )}

        {!loading && !error && appointments.length === 0 && (
          <p className="mt-6 text-gray-600">No appointments match these filters.</p>
        )}

        {appointments.length > 0 && (
          <>
            <div
              className={`mt-6 overflow-x-auto rounded-lg bg-white shadow ${
                loading ? 'opacity-60' : ''
              }`}
            >
              <table className="min-w-full text-left text-sm">
                <thead className="bg-gray-50 text-gray-600">
                  <tr>
                    <th className="px-4 py-3">Date</th>
                    <th className="px-4 py-3">Time</th>
                    <th className="px-4 py-3">Patient</th>
                    <th className="px-4 py-3">Dentist</th>
                    <th className="px-4 py-3">Service</th>
                    <th className="px-4 py-3">Status</th>
                    <th className="px-4 py-3">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  {appointments.map((appointment) => (
                    <tr key={appointment.id} className="border-t border-gray-100">
                      <td className="px-4 py-3 whitespace-nowrap">
                        {formatShortDate(appointment.appointment_date)}
                      </td>
                      <td className="px-4 py-3 whitespace-nowrap">
                        {formatTime(appointment.start_time)} - {formatTime(appointment.end_time)}
                      </td>
                      <td className="px-4 py-3">
                        <p className="font-medium text-gray-900">{appointment.patient.name}</p>
                        <p className="text-xs text-gray-500">
                          {appointment.patient.phone ?? 'No phone number'}
                        </p>
                      </td>
                      <td className="px-4 py-3">{appointment.dentist.name}</td>
                      <td className="px-4 py-3">{appointment.service.name}</td>
                      <td className="px-4 py-3">
                        <StatusBadge status={appointment.status} />
                      </td>
                      <td className="px-4 py-3">
                        <div className="flex flex-wrap gap-2">
                          {appointment.available_statuses.map((status) => (
                            <button
                              key={status}
                              onClick={() => handleChangeStatus(appointment, status)}
                              disabled={changing}
                              className={`rounded px-2.5 py-1 text-xs disabled:opacity-60 ${ACTION_BUTTONS[status].classes}`}
                            >
                              {ACTION_BUTTONS[status].label}
                            </button>
                          ))}
                            {appointment.can_reschedule && (
                            <button
                              onClick={() => openReschedule(appointment)}
                              disabled={changing}
                              className="rounded border border-teal-600 px-2.5 py-1 text-xs text-teal-700 hover:bg-teal-50 disabled:opacity-60"
                            >
                              Reschedule
                            </button>
                            )}
                        </div>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>

            {meta && (
              <nav
                aria-label="Pagination"
                className="mt-4 flex flex-wrap items-center justify-between gap-3"
              >
                <p className="text-sm text-gray-600">
                  Page {meta.current_page} of {meta.last_page} ({meta.total} appointments)
                </p>
                <div className="flex gap-2">
                  <button
                    onClick={() => setPage((current) => current - 1)}
                    disabled={meta.current_page <= 1 || loading}
                    className="rounded border border-gray-300 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50 disabled:opacity-50"
                  >
                    Previous
                  </button>
                  <button
                    onClick={() => setPage((current) => current + 1)}
                    disabled={meta.current_page >= meta.last_page || loading}
                    className="rounded border border-gray-300 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50 disabled:opacity-50"
                  >
                    Next
                  </button>
                </div>
              </nav>
            )}
          </>
        )}
      </div>

        {pendingChange && (
        <ConfirmDialog
          title={dialogInfo.title}
          message={`${pendingChange.appointment.patient.name}, ${
            pendingChange.appointment.service.name
          } with ${pendingChange.appointment.dentist.name} on ${formatLongDate(
            pendingChange.appointment.appointment_date
          )} at ${formatTime(pendingChange.appointment.start_time)}. ${dialogInfo.note}`}
          confirmLabel={dialogInfo.confirmLabel}
          cancelLabel="Go back"
          busy={changing}
          error={changeError}
          onConfirm={() => changeStatus(pendingChange.appointment, pendingChange.status)}
          onCancel={() => setPendingChange(null)}
        />
      )}

      {rescheduleTarget && (
        <Modal title="Reschedule appointment" onClose={() => setRescheduleTarget(null)}>
          <RescheduleForm
            appointment={rescheduleTarget}
            onSubmit={handleReschedule}
            onCancel={() => setRescheduleTarget(null)}
          />
        </Modal>
      )}
    </div>
  )
}