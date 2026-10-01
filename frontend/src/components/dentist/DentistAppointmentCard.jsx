import { formatLongDate, formatTime } from '../../utils/time'
import StatusBadge from '../StatusBadge'

// Label and look of each status button. Which ones appear comes from the API (available_statuses).
const ACTIONS = {
  confirmed: {
    label: 'Confirm',
    classes: 'bg-teal-600 text-white hover:bg-teal-700',
  },
  completed: {
    label: 'Mark completed',
    classes: 'bg-teal-600 text-white hover:bg-teal-700',
  },
  no_show: {
    label: 'Mark no-show',
    classes: 'border border-gray-300 text-gray-700 hover:bg-gray-50',
  },
  cancelled: {
    label: 'Cancel appointment',
    classes: 'border border-red-300 text-red-600 hover:bg-red-50',
  },
}

function getAge(dateString) {
  const birth = new Date(`${dateString}T00:00`)
  const today = new Date()
  const hadBirthday =
    today.getMonth() > birth.getMonth() ||
    (today.getMonth() === birth.getMonth() && today.getDate() >= birth.getDate())
  return today.getFullYear() - birth.getFullYear() - (hadBirthday ? 0 : 1)
}

export default function DentistAppointmentCard({ appointment, busy, onChangeStatus, onEditNotes }) {
  const { patient, service } = appointment

  return (
    <article className="rounded-lg bg-white p-5 shadow">
      <div className="flex flex-wrap items-start justify-between gap-2">
        <div>
          <p className="text-lg font-semibold text-teal-700">
            {formatTime(appointment.start_time)} to {formatTime(appointment.end_time)}
          </p>
          <p className="text-sm text-gray-600">{formatLongDate(appointment.appointment_date)}</p>
        </div>
        <StatusBadge status={appointment.status} />
      </div>

      <div className="mt-4 text-sm">
        <p className="font-medium text-gray-900">{patient.name}</p>
        <p className="text-gray-600">
          {patient.phone ?? 'No phone number'}
          {patient.date_of_birth && ` | Age ${getAge(patient.date_of_birth)}`}
        </p>
        <p className="mt-2 text-gray-700">
          {service.name} ({service.duration_minutes} min)
        </p>
        {appointment.reason && <p className="mt-1 text-gray-500">Reason: {appointment.reason}</p>}
      </div>

      {appointment.dentist_notes && (
        <p className="mt-3 whitespace-pre-line rounded bg-gray-50 p-3 text-sm text-gray-700">
          <span className="font-medium">Notes: </span>
          {appointment.dentist_notes}
        </p>
      )}

      <div className="mt-4 flex flex-wrap items-center gap-2">
        {appointment.available_statuses.map((status) => (
          <button
            key={status}
            onClick={() => onChangeStatus(appointment, status)}
            disabled={busy}
            className={`rounded px-3 py-1.5 text-sm disabled:opacity-60 ${ACTIONS[status].classes}`}
          >
            {ACTIONS[status].label}
          </button>
        ))}

        <button
          onClick={() => onEditNotes(appointment)}
          className="text-sm text-teal-700 underline"
        >
          {appointment.dentist_notes ? 'Edit notes' : 'Add notes'}
        </button>
      </div>
    </article>
  )
}