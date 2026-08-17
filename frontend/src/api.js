// ---------------------------------------------------------------------------
// API client — talks to the PHP JSON API (api.php GET / api_actions.php POST).
// Same-origin in dev (Vite proxy) and production (PHP serves dist/).
//
// Paths are resolved against the document base so the SPA also works when
// deployed at a subpath (e.g. http://localhost/kyc-v4/). index.php injects a
// <base href="..."> tag in production; in dev the base is '/'.
// ---------------------------------------------------------------------------

/**
 * The application base path (e.g. "/kyc-v4/"). In dev this is "/".
 * Used for the router basename and API/upload URL resolution.
 */
export function baseUrl() {
  try {
    const base = document.querySelector('base')?.getAttribute('href') || '/'
    return base.endsWith('/') ? base : base + '/'
  } catch {
    return '/'
  }
}

const API = baseUrl() + 'api.php'
const ACTIONS = baseUrl() + 'api_actions.php'

/**
 * Resolve a server-relative URL (e.g. "uploads/users/3/abc.png") against the
 * document base so it works at any subpath and on deep client-side routes.
 */
export function resolveUrl(path) {
  if (!path) return path
  if (/^https?:\/\//i.test(path) || path.startsWith('data:') || path.startsWith('blob:')) {
    return path
  }
  return path.startsWith('/') ? baseUrl().replace(/\/$/, '') + path : baseUrl() + path
}

let csrfToken = null
let csrfPromise = null

export class ApiError extends Error {
  constructor(message, status) {
    super(message)
    this.status = status
  }
}

/** Fetch the session CSRF token once and cache it (retried on 419). */
export function getCsrf() {
  if (csrfToken) return Promise.resolve(csrfToken)
  if (csrfPromise) return csrfPromise
  csrfPromise = fetch(`${API}?action=csrf`)
    .then((r) => r.json())
    .then((body) => {
      if (!body.ok) throw new ApiError(body.error || 'Could not load security token', 0)
      csrfToken = body.data.csrf
      return csrfToken
    })
    .finally(() => {
      csrfPromise = null
    })
  return csrfPromise
}

/** Core request helper. Returns the parsed JSON body. */
async function request(url, options = {}) {
  const headers = { ...(options.headers || {}) }

  const isForm = options.body instanceof FormData
  if (!isForm) {
    headers['Content-Type'] = 'application/json'
  }

  // Attach the CSRF token to anything that mutates state.
  if (options.method && options.method !== 'GET') {
    try {
      headers['X-CSRF-Token'] = await getCsrf()
    } catch {
      // token fetch failed — let the server respond and surface the error
    }
  }

  const res = await fetch(url, { credentials: 'include', ...options, headers })

  let body = null
  const text = await res.text()
  try {
    body = text ? JSON.parse(text) : null
  } catch {
    body = { ok: false, error: text || 'Unexpected server response.' }
  }

  if (!body || body.ok !== true) {
    const err = new ApiError(body?.error || 'Request failed.', res.status)
    err.body = body
    throw err
  }
  return body
}

/** GET a read endpoint: api.php?action=...&params */
export function apiGet(action, params = {}) {
  const qs = new URLSearchParams({ action, ...params }).toString()
  return request(`${API}?${qs}`, { method: 'GET' })
}

/** POST a write action to api_actions.php with a JSON body. */
export function apiPost(action, data = {}) {
  return request(ACTIONS, {
    method: 'POST',
    body: JSON.stringify({ action, ...data }),
  })
}

/** POST a multipart form (document uploads) to api_actions.php. */
export function apiUpload(action, fields, file) {
  const fd = new FormData()
  fd.append('action', action)
  Object.entries(fields).forEach(([k, v]) => fd.append(k, v))
  fd.append('document', file)
  return request(ACTIONS, { method: 'POST', body: fd })
}

/** Reset the cached CSRF token (call after login/logout or on 419). */
export function resetCsrf() {
  csrfToken = null
}
