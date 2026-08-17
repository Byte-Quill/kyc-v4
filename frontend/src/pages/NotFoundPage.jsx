import { Link } from 'react-router-dom'

export default function NotFoundPage() {
  return (
    <div className="page">
      <div className="hero">
        <h1>Page not found</h1>
        <p className="muted">That page does not exist or has moved.</p>
        <p>
          <Link className="button button-primary" to="/dashboard">Go to dashboard</Link>
        </p>
      </div>
    </div>
  )
}
