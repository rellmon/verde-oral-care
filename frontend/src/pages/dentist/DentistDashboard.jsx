import { useCallback, useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import api from '../../api/axios'
import ConfirmDialog from '../../components/ConfirmDialog'
import DentistAppointmentCard from '../../components/dentist/DentistAppointmentCard'
import NotesForm from '../../components/dentist/NotesForm'
import Modal from '../../components/Modal'
import { useAuth } from '../../context/AuthContext'
import { formatLongDate, formatTime } from '../../utils/time'

const TABS = [
  { key: 'today', label: 'Today', empty: 'No appointments today.' },
  { key: 'upcoming', label: 'Upcoming', empty: 'No upcoming appointments.' },
  { key: 'history', label: 'History', empty: 'Past appointments will appear here.' },
]

// Wording of the confirmation dialog for each status that needs one.
const CHANGE_DIALOGS = {
  completed: {
    title: 'Mark as completed?',
    confirmLabel: 'Yes, mark completed',
    note: 'This cannot be undone.',
  },
  no_show: {
    title: 'Mark as no-show?',
    confirmLabel: 'Yes, mark no-show',
    note: 'This cannot be undone.',
  },
  cancelled: {
    title: 'Cancel this appointment?',
    confirmLabel: 'Yes, cancel it',
    note: 'The time slot becomes available to other patients again. This cannot be undone.',
  },
}

export default function DentistDashboard() {
  const { user, logout } = useAuth()

  const [lists, setLists] = useState({ today: [], upcoming: [], history: [] })
  const [activeTab, setActiveTab] = useState('today')
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [notice, setNotice] = useState(null)

  // A status change waiting for confirmation: { appointment, status } (null = dialog closed)
  const [pendingChange, setPendingChange] = useState(null)
  const [changing, setChanging] = useState(false)
  const [changeError, setChangeError] = useState('')

  // The appointment whose notes are being edited (null = modal closed)
  const [notesTarget, setNotesTarget] = useState(null)
  const [savingNotes, setSavingNotes] = useState(false)
  const [notesError, setNotesError] = useState('')

  const loadAppointments = useCallback(async () => {
    try {
      const [today, upcoming, history] = await Promise.all(
        TABS.map((tab) => api.get('/dentist/appointments', { params: { type: tab.key } }))
      )
      setLists({
        today: today.data.data,
        upcoming: upcoming.data.data,
        history: history.data.data,
      })
      setError('')
    } catch {
      setError('We could not load your appointments. Please try again later.')
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => {
    loadAppointments()
  }, [loadAppointments])

  async function changeStatus(appointment, status) {
    setChanging(true)
    setChangeError('')

    try {
      await api.patch(`/dentist/appointments/${appointment.id}`, { status })
      setNotice({ type: 'success', text: 'The appointment was updated.' })
      setPendingChange(null)
    } catch (err) {
      // Laravel explains why (for example "has not started yet"). Show it where the dentist is looking.
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
      await loadAppointments()
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

  async function handleSaveNotes(notes) {
    setSavingNotes(true)
    setNotesError('')

    try {
      await api.patch(`/dentist/appointments/${notesTarget.id}`, { dentist_notes: notes })
      setNotice({ type: 'success', text: 'Notes saved.' })
      setNotesTarget(null)
      await loadAppointments()
    } catch (err) {
      setNotesError(
        err.response?.data?.errors?.dentist_notes?.[0] ?? 'Something went wrong. Please try again.'
      )
    } finally {
      setSavingNotes(false)
    }
  }

  const activeList = lists[activeTab]
  const activeTabInfo = TABS.find((tab) => tab.key === activeTab)
  const dialogInfo = pendingChange ? CHANGE_DIALOGS[pendingChange.status] : null

  return (
    <div className="min-h-screen bg-teal-50 px-4 py-8">
      <div className="mx-auto max-w-3xl">
        <div className="flex flex-wrap items-center justify-between gap-3">
          <div>
            <h1 className="text-2xl font-bold text-teal-700">Dentist Dashboard</h1>
            <p className="text-gray-700">Welcome, {user.name}.</p>
            <p className="text-sm text-gray-500">
              Today is {formatLongDate(new Date().toLocaleDateString('en-CA'))}.
            </p>
          </div>
          <button
            onClick={logout}
            className="rounded border border-teal-600 px-4 py-2 text-teal-700 hover:bg-teal-100"
          >
            Log out
          </button>
        </div>

        <Link to="/dentist/profile" className="mt-4 inline-block text-sm text-teal-700 underline">
          View my profile and working hours
        </Link>

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

        {loading && <p className="mt-8 text-gray-600">Loading your appointments...</p>}

        {error && (
          <p className="mt-8 rounded bg-red-50 p-3 text-red-600" role="alert">
            {error}
          </p>
        )}

        {!loading && !error && (
          <>
            <div role="tablist" className="mt-8 flex gap-2 border-b border-gray-200">
              {TABS.map((tab) => (
                <button
                  key={tab.key}
                  role="tab"
                  aria-selected={activeTab === tab.key}
                  onClick={() => setActiveTab(tab.key)}
                  className={`-mb-px border-b-2 px-4 py-2 text-sm font-medium ${
                    activeTab === tab.key
                      ? 'border-teal-600 text-teal-700'
                      : 'border-transparent text-gray-500 hover:text-gray-700'
                  }`}
                >
                  {tab.label} ({lists[tab.key].length})
                </button>
              ))}
            </div>

            {activeList.length === 0 ? (
              <p className="mt-6 text-gray-600">{activeTabInfo.empty}</p>
            ) : (
              <div className="mt-6 space-y-3">
                {activeList.map((appointment) => (
                  <DentistAppointmentCard
                    key={appointment.id}
                    appointment={appointment}
                    busy={changing}
                    onChangeStatus={handleChangeStatus}
                    onEditNotes={(target) => {
                      setNotesError('')
                      setNotesTarget(target)
                    }}
                  />
                ))}
              </div>
            )}
          </>
        )}
      </div>

      {pendingChange && (
        <ConfirmDialog
          title={dialogInfo.title}
          message={`${pendingChange.appointment.patient.name}, ${
            pendingChange.appointment.service.name
          } on ${formatLongDate(pendingChange.appointment.appointment_date)} at ${formatTime(
            pendingChange.appointment.start_time
          )}. ${dialogInfo.note}`}
          confirmLabel={dialogInfo.confirmLabel}
          cancelLabel="Go back"
          busy={changing}
          error={changeError}
          onConfirm={() => changeStatus(pendingChange.appointment, pendingChange.status)}
          onCancel={() => setPendingChange(null)}
        />
      )}

      {notesTarget && (
        <Modal title={`Notes: ${notesTarget.patient.name}`} onClose={() => setNotesTarget(null)}>
          <NotesForm
            appointment={notesTarget}
            error={notesError}
            submitting={savingNotes}
            onSubmit={handleSaveNotes}
            onCancel={() => setNotesTarget(null)}
          />
        </Modal>
      )}
    </div>
  )
}