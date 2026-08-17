/** Status badge mirroring the PHP `badge()` helper. */
export default function StatusBadge({ status }) {
  const cls = String(status || '')
    .toLowerCase()
    .replace(/_/g, '-')
  const label = (status || '')
    .toLowerCase()
    .split('_')
    .map((w) => w.charAt(0).toUpperCase() + w.slice(1))
    .join(' ')
  return <span className={`badge badge-${cls}`}>{label}</span>
}
