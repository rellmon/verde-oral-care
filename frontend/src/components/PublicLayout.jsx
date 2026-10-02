import { useState } from 'react'
import { Link, NavLink, Outlet } from 'react-router-dom'
import { useAuth } from '../context/AuthContext'
import { dashboardPathFor } from '../utils/roles'

const NAV_LINKS = [
  { to: '/', label: 'Home', end: true },
  { to: '/services', label: 'Services' },
  { to: '/dentists', label: 'Dentists' },
  { to: '/about', label: 'About' },
  { to: '/contact', label: 'Contact' },
]

export default function PublicLayout() {
  const { user, logout } = useAuth()
  const [menuOpen, setMenuOpen] = useState(false)

  function closeMenu() {
    setMenuOpen(false)
  }

  async function handleLogout() {
    closeMenu()
    await logout()
  }

  // NavLink tells us whether its page is the current one, so we can highlight it.
  const navLinkClasses = ({ isActive }) =>
    `rounded px-3 py-2 text-sm font-medium ${
      isActive ? 'bg-teal-100 text-teal-800' : 'text-gray-700 hover:bg-teal-50'
    }`

  return (
    <div className="flex min-h-screen flex-col bg-teal-50">
      <header className="border-b border-teal-100 bg-white">
        <div className="mx-auto flex max-w-5xl flex-wrap items-center justify-between gap-2 px-4 py-3">
          <Link to="/" onClick={closeMenu} className="text-xl font-bold text-teal-700">
            Verde Oral Care
          </Link>

          <button
            type="button"
            className="rounded border border-gray-300 px-3 py-1.5 text-sm text-gray-700 md:hidden"
            aria-expanded={menuOpen}
            aria-controls="site-menu"
            onClick={() => setMenuOpen((open) => !open)}
          >
            {menuOpen ? 'Close' : 'Menu'}
          </button>

          <nav
            id="site-menu"
            aria-label="Main"
            className={`${
              menuOpen ? 'flex' : 'hidden'
            } w-full flex-col gap-1 md:flex md:w-auto md:flex-row md:items-center`}
          >
            {NAV_LINKS.map((link) => (
              <NavLink
                key={link.to}
                to={link.to}
                end={link.end}
                onClick={closeMenu}
                className={navLinkClasses}
              >
                {link.label}
              </NavLink>
            ))}

            <span className="mx-2 hidden h-5 w-px bg-gray-200 md:block" aria-hidden="true" />

            {user ? (
              <>
                <Link
                  to={dashboardPathFor(user.role)}
                  onClick={closeMenu}
                  className="rounded px-3 py-2 text-sm font-medium text-teal-700 hover:bg-teal-50"
                >
                  My dashboard
                </Link>
                <button
                  type="button"
                  onClick={handleLogout}
                  className="rounded border border-teal-600 px-3 py-2 text-left text-sm font-medium text-teal-700 hover:bg-teal-50"
                >
                  Log out
                </button>
              </>
            ) : (
              <>
                <Link
                  to="/login"
                  onClick={closeMenu}
                  className="rounded px-3 py-2 text-sm font-medium text-teal-700 hover:bg-teal-50"
                >
                  Log in
                </Link>
                <Link
                  to="/register"
                  onClick={closeMenu}
                  className="rounded bg-teal-600 px-3 py-2 text-sm font-medium text-white hover:bg-teal-700"
                >
                  Register
                </Link>
              </>
            )}
          </nav>
        </div>
      </header>

      {/* The current page appears here. flex-1 pushes the footer to the bottom on short pages. */}
      <main className="flex-1">
        <Outlet />
      </main>

      <footer className="border-t border-teal-100 bg-white">
        <div className="mx-auto max-w-5xl px-4 py-6 text-sm text-gray-600">
          <p className="font-medium text-gray-900">Verde Oral Care</p>
          <p className="mt-1">
            Verde Oral Care is a fictional clinic created for a portfolio project.
          </p>
          <p className="mt-1 text-gray-500">&copy; {new Date().getFullYear()} Verde Oral Care</p>
        </div>
      </footer>
    </div>
  )
}