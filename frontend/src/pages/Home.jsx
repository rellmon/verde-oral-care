import { Link } from 'react-router-dom'
import { useAuth } from '../context/AuthContext'
import { dashboardPathFor } from '../utils/roles'

export default function Home() {
  const { user, logout } = useAuth()

  return (
    <div className="min-h-screen flex flex-col items-center justify-center gap-4 bg-teal-50">
      <h1 className="text-3xl font-bold text-teal-700">Verde Oral Care</h1>

      <Link to="/services" className="text-teal-700 underline">
        View our services
      </Link>

      {user ? (
        <>
          <p className="text-gray-700">
            Logged in as <strong>{user.name}</strong> ({user.role})
          </p>

          <Link
            to={dashboardPathFor(user.role)}
            className="rounded border border-teal-600 px-4 py-2 text-teal-700 hover:bg-teal-100"
          >
            Go to my dashboard
          </Link>

          <button
            onClick={logout}
            className="rounded bg-teal-600 px-4 py-2 text-white hover:bg-teal-700"
          >
            Log out
          </button>
        </>
      ) : (
        <Link to="/login" className="rounded bg-teal-600 px-4 py-2 text-white hover:bg-teal-700">
          Log in
        </Link>
      )}
    </div>
  )
}