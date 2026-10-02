import { Link } from 'react-router-dom'
import { CLINIC } from '../config/clinic'

export default function Contact() {
  return (
    <div className="px-4 py-8">
      <div className="mx-auto max-w-3xl">
        <h1 className="text-3xl font-bold text-teal-700">Contact us</h1>
        <p className="mt-2 text-gray-600">Questions before you book? We are happy to help.</p>

        <dl className="mt-6 divide-y divide-gray-100 rounded-lg bg-white p-6 shadow">
          <div className="py-3">
            <dt className="text-sm font-medium text-gray-700">Address</dt>
            <dd className="mt-1 text-gray-900">
              <address className="not-italic">
                {CLINIC.address.map((line) => (
                  <span key={line} className="block">
                    {line}
                  </span>
                ))}
              </address>
            </dd>
          </div>
          <div className="py-3">
            <dt className="text-sm font-medium text-gray-700">Phone</dt>
            <dd className="mt-1">
              <a
                href={`tel:${CLINIC.phone.replace(/[^\d+]/g, '')}`}
                className="text-teal-700 underline"
              >
                {CLINIC.phone}
              </a>
            </dd>
          </div>
          <div className="py-3">
            <dt className="text-sm font-medium text-gray-700">Email</dt>
            <dd className="mt-1">
              <a href={`mailto:${CLINIC.email}`} className="text-teal-700 underline">
                {CLINIC.email}
              </a>
            </dd>
          </div>
        </dl>

        <p className="mt-6 text-gray-700">
          Opening hours differ by dentist. You can see each dentist's weekly hours on their
          profile on the{' '}
          <Link to="/dentists" className="text-teal-700 underline">
            dentists page
          </Link>
          , and you can book any open time online.
        </p>

        <p className="mt-6 text-sm text-gray-500">
          These contact details are placeholders for a fictional clinic.
        </p>
      </div>
    </div>
  )
}