import { formatLongDate, formatTime } from '../utils/time'
import StatusBadge from './StatusBadge'

export default function AppointmentCard({ appointment }) {
  return (
    <article className="rounded-lg bg-white p-5 shadow">
      <div className="flex flex-wrap items-start justify-between gap-2">
        <div>
          <h3 className="font-semibold text-teal-700">{appointment.service.name}</h3>
          <p className="text-sm text-gray-600">with {appointment.dentist.name}</p>
        </div>
        <StatusBadge status={appointment.status} />
      </div>

      <p className="mt-3 text-sm font-medium text-gray-900">
        {formatLongDate(appointment.appointment_date)}
      </p>
      <p className="text-sm text-gray-600">
        {formatTime(appointment.start_time)} to {formatTime(appointment.end_time)}
      </p>

      {appointment.reason && (
        <p className="mt-3 text-sm text-gray-500">Reason: {appointment.reason}</p>
      )}
    </article>
  )
}