import { Navigate, Route, Routes } from 'react-router-dom'
import ProtectedRoute from './components/ProtectedRoute'
import { useAuth } from './context/AuthContext'
import Home from './pages/Home'
import Login from './pages/Login'
import Register from './pages/Register'
import AdminDashboard from './pages/admin/AdminDashboard'
import DentistDashboard from './pages/dentist/DentistDashboard'
import PatientDashboard from './pages/patient/PatientDashboard'
import BookAppointment from './pages/patient/BookAppointment'
import PatientProfile from './pages/patient/PatientProfile'
import Services from './pages/Services'
import Dentists from './pages/Dentists'
import DentistProfile from './pages/DentistProfile'
import AdminServices from './pages/admin/AdminServices'
import AdminDentists from './pages/admin/AdminDentists'

function App() {
  const { loading } = useAuth()

  if (loading) {
    return <p className="p-6 text-gray-600">Loading...</p>
  }

  return (
    <Routes>
      <Route path="/" element={<Home />} />
      <Route path="/login" element={<Login />} />
      <Route path="/register" element={<Register />} />
      <Route path="/dentists" element={<Dentists />} />
      <Route path="/dentists/:id" element={<DentistProfile />} />

      <Route element={<ProtectedRoute allowedRoles={['patient']} />}>
        <Route path="/patient" element={<PatientDashboard />} />
        <Route path="/patient/book" element={<BookAppointment />} />
        <Route path="/patient/profile" element={<PatientProfile />} />
      </Route>

      <Route element={<ProtectedRoute allowedRoles={['dentist']} />}>
        <Route path="/dentist" element={<DentistDashboard />} />
      </Route>

      <Route element={<ProtectedRoute allowedRoles={['admin']} />}>
        <Route path="/admin" element={<AdminDashboard />} />
        <Route path="/admin/services" element={<AdminServices />} />
        <Route path="/admin/dentists" element={<AdminDentists />} />
      </Route>

      <Route path="*" element={<Navigate to="/" replace />} />

      <Route path="/services" element={<Services />} />
    </Routes>
  )
}

export default App