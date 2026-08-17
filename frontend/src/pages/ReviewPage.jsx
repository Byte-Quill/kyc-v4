import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { apiGet } from '../api.js'
import StatusBadge from '../components/StatusBadge.jsx'
import EmptyState from '../components/EmptyState.jsx'

export default function ReviewPage() {
  const [apps, setApps] = useState([])
  const [error, setError] = useState('')

  useEffect(() => {
    apiGet('review_queue')
      .then((b) => setApps(b.data))
      .catch((e) => setError(e.message))
  }, [])

  if (error) return <div className="flash flash-error">{error}</div>

  return (
    <div className="page">
      <div className="hero">
        <h1>Review queue</h1>
        <p className="muted">Applications waiting for a decision.</p>
      </div>

      {apps.length ? (
        <div className="table-wrap">
          <table>
            <thead>
              <tr>
                <th>ID</th>
                <th>Applicant</th>
                <th>Full name</th>
                <th>Status</th>
                <th>Submitted</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              {apps.map((a) => (
                <tr key={a.id}>
                  <td>#{a.id}</td>
                  <td>{a.applicant_name}</td>
                  <td>{a.full_name || <span className="muted">—</span>}</td>
                  <td><StatusBadge status={a.status} /></td>
                  <td className="muted">{a.created_at}</td>
                  <td><Link className="link" to={`/application/${a.id}`}>Review</Link></td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      ) : (
        <EmptyState>The review queue is empty — nothing is waiting right now.</EmptyState>
      )}
    </div>
  )
}
