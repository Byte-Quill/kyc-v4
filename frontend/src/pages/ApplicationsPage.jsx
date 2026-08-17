import { useEffect, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { apiGet, apiPost } from '../api.js'
import { useAuth } from '../hooks/useAuth.jsx'
import { useFlash } from '../components/Flash.jsx'
import StatusBadge from '../components/StatusBadge.jsx'
import EmptyState from '../components/EmptyState.jsx'

export default function ApplicationsPage() {
  const { user } = useAuth()
  const navigate = useNavigate()
  const flash = useFlash()
  const [apps, setApps] = useState([])
  const [error, setError] = useState('')
  const [creating, setCreating] = useState(false)

  const isStaff = ['ADMIN', 'SUPER_ADMIN', 'CEO'].includes(user.role)

  const load = async () => {
    try {
      const body = await apiGet('applications')
      setApps(body.data)
    } catch (err) {
      setError(err.message)
    }
  }

  useEffect(() => {
    load()
  }, [])

  const handleNew = async () => {
    setCreating(true)
    try {
      const body = await apiPost('create_application')
      if (body.flash) flash.push('success', body.flash)
      navigate(`/application/${body.data.id}`, { replace: true })
    } catch (err) {
      setError(err.message)
      setCreating(false)
    }
  }

  if (error) return <div className="flash flash-error">{error}</div>

  return (
    <div className="page">
      <div className="hero">
        <h1>Applications</h1>
        {!isStaff && (
          <button className="button button-primary" onClick={handleNew} disabled={creating}>
            {creating ? 'Creating…' : '+ New application'}
          </button>
        )}
      </div>

      {apps.length ? (
        <div className="table-wrap">
          <table>
            <thead>
              <tr>
                <th>ID</th>
                {isStaff && <th>Applicant</th>}
                <th>Full name</th>
                <th>Status</th>
                <th>Updated</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              {apps.map((a) => (
                <tr key={a.id}>
                  <td>#{a.id}</td>
                  {isStaff && <td>{a.applicant_name}</td>}
                  <td>{a.full_name || <span className="muted">—</span>}</td>
                  <td><StatusBadge status={a.status} /></td>
                  <td className="muted">{a.updated_at}</td>
                  <td><Link className="link" to={`/application/${a.id}`}>View</Link></td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      ) : (
        <EmptyState>
          No applications yet. {!isStaff && 'Click "+ New application" to create your first draft.'}
        </EmptyState>
      )}
    </div>
  )
}
