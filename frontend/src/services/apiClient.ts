import axios, { AxiosError } from 'axios'
import { useToastStore } from '../stores/toast'

const apiBaseUrl = (import.meta.env.VITE_API_BASE_URL ?? '').replace(/\/$/, '')
const baseURL = `${apiBaseUrl}/api/v1`

let onUnauthorized: () => Promise<void> | void = () => {}
let csrfPromise: Promise<void> | null = null

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
  return match ? decodeURIComponent(match[2]) : null
}

apiClient.interceptors.request.use((config) => {
  const xsrf = readCookie('XSRF-TOKEN')
  if (xsrf && (!config.headers || !config.headers['X-XSRF-TOKEN'])) {
    config.headers = config.headers ?? {}
    config.headers['X-XSRF-TOKEN'] = xsrf
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
    if (status === 401) {
      toast.push({ title: 'Session expired', message: 'Please log in again.', type: 'error' })
      await onUnauthorized()
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
    return Promise.reject({
      status,
      message,
      errors: (error.response?.data as any)?.errors,
    })
  },
)

export type ApiError = {
  status?: number
  message: string
  errors?: Record<string, string[]>
}
