import { useCallback, useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import api from '../../api/axios'
import DentistForm from '../../components/admin/DentistForm'
import ScheduleForm from '../../components/admin/ScheduleForm'
import Modal from '../../components/Modal'

export default function AdminDentists() {
  const [dentists, setDentists] = useState([])
  const [loading, setLoading] = useState(true)
  const [loadError, setLoadError] = useState('')
  const [notice, setNotice] = useState(null)

  // null = modal closed, 'new' = adding, or a dentist object = editing
  const [editing, setEditing] = useState(null)
  const [formErrors, setFormErrors] = useState({})
  const [formGeneralError, setFormGeneralError] = useState('')
  const [saving, setSaving] = useState(false)

  // Schedule modal: the dentist being edited, their saved schedule, and form feedback
  const [scheduleDentist, setScheduleDentist] = useState(null)
  const [scheduleData, setScheduleData] = useState(null)
  const [scheduleLoadError, setScheduleLoadError] = useState('')
  const [scheduleErrors, setScheduleErrors] = useState({})
  const [scheduleGeneralError, setScheduleGeneralError] = useState('')
  const [scheduleSaving, setScheduleSaving] = useState(false)

  const loadDentists = useCallback(async () => {
    try {
      const response = await api.get('/admin/dentists')
      setDentists(response.data.data)
      setLoadError('')
    } catch {
      setLoadError('Could not load dentists. Please try again.')
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => {
    loadDentists()
  }, [loadDentists])

  function openForm(dentist) {
    setFormErrors({})
    setFormGeneralError('')
    setEditing(dentist)
  }

  function closeForm() {
    setEditing(null)
  }

  async function handleSave(data) {
    setSaving(true)
    setFormErrors({})
    setFormGeneralError('')

    try {
      if (editing === 'new') {
        await api.post('/admin/dentists', data)
        setNotice({ type: 'success', text: 'Dentist created.' })
      } else {
        await api.put(`/admin/dentists/${editing.id}`, data)
        setNotice({ type: 'success', text: 'Dentist updated.' })
      }
      setEditing(null)
      await loadDentists()
    } catch (err) {
      if (err.response?.status === 422) {
        setFormErrors(err.response.data.errors)
      } else {
        setFormGeneralError('Something went wrong. Please try again.')
      }
    } finally {
      setSaving(false)
    }
  }

  async function openSchedule(dentist) {
    setScheduleErrors({})
    setScheduleGeneralError('')
    setScheduleLoadError('')
    setScheduleData(null)
    setScheduleDentist(dentist)

    try {
      const response = await api.get(`/admin/dentists/${dentist.id}/schedules`)
      setScheduleData(response.data.data)
    } catch {
      setScheduleLoadError('Could not load the schedule. Please try again.')
    }
  }

  function closeSchedule() {
    setScheduleDentist(null)
    setScheduleData(null)
  }

  async function handleScheduleSave(schedules) {
    setScheduleSaving(true)
    setScheduleErrors({})
    setScheduleGeneralError('')

    try {
      await api.put(`/admin/dentists/${scheduleDentist.id}/schedules`, { schedules })
      setNotice({ type: 'success', text: `Schedule updated for ${scheduleDentist.name}.` })
      closeSchedule()
    } catch (err) {
      if (err.response?.status === 422) {
        setScheduleErrors(err.response.data.errors)
      } else {
        setScheduleGeneralError('Something went wrong. Please try again.')
      }
    } finally {
      setScheduleSaving(false)
    }
  }

  return (
    <div className="min-h-screen bg-teal-50 px-4 py-8">
      <div className="mx-auto max-w-5xl">
        <Link to="/admin" className="text-sm text-teal-700 underline">
          &larr; Back to dashboard
        </Link>

        <div className="mt-4 flex items-center justify-between">
          <h1 className="text-3xl font-bold text-teal-700">Manage Dentists</h1>
          <button
            onClick={() => openForm('new')}
            className="rounded bg-teal-600 px-4 py-2 text-white hover:bg-teal-700"
          >
            Add dentist
          </button>
        </div>

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

        {loading && <p className="mt-8 text-gray-600">Loading dentists...</p>}

        {loadError && (
          <p className="mt-8 rounded bg-red-50 p-3 text-red-600" role="alert">
            {loadError}
          </p>
        )}

        {!loading && !loadError && dentists.length === 0 && (
          <p className="mt-8 text-gray-600">No dentists yet. Add your first one.</p>
        )}

        {dentists.length > 0 && (
          <div className="mt-6 overflow-x-auto rounded-lg bg-white shadow">
            <table className="min-w-full text-left text-sm">
              <thead className="bg-gray-50 text-gray-600">
                <tr>
                  <th className="px-4 py-3">Name</th>
                  <th className="px-4 py-3">Email</th>
                  <th className="px-4 py-3">Specialization</th>
                  <th className="px-4 py-3">License no.</th>
                  <th className="px-4 py-3">Status</th>
                  <th className="px-4 py-3 text-right">Actions</th>
                </tr>
              </thead>
              <tbody>
                {dentists.map((dentist) => (
                  <tr key={dentist.id} className="border-t border-gray-100">
                    <td className="px-4 py-3 font-medium text-gray-900">{dentist.name}</td>
                    <td className="px-4 py-3">{dentist.email}</td>
                    <td className="px-4 py-3">{dentist.specialization}</td>
                    <td className="px-4 py-3">{dentist.license_number}</td>
                    <td className="px-4 py-3">
                      <span
                        className={`rounded-full px-2 py-1 text-xs font-medium ${
                          dentist.is_active
                            ? 'bg-green-100 text-green-700'
                            : 'bg-gray-200 text-gray-600'
                        }`}
                      >
                        {dentist.is_active ? 'Active' : 'Inactive'}
                      </span>
                    </td>
                    <td className="space-x-3 px-4 py-3 text-right whitespace-nowrap">
                      <button
                        onClick={() => openForm(dentist)}
                        className="text-teal-700 underline"
                      >
                        Edit
                      </button>
                      <button
                        onClick={() => openSchedule(dentist)}
                        className="text-teal-700 underline"
                      >
                        Schedule
                      </button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>

      {editing && (
        <Modal title={editing === 'new' ? 'Add dentist' : 'Edit dentist'} onClose={closeForm}>
          <DentistForm
            dentist={editing === 'new' ? null : editing}
            errors={formErrors}
            generalError={formGeneralError}
            submitting={saving}
            onSubmit={handleSave}
            onCancel={closeForm}
          />
        </Modal>
      )}

      {scheduleDentist && (
        <Modal title={`Schedule: ${scheduleDentist.name}`} onClose={closeSchedule}>
          {scheduleLoadError && (
            <div>
              <p className="rounded bg-red-50 p-2 text-sm text-red-600" role="alert">
                {scheduleLoadError}
              </p>
              <div className="flex justify-end pt-4">
                <button
                  onClick={closeSchedule}
                  className="rounded border border-gray-300 px-4 py-2 text-gray-700 hover:bg-gray-50"
                >
                  Close
                </button>
              </div>
            </div>
          )}

          {!scheduleLoadError && scheduleData === null && (
            <p className="text-gray-600">Loading schedule...</p>
          )}

          {scheduleData !== null && (
            <ScheduleForm
              schedules={scheduleData}
              errors={scheduleErrors}
              generalError={scheduleGeneralError}
              submitting={scheduleSaving}
              onSubmit={handleScheduleSave}
              onCancel={closeSchedule}
            />
          )}
        </Modal>
      )}
    </div>
  )
}