import { useEffect, useState } from 'react'
import { apiGet, apiPost } from '../api.js'
import { useAuth } from '../hooks/useAuth.jsx'
import { useFlash } from '../components/Flash.jsx'
import StatCard from '../components/StatCard.jsx'

const ROLES = ['APPLICANT', 'ADMIN', 'SUPER_ADMIN', 'CEO']

function roleLabel(role) {
  return role.toLowerCase().split('_').map((w) => w.charAt(0).toUpperCase() + w.slice(1)).join(' ')
}

export default function UsersPage() {
  const { user: me } = useAuth()
  const flash = useFlash()
  const [data, setData] = useState(null)
  const [error, setError] = useState('')
  const [busy, setBusy] = useState('')
  const [createForm, setCreateForm] = useState({ username: '', email: '', password: '', role: 'APPLICANT' })
  const [pw, setPw] = useState({}) // userId -> new password

  const load = async () => {
    try {
      const body = await apiGet('users')
      setData(body.data)
    } catch (err) {
      setError(err.message)
    }
  }

  useEffect(() => {
    load()
  }, [])

  if (error) return <div className="flash flash-error">{error}</div>
  if (!data) return <p className="muted">Loading…</p>

  const counts = Object.fromEntries((data.counts || []).map((c) => [c.role, Number(c.c)]))
  const totalUsers = data.users.length

  const createUser = async (e) => {
    e.preventDefault()
    setBusy('create')
    try {
      const body = await apiPost('create_user', createForm)
      if (body.flash) flash.push('success', body.flash)
      setCreateForm({ username: '', email: '', password: '', role: 'APPLICANT' })
      await load()
    } catch (err) {
      flash.push('error', err.message)
    } finally {
      setBusy('')
    }
  }

  const changeRole = async (id, role) => {
    setBusy(`role:${id}`)
    try {
      const body = await apiPost('update_user', { id, role })
      if (body.flash) flash.push('success', body.flash)
      await load()
    } catch (err) {
      flash.push('error', err.message)
    } finally {
      setBusy('')
    }
  }

  const resetPassword = async (id) => {
    const password = pw[id] || ''
    if (password.length < 8) {
      flash.push('error', 'The new password must be at least 8 characters.')
      return
    }
    setBusy(`pw:${id}`)
    try {
      const body = await apiPost('reset_password', { id, password })
      if (body.flash) flash.push('success', body.flash)
      setPw((p) => ({ ...p, [id]: '' }))
    } catch (err) {
      flash.push('error', err.message)
    } finally {
      setBusy('')
    }
  }

  return (
    <div className="page">
      <div className="hero">
        <h1>Users</h1>
        <p className="muted">Manage accounts and roles.</p>
      </div>

      <div className="stats stats-inline">
        <StatCard value={totalUsers} label="Total users" />
        <StatCard value={counts.APPLICANT || 0} label="Applicants" />
        <StatCard value={counts.ADMIN || 0} label="Admins" />
        <StatCard value={counts.SUPER_ADMIN || 0} label="Super admins" />
        <StatCard value={counts.CEO || 0} label="CEOs" />
      </div>

      <form className="card" onSubmit={createUser}>
        <h2>Create user</h2>
        <div className="form-grid">
          <div>
            <label>Username</label>
            <input value={createForm.username} onChange={(e) => setCreateForm({ ...createForm, username: e.target.value })} required />
          </div>
          <div>
            <label>Email</label>
            <input type="email" value={createForm.email} onChange={(e) => setCreateForm({ ...createForm, email: e.target.value })} required />
          </div>
          <div>
            <label>Password</label>
            <input type="password" minLength={8} value={createForm.password} onChange={(e) => setCreateForm({ ...createForm, password: e.target.value })} required />
            <p className="hint">At least 8 characters.</p>
          </div>
          <div>
            <label>Role</label>
            <select value={createForm.role} onChange={(e) => setCreateForm({ ...createForm, role: e.target.value })}>
              {ROLES.map((r) => (
                <option key={r} value={r}>{roleLabel(r)}</option>
              ))}
            </select>
          </div>
        </div>
        <div className="form-actions">
          <button className="button button-primary" disabled={busy === 'create'}>
            {busy === 'create' ? 'Creating…' : 'Create user'}
          </button>
        </div>
      </form>

      <div className="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Username</th>
              <th>Email</th>
              <th>Role</th>
              <th>Created</th>
              <th>Reset password</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            {data.users.map((u) => (
              <tr key={u.id}>
                <td>
                  {u.username}
                  {u.id === me.id && <span className="muted"> (you)</span>}
                </td>
                <td className="muted">{u.email}</td>
                <td>
                  <select
                    className="inline-edit"
                    value={u.role}
                    disabled={u.id === me.id || busy === `role:${u.id}`}
                    onChange={(e) => changeRole(u.id, e.target.value)}
                  >
                    {ROLES.map((r) => (
                      <option key={r} value={r}>{roleLabel(r)}</option>
                    ))}
                  </select>
                </td>
                <td className="muted">{u.created_at}</td>
                <td>
                  <input
                    className="inline-edit"
                    type="password"
                    placeholder="New password"
                    value={pw[u.id] || ''}
                    onChange={(e) => setPw({ ...pw, [u.id]: e.target.value })}
                  />
                </td>
                <td>
                  <button
                    className="button button-small"
                    onClick={() => resetPassword(u.id)}
                    disabled={busy === `pw:${u.id}`}
                  >
                    Reset
                  </button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  )
}
