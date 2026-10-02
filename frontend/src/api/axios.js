import axios from 'axios'

const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL,
  headers: {
    Accept: 'application/json',
  },
})

// AuthContext registers a function here. It is called when Laravel rejects our token.
let unauthorizedHandler = null

export function setUnauthorizedHandler(handler) {
  unauthorizedHandler = handler
}

api.interceptors.request.use((config) => {
  const token = localStorage.getItem('token')
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }
  return config
})

api.interceptors.response.use(
  (response) => response,
  (error) => {
    const status = error.response?.status
    const url = error.config?.url

    // A 401 while we hold a token means the token is no longer valid (revoked or expired).
    // /logout is skipped: someone logging out on purpose should not see an "expired" message.
    if (status === 401 && localStorage.getItem('token') && url !== '/logout') {
      unauthorizedHandler?.()
    }

    // Still reject, so the code that made the request can run its own error handling.
    return Promise.reject(error)
  }
)

export default api