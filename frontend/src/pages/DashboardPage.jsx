import { useEffect, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { apiGet, apiPost } from '../api.js'
import { useAuth } from '../hooks/useAuth.jsx'
import { useFlash } from '../components/Flash.jsx'
import StatCard from '../components/StatCard.jsx'
import StatusBadge from '../components/StatusBadge.jsx'
import EmptyState from '../components/EmptyState.jsx'

export default function DashboardPage() {
  const { user } = useAuth()
  const navigate = useNavigate()
  const flash = useFlash()
  const [data, setData] = useState(null)
  const [error, setError] = useState('')
  const [creating, setCreating] = useState(false)

  const load = async () => {
    try {
      const body = await apiGet('dashboard')
      setData(body.data)
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
  if (!data) return <p className="muted">Loading…</p>

  const isStaff = ['ADMIN', 'SUPER_ADMIN', 'CEO'].includes(user.role)

  return (
    <div className="page">
      <div className="hero">
        <h1>Welcome back, {user.username}</h1>
        <p className="muted">
          {isStaff ? 'Here is an overview of applications across the platform.' : 'Manage your KYC application and documents below.'}
        </p>
      </div>

      {!isStaff && (
        <div className="hero compact">
          <p className="eyebrow">My application</p>
          {data.resubmissions > 0 ? (
            <div className="callout callout-warn">
              <strong>Action needed.</strong> Your application needs changes before
              it can be approved. Open it to review the requested corrections.
            </div>
          ) : (
            <p className="muted">You do not have an active application yet.</p>
          )}
          <div className="form-actions">
            <button className="button button-primary" onClick={handleNew} disabled={creating}>
              {creating ? 'Creating…' : '+ New application'}
            </button>
            <Link className="link" to="/applications">
              View all my applications
            </Link>
          </div>
        </div>
      )}

      {isStaff && (
        <>
          <div className="stats">
            <StatCard value={data.stats.total} label="Total applications" />
            <StatCard value={data.stats.pending} label="Pending review" bad={data.stats.pending > 0} />
            <StatCard value={data.stats.approved} label="Approved" />
            <StatCard value={data.stats.rejected} label="Rejected" />
            <StatCard value={data.stats.resubmits} label="Change requests" />
            <StatCard value={`${data.stats.approval_rate}%`} label="Approval rate" />
          </div>

          <h2>Recent activity</h2>
          {data.recent.length ? (
            <div className="table-wrap">
              <table>
                <thead>
                  <tr>
                    <th>ID</th>
                    <th>Applicant</th>
                    <th>Status</th>
                    <th>Updated</th>
                    <th></th>
                  </tr>
                </thead>
                <tbody>
                  {data.recent.map((a) => (
                    <tr key={a.id}>
                      <td>#{a.id}</td>
                      <td>{a.applicant_name}</td>
                      <td><StatusBadge status={a.status} /></td>
                      <td className="muted">{a.updated_at}</td>
                      <td><Link className="link" to={`/application/${a.id}`}>View</Link></td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          ) : (
            <EmptyState>No applications yet.</EmptyState>
          )}
        </>
      )}
    </div>
  )
}
