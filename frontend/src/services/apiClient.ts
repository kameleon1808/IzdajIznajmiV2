import axios, { AxiosError } from 'axios'
import { useToastStore } from '../stores/toast'

const baseURL = `${import.meta.env.VITE_API_BASE_URL ?? ''}/api`

let getToken: () => string | null = () => null
let onUnauthorized: () => Promise<void> | void = () => {}

export const registerAuthHandlers = (options: {
  getToken: () => string | null
  onUnauthorized: () => Promise<void> | void
}) => {
  getToken = options.getToken
  onUnauthorized = options.onUnauthorized
}

export const apiClient = axios.create({
  baseURL,
  timeout: 10000,
  headers: {
    'Content-Type': 'application/json',
    Accept: 'application/json',
  },
  withCredentials: false,
})

apiClient.interceptors.request.use((config) => {
  const token = getToken()
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
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
