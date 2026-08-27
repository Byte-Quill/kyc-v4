import { useEffect, useState, useRef } from 'react'
import { Link, useParams } from 'react-router-dom'
import { apiGet, apiPost, apiUpload, resolveUrl } from '../api.js'
import { useFlash } from '../components/Flash.jsx'
import StatusBadge from '../components/StatusBadge.jsx'
import ConfirmButton from '../components/ConfirmButton.jsx'

const ID_TYPES = ['Citizenship', 'Passport', 'Driver License', 'National ID']

const DOC_GROUPS = [
  {
    key: 'education',
    label: 'Education documents',
    fields: [
      ['see_document', 'SEE certificate'],
      ['slc_document', 'SLC certificate'],
      ['graduate_document', 'Graduate degree'],
    ],
  },
  {
    key: 'additional_documents',
    label: 'Government identity documents',
    fields: [
      ['citizenship_document', 'Citizenship'],
      ['passport_document', 'Passport'],
      ['license_document', 'License'],
    ],
  },
]

export default function ApplicationPage() {
  const { id } = useParams()
  const flash = useFlash()

  const [data, setData] = useState(null)
  const [error, setError] = useState('')
  const [busy, setBusy] = useState('')
  const [form, setForm] = useState({})
  const [reviewNotes, setReviewNotes] = useState('')
  const [uploading, setUploading] = useState(null)
  const fileRefs = useRef({})
  const formReady = useRef(false)

  // Populate the form from the server response, but only the first time for a
  // given application. Reloads (e.g. after a document upload) must not wipe
  // unsaved edits the applicant has typed.
  const load = async () => {
    try {
      const body = await apiGet('application', { id })
      setData(body.data)
      const app = body.data.application
      if (!formReady.current) {
        setForm({
          full_name: app.full_name || '',
          date_of_birth: app.date_of_birth || '',
          nationality: app.nationality || '',
          id_type: app.id_type || '',
          id_number: app.id_number || '',
          id_issue_date: app.id_issue_date || '',
          issuing_district: app.issuing_district || '',
          permanent_address: app.permanent_address || '',
          temporary_address: app.temporary_address || '',
        })
        formReady.current = true
      }
    } catch (err) {
      setError(err.message)
    }
  }

  useEffect(() => {
    formReady.current = false
    load()
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [id])

  if (error) return <div className="flash flash-error">{error}</div>
  if (!data) return <p className="muted">Loading…</p>

  const { application: app, permissions: perm, documents, audit } = data

  const set = (k) => (e) => setForm({ ...form, [k]: e.target.value })

  const save = async (submit = false) => {
    setBusy(submit ? 'submitting' : 'saving')
    try {
      const body = await apiPost(submit ? 'submit' : 'save_application', { id, ...form })
      if (body.flash) flash.push('success', body.flash)
      await load()
    } catch (err) {
      flash.push('error', err.message)
    } finally {
      setBusy('')
    }
  }

  const upload = async (table, column) => {
    const input = fileRefs.current[`${table}:${column}`]
    const file = input?.files?.[0]
    if (!file) return
    setUploading(`${table}:${column}`)
    try {
      const body = await apiUpload('upload_profile_document', { id, table, column }, file)
      if (body.flash) flash.push('success', body.flash)
      await load()
      input.value = ''
    } catch (err) {
      flash.push('error', err.message)
    } finally {
      setUploading(null)
    }
  }

  const review = async (decision) => {
    setBusy(`review:${decision}`)
    try {
      const body = await apiPost('review', { id, decision, review_notes: reviewNotes })
      if (body.flash) flash.push('success', body.flash)
      setReviewNotes('')
      await load()
    } catch (err) {
      flash.push('error', err.message)
    } finally {
      setBusy('')
    }
  }

  return (
    <div className="page">
      <div className="hero">
        <h1>
          Application #{app.id}
          <StatusBadge status={app.status} />
        </h1>
        <p className="muted">
          Submitted by {app.applicant_name} · {app.created_at}
        </p>
      </div>

      {perm.editable && (
        <form
          onSubmit={(e) => {
            e.preventDefault()
            save(false)
          }}
        >
          <div className="card">
            <h2>Personal details</h2>
            <div className="form-grid">
              <div>
                <label>Full name *</label>
                <input value={form.full_name} onChange={set('full_name')} required />
              </div>
              <div>
                <label>Date of birth *</label>
                <input type="date" value={form.date_of_birth} onChange={set('date_of_birth')} required />
              </div>
              <div>
                <label>Nationality *</label>
                <input value={form.nationality} onChange={set('nationality')} required />
              </div>
              <div>
                <label>ID type *</label>
                <select value={form.id_type} onChange={set('id_type')} required>
                  <option value="">Select type</option>
                  {ID_TYPES.map((t) => (
                    <option key={t} value={t}>{t}</option>
                  ))}
                </select>
              </div>
              <div>
                <label>ID number *</label>
                <input value={form.id_number} onChange={set('id_number')} required />
              </div>
              <div>
                <label>ID issue date</label>
                <input type="date" value={form.id_issue_date} onChange={set('id_issue_date')} />
              </div>
              <div>
                <label>Issuing district</label>
                <input value={form.issuing_district} onChange={set('issuing_district')} />
              </div>
              <div className="grid-span">
                <label>Permanent address *</label>
                <textarea rows={2} value={form.permanent_address} onChange={set('permanent_address')} required />
              </div>
              <div className="grid-span">
                <label>Temporary address</label>
                <textarea rows={2} value={form.temporary_address} onChange={set('temporary_address')} />
              </div>
            </div>
            <div className="form-actions">
              <button className="button button-primary" disabled={busy === 'saving' || busy === 'submitting'}>
                {busy === 'saving' ? 'Saving…' : 'Save draft'}
              </button>
              <ConfirmButton
                className="button button-success"
                onClick={() => save(true)}
                disabled={busy === 'saving' || busy === 'submitting'}
              >
                {busy === 'submitting' ? 'Submitting…' : 'Submit for review'}
              </ConfirmButton>
              <Link className="link" to="/applications">Back to applications</Link>
            </div>
          </div>
        </form>
      )}

      {!perm.editable && (
        <div className="card">
          <h2>Personal details</h2>
          <div className="details">
            <dl>
              <div><dt>Full name</dt><dd>{app.full_name || '—'}</dd></div>
              <div><dt>Date of birth</dt><dd>{app.date_of_birth || '—'}</dd></div>
              <div><dt>Nationality</dt><dd>{app.nationality || '—'}</dd></div>
              <div><dt>ID type</dt><dd>{app.id_type || '—'}</dd></div>
              <div><dt>ID number</dt><dd>{app.id_number || '—'}</dd></div>
              <div><dt>ID issue date</dt><dd>{app.id_issue_date || '—'}</dd></div>
              <div><dt>Issuing district</dt><dd>{app.issuing_district || '—'}</dd></div>
              <div><dt>Permanent address</dt><dd className="wide">{app.permanent_address || '—'}</dd></div>
              <div><dt>Temporary address</dt><dd className="wide">{app.temporary_address || '—'}</dd></div>
            </dl>
          </div>
        </div>
      )}

      <div className="card">
        <h2>Documents</h2>
        <div className="docs-grid">
          {DOC_GROUPS.map((group) => (
            <div className="doc-group" key={group.key}>
              <h3>{group.label}</h3>
              {group.fields.map(([column, label]) => {
                const url = documents?.[group.key]?.[column]
                const key = `${group.key}:${column}`
                return (
                  <div className="doc-item" key={column}>
                    <strong>{label}</strong>
                    {url ? (
                      <a className="link" href={resolveUrl(url)} target="_blank" rel="noopener noreferrer">View uploaded document</a>
                    ) : (
                      <span className="muted">Not uploaded</span>
                    )}
                    {perm.editable && (
                      <div className="doc-upload">
                        <input
                          ref={(el) => (fileRefs.current[key] = el)}
                          type="file"
                          accept=".jpg,.jpeg,.png,.pdf"
                          onChange={() => upload(group.key, column)}
                        />
                        {uploading === key && <span className="muted">Uploading…</span>}
                      </div>
                    )}
                  </div>
                )
              })}
            </div>
          ))}
        </div>
      </div>

      {perm.reviewable && (
        <div className="card review-form">
          <h2>Review decision</h2>
          <label>
            Notes (required for rejection / change request)
          </label>
          <textarea
            rows={3}
            placeholder="Explain what needs to change, or why this is being rejected..."
            value={reviewNotes}
            onChange={(e) => setReviewNotes(e.target.value)}
          />
          <div className="review-actions">
            <button
              className="button button-success"
              onClick={() => review('approve')}
              disabled={busy.startsWith('review:')}
            >
              Approve
            </button>
            <button
              className="button button-danger"
              onClick={() => review('reject')}
              disabled={busy.startsWith('review:')}
            >
              Reject
            </button>
            <button
              className="button button-warn"
              onClick={() => review('resubmit')}
              disabled={busy.startsWith('review:')}
            >
              Request changes
            </button>
          </div>
        </div>
      )}

      {app.review_notes && (
        <div className="card">
          <h2>Review outcome</h2>
          <p className="muted">
            {app.reviewer_name ? `Reviewed by ${app.reviewer_name} · ${app.reviewed_at}` : 'Reviewed'}
          </p>
          <div className="callout callout-info">{app.review_notes}</div>
        </div>
      )}

      <div className="card">
        <h2>Activity</h2>
        {audit.length ? (
          <div className="timeline">
            {audit.map((entry) => (
              <div className="tl-item" key={entry.id}>
                <span className="tl-dot" />
                <div>
                  <strong>{entry.action}</strong>
                  <p>{entry.detail}</p>
                  <p className="muted">
                    {entry.actor_name || 'System'} · {entry.created_at}
                  </p>
                </div>
              </div>
            ))}
          </div>
        ) : (
          <p className="empty">No activity recorded.</p>
        )}
      </div>
    </div>
  )
}
