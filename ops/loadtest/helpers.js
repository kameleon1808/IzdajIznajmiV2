import http from 'k6/http'
import { check } from 'k6'

export const baseUrl = (__ENV.BASE_URL || 'http://localhost:8000').replace(/\/$/, '')
export const apiBaseUrl = `${baseUrl}/api/v1`

export function buildOptions(flow, overrides = {}) {
  const p95 = Number(__ENV.K6_P95_MS || 500)
  return {
    vus: Number(__ENV.K6_VUS || 5),
    duration: __ENV.K6_DURATION || '30s',
    thresholds: {
      http_req_duration: [`p(95)<${p95}`],
      http_req_failed: ['rate<0.01'],
    },
    tags: { flow },
    ...overrides,
  }
}

export function loginWithSession(jar) {
  const email = __ENV.LOGIN_EMAIL
  const password = __ENV.LOGIN_PASSWORD
  if (!email || !password) {
    throw new Error('LOGIN_EMAIL and LOGIN_PASSWORD are required for authenticated load tests.')
  }

  const csrf = http.get(`${baseUrl}/sanctum/csrf-cookie`, { jar, tags: { step: 'csrf' } })
  check(csrf, { 'csrf cookie fetched': (r) => r.status === 204 || r.status === 200 })

  const xsrfToken = getXsrfToken(jar)
  const headers = {
    Accept: 'application/json',
    'Content-Type': 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
  }
  if (xsrfToken) {
    headers['X-XSRF-TOKEN'] = xsrfToken
  }

  return http.post(
    `${apiBaseUrl}/auth/login`,
    JSON.stringify({ email, password }),
    { jar, headers, tags: { step: 'login' } },
  )
}

export function authJsonHeaders(jar) {
  const headers = {
    Accept: 'application/json',
    'Content-Type': 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
  }
  const xsrfToken = getXsrfToken(jar)
  if (xsrfToken) {
    headers['X-XSRF-TOKEN'] = xsrfToken
  }

  return headers
}

export function resolveListingId() {
  if (__ENV.LISTING_ID) {
    return __ENV.LISTING_ID
  }

  const response = http.get(`${apiBaseUrl}/listings?perPage=1`, { tags: { step: 'listing_seed' } })
  check(response, { 'listing seed request ok': (r) => r.status === 200 })
  const body = safeJson(response)
  const listingId = body?.data?.[0]?.id
  if (!listingId) {
    throw new Error('No listing available for load test. Set LISTING_ID explicitly.')
  }

  return String(listingId)
}

export function resolveConversationId(jar, listingId) {
  if (__ENV.CONVERSATION_ID) {
    return __ENV.CONVERSATION_ID
  }

  const response = http.get(
    `${apiBaseUrl}/listings/${listingId}/conversation`,
    {
      jar,
      headers: authJsonHeaders(jar),
      tags: { step: 'conversation_seed' },
    },
  )
  check(response, { 'conversation endpoint ok': (r) => r.status === 200 })

  const body = safeJson(response)
  const id = body?.id ?? body?.data?.id
  if (!id) {
    throw new Error('Failed to resolve conversation id. Set CONVERSATION_ID explicitly.')
  }

  return String(id)
}

function getXsrfToken(jar) {
  const cookies = jar.cookiesForURL(baseUrl)
  const tokenCookie = cookies['XSRF-TOKEN'] && cookies['XSRF-TOKEN'][0]
  if (!tokenCookie || !tokenCookie.value) {
    return null
  }

  try {
    return decodeURIComponent(tokenCookie.value)
  } catch {
    return tokenCookie.value
  }
}

function safeJson(response) {
  try {
    return response.json()
  } catch {
    return null
  }
}
