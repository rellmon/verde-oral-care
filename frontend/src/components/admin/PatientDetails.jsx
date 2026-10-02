import { useEffect, useState } from 'react'
import api from '../../api/axios'
import { formatShortDate, formatTime } from '../../utils/time'
import StatusBadge from '../StatusBadge'

function capitalize(text) {
  return text.charAt(0).toUpperCase() + text.slice(1)
}

export default function PatientDetails({ patientId, onClose }) {
  const [patient, setPatient] = useState(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')

  useEffect(() => {
    let ignore = false

    api
      .get(`/admin/patients/${patientId}`)
      .then((response) => {
        if (!ignore) setPatient(response.data.data)
      })
      .catch(() => {
        if (!ignore) setError('Could not load this patient. Please try again.')
      })
      .finally(() => {
        if (!ignore) setLoading(false)
      })

    return () => {
      ignore = true
    }
  }, [patientId])

  return (
    <div>
      {loading && <p className="text-gray-600">Loading patient...</p>}

      {error && (
        <p className="rounded bg-red-50 p-2 text-sm text-red-600" role="alert">
          {error}
        </p>
      )}

      {patient && (
        <>
          <dl className="divide-y divide-gray-100 text-sm">
            <div className="flex justify-between gap-4 py-2">
              <dt className="font-medium text-gray-700">Email</dt>
              <dd className="text-right text-gray-900">{patient.email}</dd>
            </div>
            <div className="flex justify-between gap-4 py-2">
              <dt className="font-medium text-gray-700">Phone</dt>
              <dd className="text-right text-gray-900">{patient.phone ?? 'Not provided'}</dd>
            </div>
            <div className="flex justify-between gap-4 py-2">
              <dt className="font-medium text-gray-700">Date of birth</dt>
              <dd className="text-right text-gray-900">
                {patient.date_of_birth ? formatShortDate(patient.date_of_birth) : 'Not provided'}
              </dd>
            </div>
            <div className="flex justify-between gap-4 py-2">
              <dt className="font-medium text-gray-700">Gender</dt>
              <dd className="text-right text-gray-900">
                {patient.gender ? capitalize(patient.gender) : 'Not provided'}
              </dd>
            </div>
            <div className="flex justify-between gap-4 py-2">
              <dt className="font-medium text-gray-700">Address</dt>
              <dd className="text-right text-gray-900">{patient.address ?? 'Not provided'}</dd>
            </div>
            <div className="flex justify-between gap-4 py-2">
              <dt className="font-medium text-gray-700">Emergency contact</dt>
              <dd className="text-right text-gray-900">
                {patient.emergency_contact_name || patient.emergency_contact_phone ? (
                  <>
                    {patient.emergency_contact_name}
                    {patient.emergency_contact_phone && (
                      <span className="block text-gray-500">{patient.emergency_contact_phone}</span>
                    )}
                  </>
                ) : (
                  'Not provided'
                )}
              </dd>
            </div>
          </dl>

          <h3 className="mt-6 font-semibold text-gray-900">
            Appointment history ({patient.appointments_count})
          </h3>

          {patient.appointments.length === 0 ? (
            <p className="mt-2 text-sm text-gray-600">This patient has no appointments yet.</p>
          ) : (
            <ul className="mt-2 divide-y divide-gray-100">
              {patient.appointments.map((appointment) => (
                <li key={appointment.id} className="py-3 text-sm">
                  <div className="flex items-start justify-between gap-2">
                    <div>
                      <p className="font-medium text-gray-900">
                        {formatShortDate(appointment.appointment_date)},{' '}
                        {formatTime(appointment.start_time)}
                      </p>
                      <p className="text-gray-600">
                        {appointment.service.name} with {appointment.dentist.name}
                      </p>
                    </div>
                    <StatusBadge status={appointment.status} />
                  </div>
                  {appointment.reason && (
                    <p className="mt-1 text-xs text-gray-500">Reason: {appointment.reason}</p>
                  )}
                </li>
              ))}
            </ul>
          )}
        </>
      )}

      <div className="mt-6 flex justify-end">
        <button
          onClick={onClose}
          className="rounded border border-gray-300 px-4 py-2 text-gray-700 hover:bg-gray-50"
        >
          Close
        </button>
      </div>
    </div>
  )
}