export default function StatCard({ value, label, bad = false }) {
  return (
    <div className={`stat${bad ? ' stat-bad' : ''}`}>
      <strong>{value}</strong>
      <span>{label}</span>
    </div>
  )
}
