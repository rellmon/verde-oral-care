import { Link } from 'react-router-dom'
import { CLINIC } from '../config/clinic'

const VALUES = [
  {
    title: 'Gentle care',
    text: 'We take our time, explain each step, and make sure you are comfortable.',
  },
  {
    title: 'Clear communication',
    text: 'You always know what a visit involves, how long it takes, and what it costs.',
  },
  {
    title: 'Easy scheduling',
    text: 'Book, check, and cancel appointments online whenever it suits you.',
  },
]

export default function About() {
  return (
    <div className="px-4 py-8">
      <div className="mx-auto max-w-3xl">
        <h1 className="text-3xl font-bold text-teal-700">About {CLINIC.name}</h1>

        <p className="mt-4 text-gray-700">
          {CLINIC.name} is a modern dental clinic built around a simple idea: looking after your
          teeth should be straightforward. Our dentists cover general, orthodontic, endodontic,
          and pediatric care, so families can find the right person in one place.
        </p>
        <p className="mt-4 text-gray-700">
          Everything from choosing a service to following your appointment status happens online,
          so you spend less time on the phone and more time on your smile.
        </p>

        <h2 className="mt-10 text-2xl font-bold text-teal-700">What we care about</h2>
        <ul className="mt-4 grid gap-4 sm:grid-cols-3">
          {VALUES.map((value) => (
            <li key={value.title} className="rounded-lg bg-white p-5 shadow">
              <h3 className="font-semibold text-teal-700">{value.title}</h3>
              <p className="mt-2 text-sm text-gray-600">{value.text}</p>
            </li>
          ))}
        </ul>

        <p className="mt-10 text-gray-700">
          Want to know who will look after you?{' '}
          <Link to="/dentists" className="text-teal-700 underline">
            Meet our dentists
          </Link>
          .
        </p>

        <p className="mt-6 text-sm text-gray-500">
          {CLINIC.name} is a fictional clinic created for a portfolio project.
        </p>
      </div>
    </div>
  )
}