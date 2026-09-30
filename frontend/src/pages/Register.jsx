import { useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { useAuth } from '../context/AuthContext'

const initialForm = {
  name: '',
  email: '',
  phone: '',
  password: '',
  password_confirmation: '',
}

export default function Register() {
  const { register } = useAuth()
  const navigate = useNavigate()

  const [form, setForm] = useState(initialForm)
  const [errors, setErrors] = useState({})
  const [generalError, setGeneralError] = useState('')
  const [submitting, setSubmitting] = useState(false)

  function handleChange(event) {
    setForm({ ...form, [event.target.name]: event.target.value })
  }

  async function handleSubmit(event) {
    event.preventDefault()
    setErrors({})
    setGeneralError('')
    setSubmitting(true)

    try {
      await register(form)
      navigate('/')
    } catch (err) {
      if (err.response?.status === 422) {
        setErrors(err.response.data.errors)
      } else {
        setGeneralError('Something went wrong. Please try again.')
      }
    } finally {
      setSubmitting(false)
    }
  }

  function renderField(name, label, type = 'text', required = true) {
    return (
      <div>
        <label htmlFor={name} className="block text-sm font-medium text-gray-700">
          {label}
        </label>
        <input
          id={name}
          name={name}
          type={type}
          value={form[name]}
          onChange={handleChange}
          required={required}
          className="mt-1 w-full rounded border border-gray-300 px-3 py-2"
        />
        {errors[name] && (
          <p className="mt-1 text-sm text-red-600" role="alert">
            {errors[name][0]}
          </p>
        )}
      </div>
    )
  }

  return (
    <div className="min-h-screen flex items-center justify-center bg-teal-50 px-4 py-8">
      <form
        onSubmit={handleSubmit}
        className="w-full max-w-sm bg-white rounded-lg shadow p-6 space-y-4"
      >
        <h1 className="text-2xl font-bold text-teal-700 text-center">Create account</h1>

        {generalError && (
          <p className="text-sm text-red-600 bg-red-50 rounded p-2" role="alert">
            {generalError}
          </p>
        )}

        {renderField('name', 'Full name')}
        {renderField('email', 'Email', 'email')}
        {renderField('phone', 'Phone (optional)', 'tel', false)}
        {renderField('password', 'Password (at least 8 characters)', 'password')}
        {renderField('password_confirmation', 'Confirm password', 'password')}

        <button
          type="submit"
          disabled={submitting}
          className="w-full rounded bg-teal-600 py-2 font-medium text-white hover:bg-teal-700 disabled:opacity-60"
        >
          {submitting ? 'Creating account...' : 'Register'}
        </button>

        <p className="text-sm text-center text-gray-600">
          Already have an account?{' '}
          <Link to="/login" className="text-teal-700 underline">
            Log in
          </Link>
        </p>
      </form>
    </div>
  )
}