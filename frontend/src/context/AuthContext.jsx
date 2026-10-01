import { createContext, useContext, useEffect, useState } from 'react'
import api from '../api/axios'

const AuthContext = createContext(null)

export function AuthProvider({ children }) {
  const [user, setUser] = useState(null)
  const [loading, setLoading] = useState(true)

  // On first load, if a token is saved, ask Laravel who it belongs to.
  useEffect(() => {
    if (!localStorage.getItem('token')) {
      setLoading(false)
      return
    }

    api
      .get('/user')
      .then((response) => setUser(response.data))
      .catch(() => localStorage.removeItem('token'))
      .finally(() => setLoading(false))
  }, [])

  async function login(email, password) {
    const response = await api.post('/login', { email, password })
    localStorage.setItem('token', response.data.token)
    setUser(response.data.user)
    return response.data.user
  }

  async function register(formData) {
    const response = await api.post('/register', formData)
    localStorage.setItem('token', response.data.token)
    setUser(response.data.user)
    return response.data.user
  }

  async function logout() {
    try {
      await api.post('/logout')
    } finally {
      localStorage.removeItem('token')
      setUser(null)
    }
  }

    // Lets other pages update the logged-in user (for example after editing the profile).
  function updateUser(changes) {
    setUser((current) => ({ ...current, ...changes }))
  }

  return (
    <AuthContext.Provider value={{ user, loading, login, register, logout, updateUser }}>
      {children}
    </AuthContext.Provider>
  )
}

// eslint-disable-next-line react-refresh/only-export-components
export function useAuth() {
  return useContext(AuthContext)
}