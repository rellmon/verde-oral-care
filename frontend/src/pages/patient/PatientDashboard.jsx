import { useAuth } from '../../context/AuthContext'

export default function PatientDashboard() {
  const { user, logout } = useAuth()

  return (
    <div className="min-h-screen bg-teal-50 p-6">
      <div className="mx-auto max-w-3xl space-y-4">
        <h1 className="text-2xl font-bold text-teal-700">Patient Dashboard</h1>
        <p className="text-gray-700">Welcome, {user.name}.</p>
        <button
          onClick={logout}
          className="rounded bg-teal-600 px-4 py-2 text-white hover:bg-teal-700"
        >
          Log out
        </button>
      </div>
    </div>
  )
}