import { Navigate, Route, Routes } from 'react-router-dom'
import ProtectedRoute from './components/ProtectedRoute'
import { useAuth } from './context/AuthContext'
import Home from './pages/Home'
import Login from './pages/Login'
import Register from './pages/Register'
import AdminDashboard from './pages/admin/AdminDashboard'
import DentistDashboard from './pages/dentist/DentistDashboard'
import PatientDashboard from './pages/patient/PatientDashboard'
import Services from './pages/Services'

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

      <Route element={<ProtectedRoute allowedRoles={['patient']} />}>
        <Route path="/patient" element={<PatientDashboard />} />
      </Route>

      <Route element={<ProtectedRoute allowedRoles={['dentist']} />}>
        <Route path="/dentist" element={<DentistDashboard />} />
      </Route>

      <Route element={<ProtectedRoute allowedRoles={['admin']} />}>
        <Route path="/admin" element={<AdminDashboard />} />
      </Route>

      <Route path="*" element={<Navigate to="/" replace />} />

      <Route path="/services" element={<Services />} />
    </Routes>
  )
}

export default App