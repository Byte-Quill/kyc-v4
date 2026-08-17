import { useState } from 'react'
import { Link, useNavigate, useLocation } from 'react-router-dom'
import { apiPost, resetCsrf } from '../api.js'
import { useAuth } from '../hooks/useAuth.jsx'
import { useFlash } from '../components/Flash.jsx'

export default function LoginPage() {
  const [form, setForm] = useState({ email: '', password: '' })
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState('')
  const navigate = useNavigate()
  const location = useLocation()
  const { refresh } = useAuth()
  const flash = useFlash()

  const handleSubmit = async (e) => {
    e.preventDefault()
    setBusy(true)
    setError('')
    try {
      const body = await apiPost('login', form)
      resetCsrf()
      await refresh()
      const from = location.state?.from?.pathname || '/dashboard'
      if (body.flash) flash.push('success', body.flash)
      navigate(from, { replace: true })
    } catch (err) {
      setError(err.message)
    } finally {
      setBusy(false)
    }
  }

  return (
    <div className="auth">
      <div className="auth-side">
        <div className="brand-inline">KYC<span>Verify</span></div>
        <h1>Secure identity verification</h1>
        <p>
          Submit your KYC application, upload your documents, and track your
          review — all in one place.
        </p>
        <ul>
          <li>Fast, guided application form</li>
          <li>Document uploads with instant validation</li>
          <li>Transparent review status at every step</li>
        </ul>
      </div>
      <div className="auth-form">
        <div className="card">
          <h2>Sign in</h2>
          {error && <div className="flash flash-error">{error}</div>}
          <form onSubmit={handleSubmit}>
            <div className="stack">
              <div>
                <label htmlFor="email">Email</label>
                <input
                  id="email"
                  type="email"
                  autoComplete="email"
                  value={form.email}
                  onChange={(e) => setForm({ ...form, email: e.target.value })}
                  required
                  autoFocus
                />
              </div>
              <div>
                <label htmlFor="password">Password</label>
                <input
                  id="password"
                  type="password"
                  autoComplete="current-password"
                  value={form.password}
                  onChange={(e) => setForm({ ...form, password: e.target.value })}
                  required
                />
              </div>
              <button className="button button-primary button-block" disabled={busy}>
                {busy ? 'Signing in…' : 'Sign in'}
              </button>
            </div>
          </form>
          <p className="muted center">
            No account yet? <Link to="/register">Create one</Link>
          </p>
        </div>
      </div>
    </div>
  )
}
