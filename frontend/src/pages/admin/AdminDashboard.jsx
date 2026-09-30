import { Link } from 'react-router-dom'
import { useAuth } from '../../context/AuthContext'

export default function AdminDashboard() {
  const { user, logout } = useAuth()

  return (
    <div className="min-h-screen bg-teal-50 p-6">
      <div className="mx-auto max-w-3xl space-y-4">
        <h1 className="text-2xl font-bold text-teal-700">Admin Dashboard</h1>
        <p className="text-gray-700">Welcome, {user.name}.</p>
        <Link
          to="/admin/services"
          className="inline-block rounded border border-teal-600 px-4 py-2 text-teal-700 hover:bg-teal-100"
        >
          Manage services
        </Link>
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