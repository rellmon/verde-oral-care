import { useState } from 'react'

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

const DEFAULT_START = '09:00'
const DEFAULT_END = '17:00'

export default function ScheduleForm({ schedules, errors, generalError, submitting, onSubmit, onCancel }) {
  const [rows, setRows] = useState(() =>
    WEEK_DAYS.map((day) => {
      const existing = schedules.find((schedule) => schedule.day_of_week === day.number)
      return {
        day_of_week: day.number,
        label: day.label,
        enabled: Boolean(existing),
        start_time: existing?.start_time ?? DEFAULT_START,
        end_time: existing?.end_time ?? DEFAULT_END,
      }
    })
  )

  function updateRow(dayNumber, changes) {
    setRows(rows.map((row) => (row.day_of_week === dayNumber ? { ...row, ...changes } : row)))
  }

  function handleSubmit(event) {
    event.preventDefault()
    const schedulesToSave = rows
      .filter((row) => row.enabled)
      .map(({ day_of_week, start_time, end_time }) => ({ day_of_week, start_time, end_time }))
    onSubmit(schedulesToSave)
  }

  // Laravel reports errors by position in the list we sent, e.g. "schedules.1.end_time".
  // Only ticked days are sent, so we map each row to its position among the ticked days.
  const enabledDays = rows.filter((row) => row.enabled).map((row) => row.day_of_week)

  function rowErrors(dayNumber) {
    const index = enabledDays.indexOf(dayNumber)
    if (index === -1) return []
    return ['day_of_week', 'start_time', 'end_time'].flatMap(
      (field) => errors?.[`schedules.${index}.${field}`] ?? []
    )
  }

  return (
    <form onSubmit={handleSubmit}>
      {generalError && (
        <p className="mb-3 rounded bg-red-50 p-2 text-sm text-red-600" role="alert">
          {generalError}
        </p>
      )}

      {errors?.schedules && (
        <p className="mb-3 rounded bg-red-50 p-2 text-sm text-red-600" role="alert">
          {errors.schedules[0]}
        </p>
      )}

      <p className="mb-2 text-sm text-gray-600">
        Tick the days this dentist works. Unticked days are closed.
      </p>

      <ul className="divide-y divide-gray-100">
        {rows.map((row) => (
          <li key={row.day_of_week} className="flex flex-wrap items-center gap-x-3 gap-y-1 py-3">
            <label className="flex w-28 items-center gap-2 text-sm font-medium text-gray-700">
              <input
                type="checkbox"
                checked={row.enabled}
                onChange={(event) => updateRow(row.day_of_week, { enabled: event.target.checked })}
              />
              {row.label}
            </label>

            {row.enabled ? (
              <>
                <input
                  type="time"
                  aria-label={`${row.label} start time`}
                  value={row.start_time}
                  onChange={(event) => updateRow(row.day_of_week, { start_time: event.target.value })}
                  required
                  className="rounded border border-gray-300 px-2 py-1 text-sm"
                />
                <span className="text-sm text-gray-500">to</span>
                <input
                  type="time"
                  aria-label={`${row.label} end time`}
                  value={row.end_time}
                  onChange={(event) => updateRow(row.day_of_week, { end_time: event.target.value })}
                  required
                  className="rounded border border-gray-300 px-2 py-1 text-sm"
                />
              </>
            ) : (
              <span className="text-sm text-gray-400">Closed</span>
            )}

            {rowErrors(row.day_of_week).map((message) => (
              <p key={message} className="basis-full text-sm text-red-600" role="alert">
                {message}
              </p>
            ))}
          </li>
        ))}
      </ul>

      <div className="flex justify-end gap-2 pt-4">
        <button
          type="button"
          onClick={onCancel}
          className="rounded border border-gray-300 px-4 py-2 text-gray-700 hover:bg-gray-50"
        >
          Cancel
        </button>
        <button
          type="submit"
          disabled={submitting}
          className="rounded bg-teal-600 px-4 py-2 text-white hover:bg-teal-700 disabled:opacity-60"
        >
          {submitting ? 'Saving...' : 'Save schedule'}
        </button>
      </div>
    </form>
  )
}