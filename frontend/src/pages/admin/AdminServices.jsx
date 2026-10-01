import { useCallback, useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import api from '../../api/axios'
import ServiceForm from '../../components/admin/ServiceForm'
import Modal from '../../components/Modal'
import ConfirmDialog from '../../components/ConfirmDialog'

const pesoFormatter = new Intl.NumberFormat('en-PH', {
  style: 'currency',
  currency: 'PHP',
})

export default function AdminServices() {
  const [services, setServices] = useState([])
  const [loading, setLoading] = useState(true)
  const [loadError, setLoadError] = useState('')
  const [notice, setNotice] = useState(null)

  // null = modal closed, 'new' = adding, or a service object = editing
  const [editing, setEditing] = useState(null)
  const [formErrors, setFormErrors] = useState({})
  const [formGeneralError, setFormGeneralError] = useState('')
  const [saving, setSaving] = useState(false)

    // The service the admin is about to delete (null = dialog closed)
  const [deleteTarget, setDeleteTarget] = useState(null)
  const [deleting, setDeleting] = useState(false)
  const [deleteError, setDeleteError] = useState('')

  const loadServices = useCallback(async () => {
    try {
      const response = await api.get('/admin/services')
      setServices(response.data.data)
      setLoadError('')
    } catch {
      setLoadError('Could not load services. Please try again.')
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => {
    loadServices()
  }, [loadServices])

  function openForm(service) {
    setFormErrors({})
    setFormGeneralError('')
    setEditing(service)
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
        await api.post('/admin/services', data)
        setNotice({ type: 'success', text: 'Service created.' })
      } else {
        await api.put(`/admin/services/${editing.id}`, data)
        setNotice({ type: 'success', text: 'Service updated.' })
      }
      setEditing(null)
      await loadServices()
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

  async function handleToggleActive(service) {
    try {
      await api.put(`/admin/services/${service.id}`, {
        name: service.name,
        description: service.description,
        price: service.price,
        duration_minutes: service.duration_minutes,
        is_active: !service.is_active,
      })
      setNotice({
        type: 'success',
        text: `${service.name} is now ${service.is_active ? 'inactive' : 'active'}.`,
      })
      await loadServices()
    } catch {
      setNotice({ type: 'error', text: 'Could not change the status. Please try again.' })
    }
  }

  function openDeleteDialog(service) {
    setDeleteError('')
    setDeleteTarget(service)
  }

  async function handleConfirmDelete() {
    setDeleting(true)
    setDeleteError('')

    try {
      await api.delete(`/admin/services/${deleteTarget.id}`)
      setNotice({ type: 'success', text: `${deleteTarget.name} was deleted.` })
      setDeleteTarget(null)
      await loadServices()
    } catch (err) {
      setDeleteError(err.response?.data?.message || 'Could not delete the service.')
    } finally {
      setDeleting(false)
    }
  }

  return (
    <div className="min-h-screen bg-teal-50 px-4 py-8">
      <div className="mx-auto max-w-5xl">
        <Link to="/admin" className="text-sm text-teal-700 underline">
          &larr; Back to dashboard
        </Link>

        <div className="mt-4 flex items-center justify-between">
          <h1 className="text-3xl font-bold text-teal-700">Manage Services</h1>
          <button
            onClick={() => openForm('new')}
            className="rounded bg-teal-600 px-4 py-2 text-white hover:bg-teal-700"
          >
            Add service
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

        {loading && <p className="mt-8 text-gray-600">Loading services...</p>}

        {loadError && (
          <p className="mt-8 rounded bg-red-50 p-3 text-red-600" role="alert">
            {loadError}
          </p>
        )}

        {!loading && !loadError && services.length === 0 && (
          <p className="mt-8 text-gray-600">No services yet. Add your first one.</p>
        )}

        {services.length > 0 && (
          <div className="mt-6 overflow-x-auto rounded-lg bg-white shadow">
            <table className="min-w-full text-left text-sm">
              <thead className="bg-gray-50 text-gray-600">
                <tr>
                  <th className="px-4 py-3">Name</th>
                  <th className="px-4 py-3">Price</th>
                  <th className="px-4 py-3">Duration</th>
                  <th className="px-4 py-3">Status</th>
                  <th className="px-4 py-3 text-right">Actions</th>
                </tr>
              </thead>
              <tbody>
                {services.map((service) => (
                  <tr key={service.id} className="border-t border-gray-100">
                    <td className="px-4 py-3 font-medium text-gray-900">{service.name}</td>
                    <td className="px-4 py-3">{pesoFormatter.format(Number(service.price))}</td>
                    <td className="px-4 py-3">{service.duration_minutes} min</td>
                    <td className="px-4 py-3">
                      <span
                        className={`rounded-full px-2 py-1 text-xs font-medium ${
                          service.is_active
                            ? 'bg-green-100 text-green-700'
                            : 'bg-gray-200 text-gray-600'
                        }`}
                      >
                        {service.is_active ? 'Active' : 'Inactive'}
                      </span>
                    </td>
                    <td className="space-x-3 px-4 py-3 text-right whitespace-nowrap">
                      <button
                        onClick={() => openForm(service)}
                        className="text-teal-700 underline"
                      >
                        Edit
                      </button>
                      <button
                        onClick={() => handleToggleActive(service)}
                        className="text-teal-700 underline"
                      >
                        {service.is_active ? 'Deactivate' : 'Activate'}
                      </button>
                      <button
                        onClick={() => openDeleteDialog(service)}
                        className="text-red-600 underline"
                      >
                        Delete
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
        <Modal title={editing === 'new' ? 'Add service' : 'Edit service'} onClose={closeForm}>
          <ServiceForm
            service={editing === 'new' ? null : editing}
            errors={formErrors}
            generalError={formGeneralError}
            submitting={saving}
            onSubmit={handleSave}
            onCancel={closeForm}
          />
        </Modal>
      )}

      {deleteTarget && (
        <ConfirmDialog
          title="Delete this service?"
          message={`"${deleteTarget.name}" will be permanently deleted. This cannot be undone. If you only want to hide it from patients, use Deactivate instead.`}
          confirmLabel="Yes, delete"
          cancelLabel="Keep service"
          busy={deleting}
          error={deleteError}
          onConfirm={handleConfirmDelete}
          onCancel={() => setDeleteTarget(null)}
        />
      )}
    </div>
  )
}