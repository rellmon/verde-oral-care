import { Navigate, Route, Routes } from 'react-router-dom'
import ProtectedRoute from './components/ProtectedRoute'
import { useAuth } from './context/AuthContext'
import Home from './pages/Home'
import Login from './pages/Login'
import Register from './pages/Register'
import AdminDashboard from './pages/admin/AdminDashboard'
import DentistDashboard from './pages/dentist/DentistDashboard'
import MyProfile from './pages/dentist/MyProfile'
import PatientDashboard from './pages/patient/PatientDashboard'
import BookAppointment from './pages/patient/BookAppointment'
import PatientProfile from './pages/patient/PatientProfile'
import Services from './pages/Services'
import Dentists from './pages/Dentists'
import DentistProfile from './pages/DentistProfile'
import AdminServices from './pages/admin/AdminServices'
import AdminDentists from './pages/admin/AdminDentists'
import AdminAppointments from './pages/admin/AdminAppointments'
import AdminPatients from './pages/admin/AdminPatients'
import PublicLayout from './components/PublicLayout'
import About from './pages/About'
import Contact from './pages/Contact'

function App() {
  const { loading, sessionError, retrySession, sessionExpired, dismissSessionExpired } = useAuth()

  if (loading) {
    return <p className="p-6 text-gray-600">Loading...</p>
  }

  if (sessionError) {
    return (
      <div className="flex min-h-screen items-center justify-center bg-teal-50 px-4">
        <div className="max-w-md rounded-lg bg-white p-6 text-center shadow">
          <h1 className="text-xl font-semibold text-teal-700">Something went wrong</h1>
          <p className="mt-2 text-sm text-gray-600">
            We could not check your session. You are still signed in, so you can try again.
          </p>
          <button
            onClick={retrySession}
            className="mt-4 rounded bg-teal-600 px-4 py-2 text-white hover:bg-teal-700"
          >
            Try again
          </button>
        </div>
      </div>
    )
  }

    return (
    <>
      {sessionExpired && (
        <div
          role="alert"
          className="flex items-center justify-center gap-3 bg-amber-100 px-4 py-2 text-sm text-amber-900"
        >
          <span>Your session has expired. Please log in again.</span>
          <button onClick={dismissSessionExpired} className="underline">
            Dismiss
          </button>
        </div>
      )}

      <Routes>
        <Route path="/login" element={<Login />} />
        <Route path="/register" element={<Register />} />

        <Route element={<PublicLayout />}>
          <Route path="/" element={<Home />} />
          <Route path="/services" element={<Services />} />
          <Route path="/dentists" element={<Dentists />} />
          <Route path="/dentists/:id" element={<DentistProfile />} />
          <Route path="/about" element={<About />} />
          <Route path="/contact" element={<Contact />} />
        </Route>

        <Route element={<ProtectedRoute allowedRoles={['patient']} />}>
          <Route path="/patient" element={<PatientDashboard />} />
          <Route path="/patient/book" element={<BookAppointment />} />
          <Route path="/patient/profile" element={<PatientProfile />} />
        </Route>

        <Route element={<ProtectedRoute allowedRoles={['dentist']} />}>
          <Route path="/dentist" element={<DentistDashboard />} />
          <Route path="/dentist/profile" element={<MyProfile />} />
        </Route>

        <Route element={<ProtectedRoute allowedRoles={['admin']} />}>
          <Route path="/admin" element={<AdminDashboard />} />
          <Route path="/admin/services" element={<AdminServices />} />
          <Route path="/admin/dentists" element={<AdminDentists />} />
          <Route path="/admin/appointments" element={<AdminAppointments />} />
          <Route path="/admin/patients" element={<AdminPatients />} />
        </Route>

        <Route path="*" element={<Navigate to="/" replace />} />

      </Routes>
    </>
  )
}

export default App