import { apiClient } from './apiClient'

export interface PushDevice {
  id: string
  endpoint: string
  deviceLabel: string | null
  userAgent: string | null
  isEnabled: boolean
  createdAt: string | null
  updatedAt: string | null
}

type PushPermission = NotificationPermission | 'unsupported'

const enableInNonProd = (import.meta.env.VITE_ENABLE_WEB_PUSH ?? 'false') === 'true'
const vapidPublicKey = (import.meta.env.VITE_VAPID_PUBLIC_KEY ?? '').trim()

let registrationPromise: Promise<ServiceWorkerRegistration | null> | null = null

const bufferToBase64Url = (buffer: ArrayBuffer | null): string | null => {
  if (!buffer) return null
  const bytes = new Uint8Array(buffer)
  let binary = ''
  for (const value of bytes) {
    binary += String.fromCharCode(value)
  }
  return btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '')
}

const base64UrlToUint8Array = (base64Url: string): Uint8Array => {
  const padding = '='.repeat((4 - (base64Url.length % 4)) % 4)
  const base64 = (base64Url + padding).replace(/-/g, '+').replace(/_/g, '/')
  const raw = atob(base64)
  const output = new Uint8Array(new ArrayBuffer(raw.length))
  for (let index = 0; index < raw.length; index += 1) {
    output[index] = raw.charCodeAt(index)
  }
  return output
}

const mapDevice = (raw: any): PushDevice => ({
  id: String(raw.id),
  endpoint: String(raw.endpoint),
  deviceLabel: raw.deviceLabel ?? null,
  userAgent: raw.userAgent ?? null,
  isEnabled: Boolean(raw.isEnabled),
  createdAt: raw.createdAt ?? null,
  updatedAt: raw.updatedAt ?? null,
})

const defaultDeviceLabel = () => {
  const platform = (navigator.platform || '').trim()
  const browser = (() => {
    const ua = navigator.userAgent || ''
    if (ua.includes('Edg/')) return 'Edge'
    if (ua.includes('Firefox/')) return 'Firefox'
    if (ua.includes('Chrome/')) return 'Chrome'
    if (ua.includes('Safari/')) return 'Safari'
    return 'Browser'
  })()

  return platform ? `${browser} on ${platform}` : browser
}

const isSupported = () =>
  typeof window !== 'undefined' &&
  'serviceWorker' in navigator &&
  'PushManager' in window &&
  'Notification' in window

export const isPushFeatureEnabled = () => isSupported() && (import.meta.env.PROD || enableInNonProd)

export const getPushPermissionState = (): PushPermission => {
  if (!isSupported()) return 'unsupported'
  return Notification.permission
}

export const registerPushServiceWorker = async (): Promise<ServiceWorkerRegistration | null> => {
  if (!isPushFeatureEnabled()) {
    return null
  }

  if (!registrationPromise) {
    registrationPromise = navigator.serviceWorker
      .register('/sw.js')
      .then((registration) => registration)
      .catch((error) => {
        console.error('Service worker registration failed', error)
        return null
      })
  }

  return registrationPromise
}

const getRegistration = async () => {
  if (!isSupported()) return null
  const direct = await navigator.serviceWorker.getRegistration('/sw.js')
  if (direct) return direct
  return navigator.serviceWorker.getRegistration()
}

const toSubscriptionPayload = (subscription: PushSubscription) => {
  const serialized = subscription.toJSON()
  const p256dh = serialized.keys?.p256dh ?? bufferToBase64Url(subscription.getKey('p256dh'))
  const auth = serialized.keys?.auth ?? bufferToBase64Url(subscription.getKey('auth'))
  if (!p256dh || !auth) {
    throw new Error('Could not read push subscription keys from browser.')
  }

  return {
    endpoint: serialized.endpoint ?? subscription.endpoint,
    keys: {
      p256dh,
      auth,
    },
  }
}

export const getCurrentPushEndpoint = async (): Promise<string | null> => {
  const registration = await getRegistration()
  if (!registration) return null
  const subscription = await registration.pushManager.getSubscription()
  return subscription?.endpoint ?? null
}

export const subscribeCurrentDevicePush = async (deviceLabel?: string): Promise<string> => {
  if (!isPushFeatureEnabled()) {
    throw new Error('Push notifications are disabled in this environment.')
  }

  if (!vapidPublicKey) {
    throw new Error('Missing VAPID public key configuration.')
  }

  const registration = await registerPushServiceWorker()
  if (!registration) {
    throw new Error('Service worker registration failed.')
  }

  if (Notification.permission === 'denied') {
    throw new Error('Notification permission is blocked in browser settings.')
  }

  let permission: NotificationPermission = Notification.permission
  if (permission !== 'granted') {
    permission = await Notification.requestPermission()
  }

  if (permission !== 'granted') {
    throw new Error('Notification permission was not granted.')
  }

  let subscription = await registration.pushManager.getSubscription()
  if (!subscription) {
    subscription = await registration.pushManager.subscribe({
      userVisibleOnly: true,
      applicationServerKey: base64UrlToUint8Array(vapidPublicKey) as unknown as BufferSource,
    })
  }

  const payload = toSubscriptionPayload(subscription)

  await apiClient.post('/push/subscribe', {
    endpoint: payload.endpoint,
    keys: payload.keys,
    deviceLabel: deviceLabel?.trim() || defaultDeviceLabel(),
  })

  return payload.endpoint
}

export const unsubscribeCurrentDevicePush = async (): Promise<string | null> => {
  const registration = await getRegistration()
  const subscription = await registration?.pushManager.getSubscription()

  let endpoint: string | null = subscription?.endpoint ?? null

  if (subscription) {
    try {
      await subscription.unsubscribe()
    } catch (error) {
      console.warn('Failed to unsubscribe browser push subscription', error)
    }
  }

  if (!endpoint && isPushFeatureEnabled()) {
    endpoint = await getCurrentPushEndpoint()
  }

  if (endpoint) {
    await apiClient.post('/push/unsubscribe', { endpoint })
  }

  return endpoint
}

export const disablePushEndpoint = async (endpoint: string): Promise<void> => {
  await apiClient.post('/push/unsubscribe', { endpoint })
}

export const fetchPushDevices = async (): Promise<PushDevice[]> => {
  const { data } = await apiClient.get('/push/subscriptions')
  const list = Array.isArray(data?.data) ? data.data : []
  return list.map(mapDevice)
}
