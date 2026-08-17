import { useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { apiPost, resetCsrf } from '../api.js'
import { useFlash } from '../components/Flash.jsx'

export default function RegisterPage() {
  const [form, setForm] = useState({ username: '', email: '', password: '' })
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState('')
  const navigate = useNavigate()
  const flash = useFlash()

  const handleSubmit = async (e) => {
    e.preventDefault()
    setBusy(true)
    setError('')
    try {
      const body = await apiPost('register', form)
      resetCsrf()
      if (body.flash) flash.push('success', body.flash)
      navigate('/login', { replace: true })
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
        <h1>Join KYC Verify</h1>
        <p>Create your account to get started with identity verification.</p>
      </div>
      <div className="auth-form">
        <div className="card">
          <h2>Create account</h2>
          {error && <div className="flash flash-error">{error}</div>}
          <form onSubmit={handleSubmit}>
            <div className="stack">
              <div>
                <label htmlFor="username">Username</label>
                <input
                  id="username"
                  value={form.username}
                  onChange={(e) => setForm({ ...form, username: e.target.value })}
                  required
                  autoFocus
                />
              </div>
              <div>
                <label htmlFor="email">Email</label>
                <input
                  id="email"
                  type="email"
                  autoComplete="email"
                  value={form.email}
                  onChange={(e) => setForm({ ...form, email: e.target.value })}
                  required
                />
              </div>
              <div>
                <label htmlFor="password">Password</label>
                <input
                  id="password"
                  type="password"
                  autoComplete="new-password"
                  minLength={8}
                  value={form.password}
                  onChange={(e) => setForm({ ...form, password: e.target.value })}
                  required
                />
                <p className="hint">At least 8 characters.</p>
              </div>
              <button className="button button-primary button-block" disabled={busy}>
                {busy ? 'Creating account…' : 'Create account'}
              </button>
            </div>
          </form>
          <p className="muted center">
            Already registered? <Link to="/login">Sign in</Link>
          </p>
        </div>
      </div>
    </div>
  )
}
