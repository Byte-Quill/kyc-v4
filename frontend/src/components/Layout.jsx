import { NavLink, Outlet } from 'react-router-dom'
import { useAuth } from '../hooks/useAuth.jsx'
import Flash from './Flash.jsx'

const STAFF_ROLES = ['ADMIN', 'SUPER_ADMIN', 'CEO']

function roleLabel(role) {
  if (!role) return ''
  return role
    .toLowerCase()
    .split('_')
    .map((w) => w.charAt(0).toUpperCase() + w.slice(1))
    .join(' ')
}

export default function Layout() {
  const { user, signOut } = useAuth()
  if (!user) return null

  const isStaff = STAFF_ROLES.includes(user.role)
  const nav = [
    { to: '/dashboard', label: 'Dashboard' },
    { to: '/applications', label: 'Applications' },
  ]
  if (isStaff) nav.push({ to: '/review', label: 'Review queue' })
  if (user.role === 'SUPER_ADMIN') nav.push({ to: '/users', label: 'Users' })
  if (user.role === 'CEO') nav.push({ to: '/ceo', label: 'Analytics' })

  return (
    <>
      <header>
        <NavLink className="brand" to="/dashboard">
          KYC<span>Verify</span>
        </NavLink>
        <nav>
          {nav.map((item) => (
            <NavLink key={item.to} to={item.to} end={item.to === '/dashboard'}>
              {item.label}
            </NavLink>
          ))}
          <span className="user">
            {user.username}
            <small>{roleLabel(user.role)}</small>
          </span>
          <form
            className="inline"
            onSubmit={(e) => {
              e.preventDefault()
              signOut()
            }}
          >
            <button className="link" type="submit">
              Sign out
            </button>
          </form>
        </nav>
      </header>
      <main>
        <Flash />
        <Outlet />
      </main>
      <footer>KYC Verify · Secure identity verification</footer>
    </>
  )
}
