import axios, { AxiosError } from 'axios'
import { useToastStore } from '../stores/toast'

const apiBaseUrl = (import.meta.env.VITE_API_BASE_URL ?? '').replace(/\/$/, '')
const baseURL = `${apiBaseUrl}/api/v1`

let onUnauthorized: () => Promise<void> | void = () => {}
let csrfPromise: Promise<void> | null = null
const DEVICE_ID_KEY = 'ii-device-id'

const getDeviceId = (): string | null => {
  if (typeof localStorage === 'undefined') return null
  let existing = localStorage.getItem(DEVICE_ID_KEY)
  if (existing) return existing
  const next =
    typeof crypto !== 'undefined' && 'randomUUID' in crypto
      ? crypto.randomUUID()
      : Math.random().toString(36).slice(2) + Date.now().toString(36)
  localStorage.setItem(DEVICE_ID_KEY, next)
  return next
}

export const registerAuthHandlers = (options: { onUnauthorized: () => Promise<void> | void }) => {
  onUnauthorized = options.onUnauthorized
}

export const ensureCsrfCookie = async () => {
  if (!csrfPromise) {
    csrfPromise = axios
      .get(`${apiBaseUrl}/sanctum/csrf-cookie`, {
        withCredentials: true,
      })
      .then(() => undefined)
      .finally(() => {
        csrfPromise = null
      })
  }
  return csrfPromise
}

export const apiClient = axios.create({
  baseURL: baseURL || '/api/v1',
  timeout: 10000,
  headers: {
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
  },
  withCredentials: true,
  xsrfCookieName: 'XSRF-TOKEN',
  xsrfHeaderName: 'X-XSRF-TOKEN',
})

const readCookie = (name: string): string | null => {
  if (typeof document === 'undefined') return null
  const match = document.cookie.match(new RegExp('(^|; )' + name.replace(/([.*+?^${}()|[\\]\\\\])/g, '\\$1') + '=([^;]*)'))
  return match && match[2] !== undefined ? decodeURIComponent(match[2]) : null
}

apiClient.interceptors.request.use((config) => {
  const xsrf = readCookie('XSRF-TOKEN')
  if (xsrf && (!config.headers || !config.headers['X-XSRF-TOKEN'])) {
    config.headers = config.headers ?? {}
    config.headers['X-XSRF-TOKEN'] = xsrf
  }
  const deviceId = getDeviceId()
  if (deviceId) {
    config.headers = config.headers ?? {}
    config.headers['X-Device-Id'] = deviceId
  }
  if (config.data instanceof FormData) {
    // Let the browser set the boundary for multipart
    if (config.headers) {
      delete config.headers['Content-Type']
    }
  } else {
    config.headers['Content-Type'] = config.headers['Content-Type'] ?? 'application/json'
  }
  return config
})

apiClient.interceptors.response.use(
  (response) => response,
  async (error: AxiosError) => {
    const toast = useToastStore()
    const status = error.response?.status
    const requestId =
      (error.response?.headers as Record<string, string | undefined> | undefined)?.['x-request-id'] ||
      (error.response?.data as any)?.request_id
    if (status === 401) {
      toast.push({ title: 'Session expired', message: 'Please log in again.', type: 'error' })
      await onUnauthorized()
    } else if (status === 403) {
      toast.push({ title: 'Not allowed', message: 'You do not have permission to do that.', type: 'error' })
    } else if (status === 413) {
      toast.push({ title: 'File too large', message: 'The uploaded files exceed the allowed size limit.', type: 'error' })
    } else if (status === 429) {
      const retryAfter = (error.response?.headers as any)?.['retry-after']
      toast.push({
        title: 'Too many requests',
        message: retryAfter ? `Try again in ${retryAfter}s.` : 'Please wait a moment.',
        type: 'error',
      })
    }
    const message =
      (error.response?.data as any)?.message ||
      error.message ||
      'Something went wrong. Please try again.'
    const withRequestId =
      requestId && status && status >= 500
        ? `${message} (Request ID: ${requestId})`
        : message
    return Promise.reject({
      status,
      message: withRequestId,
      errors: (error.response?.data as any)?.errors,
      requestId,
    })
  },
)

export type ApiError = {
  status?: number
  message: string
  errors?: Record<string, string[]>
  requestId?: string
}
