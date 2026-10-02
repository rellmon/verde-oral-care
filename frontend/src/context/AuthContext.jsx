import { createContext, useCallback, useContext, useEffect, useState } from 'react'
import api from '../api/axios'

const AuthContext = createContext(null)

export function AuthProvider({ children }) {
  const [user, setUser] = useState(null)
  const [loading, setLoading] = useState(true)
  // True when we could not find out who the saved token belongs to (server down, network error).
  const [sessionError, setSessionError] = useState(false)

  // If a token is saved, ask Laravel who it belongs to.
  const restoreSession = useCallback(async () => {
    if (!localStorage.getItem('token')) {
      setLoading(false)
      return
    }

    setLoading(true)
    setSessionError(false)

    try {
      const response = await api.get('/user')
      setUser(response.data)
    } catch (err) {
      if (err.response?.status === 401) {
        // Laravel says the token is no longer valid, so it is safe to forget it.
        localStorage.removeItem('token')
      } else {
        // The server could not be reached (or failed). Keep the token and let the person retry.
        setSessionError(true)
      }
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => {
    restoreSession()
  }, [restoreSession])

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
    <AuthContext.Provider
      value={{
        user,
        loading,
        sessionError,
        retrySession: restoreSession,
        login,
        register,
        logout,
        updateUser,
      }}
    >
      {children}
    </AuthContext.Provider>
  )
}

// eslint-disable-next-line react-refresh/only-export-components
export function useAuth() {
  return useContext(AuthContext)
}