import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import api from '../api/axios'
import ServiceCard from '../components/ServiceCard'
import { useAuth } from '../context/AuthContext'
import { dashboardPathFor } from '../utils/roles'

const STEPS = [
  { title: 'Create an account', text: 'Sign up for free in under a minute.' },
  { title: 'Choose your visit', text: 'Pick a service, a dentist, and a time that suits you.' },
  { title: 'We confirm it', text: 'The clinic confirms your appointment, and you can follow its status online.' },
]

export default function Home() {
  const { user } = useAuth()

  const [services, setServices] = useState([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(false)

  useEffect(() => {
    api
      .get('/services')
      .then((response) => setServices(response.data.data.slice(0, 3)))
      .catch(() => setError(true))
      .finally(() => setLoading(false))
  }, [])

  const primaryButton = 'rounded bg-teal-600 px-5 py-3 font-medium text-white hover:bg-teal-700'
  const secondaryButton =
    'rounded border border-teal-600 px-5 py-3 font-medium text-teal-700 hover:bg-teal-50'

  let buttons
  if (!user) {
    buttons = (
      <>
        <Link to="/register" className={primaryButton}>
          Create an account to book
        </Link>
        <Link to="/login" className={secondaryButton}>
          Log in
        </Link>
      </>
    )
  } else if (user.role === 'patient') {
    buttons = (
      <>
        <Link to="/patient/book" className={primaryButton}>
          Book an appointment
        </Link>
        <Link to="/patient" className={secondaryButton}>
          My appointments
        </Link>
      </>
    )
  } else {
    buttons = (
      <Link to={dashboardPathFor(user.role)} className={primaryButton}>
        Go to my dashboard
      </Link>
    )
  }

  return (
    <div>
      <section className="border-b border-teal-100 bg-white">
        <div className="mx-auto max-w-5xl px-4 py-16 text-center">
          <h1 className="text-4xl font-bold text-teal-700 sm:text-5xl">
            Gentle dental care for the whole family
          </h1>
          <p className="mx-auto mt-4 max-w-2xl text-lg text-gray-600">
            Book your appointment online, choose your dentist, and pick a time that suits you.
          </p>
          <div className="mt-8 flex flex-wrap items-center justify-center gap-3">{buttons}</div>
        </div>
      </section>

      <section className="mx-auto max-w-5xl px-4 py-12">
        <h2 className="text-2xl font-bold text-teal-700">Our services</h2>
        <p className="mt-1 text-gray-600">From check-ups to treatments, all in one place.</p>

        {loading && <p className="mt-6 text-gray-600">Loading services...</p>}

        {error && (
          <p className="mt-6 rounded bg-red-50 p-3 text-sm text-red-600" role="alert">
            We could not load our services right now.
          </p>
        )}

        {services.length > 0 && (
          <div className="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            {services.map((service) => (
              <ServiceCard key={service.id} service={service} />
            ))}
          </div>
        )}

        <div className="mt-6 flex flex-wrap gap-6 text-sm">
          <Link to="/services" className="text-teal-700 underline">
            See all services
          </Link>
          <Link to="/dentists" className="text-teal-700 underline">
            Meet our dentists
          </Link>
        </div>
      </section>

      <section className="border-t border-teal-100 bg-white">
        <div className="mx-auto max-w-5xl px-4 py-12">
          <h2 className="text-2xl font-bold text-teal-700">Booking in three steps</h2>
          <ol className="mt-6 grid gap-6 sm:grid-cols-3">
            {STEPS.map((step, index) => (
              <li key={step.title}>
                <span
                  className="flex h-9 w-9 items-center justify-center rounded-full bg-teal-100 font-semibold text-teal-800"
                  aria-hidden="true"
                >
                  {index + 1}
                </span>
                <h3 className="mt-3 font-semibold text-gray-900">{step.title}</h3>
                <p className="mt-1 text-sm text-gray-600">{step.text}</p>
              </li>
            ))}
          </ol>
        </div>
      </section>
    </div>
  )
}