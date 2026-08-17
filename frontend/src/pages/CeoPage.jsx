import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { apiGet } from '../api.js'
import StatCard from '../components/StatCard.jsx'
import StatusBadge from '../components/StatusBadge.jsx'
import EmptyState from '../components/EmptyState.jsx'

export default function CeoPage() {
  const [data, setData] = useState(null)
  const [error, setError] = useState('')

  useEffect(() => {
    apiGet('ceo')
      .then((b) => setData(b.data))
      .catch((e) => setError(e.message))
  }, [])

  if (error) return <div className="flash flash-error">{error}</div>
  if (!data) return <p className="muted">Loading…</p>

  const { stats, pipeline, recent } = data
  const maxCount = Math.max(1, ...pipeline.map((p) => Number(p.c)))

  return (
    <div className="page">
      <div className="hero">
        <h1>Platform analytics</h1>
        <p className="muted">High-level view of the KYC pipeline.</p>
      </div>

      <div className="stats">
        <StatCard value={stats.total} label="Total applications" />
        <StatCard value={stats.pending} label="Pending review" bad={stats.pending > 0} />
        <StatCard value={stats.approved} label="Approved" />
        <StatCard value={stats.rejected} label="Rejected" />
        <StatCard value={stats.resubmits} label="Change requests" />
        <StatCard value={`${stats.approval_rate}%`} label="Approval rate" />
        <StatCard value={stats.applicants} label="Applicants" />
        <StatCard value={stats.emails_sent} label="Emails sent" bad={stats.emails_failed > 0} />
      </div>

      <div className="card">
        <h2>Pipeline by status</h2>
        {pipeline.length ? (
          <div className="bars">
            {pipeline.map((p) => (
              <div className="bar" key={p.status}>
                <div className="bar-label">{p.status.replace(/_/g, ' ').toLowerCase()}</div>
                <div className="bar-track">
                  <div
                    className="bar-fill"
                    style={{ width: `${(Number(p.c) / maxCount) * 100}%` }}
                  />
                </div>
                <div className="bar-value">{p.c}</div>
              </div>
            ))}
          </div>
        ) : (
          <EmptyState>No applications yet.</EmptyState>
        )}
      </div>

      <h2>Recent submissions</h2>
      {recent.length ? (
        <div className="table-wrap">
          <table>
            <thead>
              <tr>
                <th>ID</th>
                <th>Applicant</th>
                <th>Status</th>
                <th>Submitted</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              {recent.map((a) => (
                <tr key={a.id}>
                  <td>#{a.id}</td>
                  <td>{a.applicant_name}</td>
                  <td><StatusBadge status={a.status} /></td>
                  <td className="muted">{a.created_at}</td>
                  <td><Link className="link" to={`/application/${a.id}`}>View</Link></td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      ) : (
        <EmptyState>Nothing submitted yet.</EmptyState>
      )}
    </div>
  )
}
