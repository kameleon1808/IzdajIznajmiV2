import Echo from 'laravel-echo'
import Pusher from 'pusher-js'

const useMockApi = (import.meta.env.VITE_USE_MOCK_API ?? 'true') !== 'false'

const appKey = import.meta.env.VITE_REVERB_APP_KEY
const host = import.meta.env.VITE_REVERB_HOST || (typeof window !== 'undefined' ? window.location.hostname : 'localhost')
const port = Number(import.meta.env.VITE_REVERB_PORT || 8080)
const scheme = import.meta.env.VITE_REVERB_SCHEME || 'http'
const forceTLS = scheme === 'https'
const apiBase = (import.meta.env.VITE_API_BASE_URL ?? '').replace(/\/$/, '')
const authEndpoint = apiBase ? `${apiBase}/broadcasting/auth` : '/broadcasting/auth'

let echo: Echo<'reverb'> | null = null

export const getEcho = () => {
  if (useMockApi || !appKey) return null
  if (echo) return echo

  ;(window as any).Pusher = Pusher

  echo = new Echo<'reverb'>({
    broadcaster: 'reverb',
    key: appKey,
    wsHost: host,
    wsPort: port,
    wssPort: port,
    forceTLS,
    enabledTransports: ['ws', 'wss'],
    authEndpoint,
    withCredentials: true,
  })

  return echo
}

export const disconnectEcho = () => {
  if (!echo) return
  echo.disconnect()
  echo = null
}
