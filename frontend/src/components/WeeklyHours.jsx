import { formatTime } from '../utils/time'

// Display order: Monday first, Sunday last. Numbers match day_of_week in the database.
const WEEK_DAYS = [
  { number: 1, label: 'Monday' },
  { number: 2, label: 'Tuesday' },
  { number: 3, label: 'Wednesday' },
  { number: 4, label: 'Thursday' },
  { number: 5, label: 'Friday' },
  { number: 6, label: 'Saturday' },
  { number: 0, label: 'Sunday' },
]

export default function WeeklyHours({ schedules }) {
  // Look each day up by number. A day with no schedule row is closed.
  const scheduleByDay = {}
  schedules.forEach((schedule) => {
    scheduleByDay[schedule.day_of_week] = schedule
  })

  return (
    <ul className="mt-2 divide-y divide-gray-100">
      {WEEK_DAYS.map((day) => {
        const schedule = scheduleByDay[day.number]

        return (
          <li key={day.number} className="flex justify-between py-2 text-sm">
            <span className="font-medium text-gray-700">{day.label}</span>
            <span className={schedule ? 'text-gray-900' : 'text-gray-400'}>
              {schedule
                ? `${formatTime(schedule.start_time)} - ${formatTime(schedule.end_time)}`
                : 'Closed'}
            </span>
          </li>
        )
      })}
    </ul>
  )
}