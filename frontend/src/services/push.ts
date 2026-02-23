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

export type PushAvailabilityCode =
  | 'ok'
  | 'disabled_by_config'
  | 'insecure_context'
  | 'ios_home_screen_required'
  | 'notification_unsupported'
  | 'service_worker_unsupported'
  | 'push_unsupported'
  | 'missing_vapid_key'

export interface PushAvailability {
  code: PushAvailabilityCode
  supported: boolean
}

const rawPushFlag = import.meta.env.VITE_ENABLE_WEB_PUSH
const vapidPublicKey = (import.meta.env.VITE_VAPID_PUBLIC_KEY ?? '').trim()

let registrationPromise: Promise<ServiceWorkerRegistration | null> | null = null

const isIosDevice = () => {
  if (typeof navigator === 'undefined') return false
  const ua = navigator.userAgent || ''
  return /iPad|iPhone|iPod/.test(ua) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1)
}

const isStandaloneDisplayMode = () => {
  if (typeof window === 'undefined') return false
  const standalone = window.matchMedia?.('(display-mode: standalone)').matches ?? false
  const iosStandalone = Boolean((navigator as Navigator & { standalone?: boolean }).standalone)
  return standalone || iosStandalone
}

const isPushConfigEnabled = () => {
  if (rawPushFlag === undefined) {
    return Boolean(import.meta.env.PROD)
  }
  return rawPushFlag === 'true'
}

const toArrayBuffer = (value: ArrayBuffer | ArrayBufferView | null | undefined): ArrayBufferLike | null => {
  if (!value) return null
  if (value instanceof ArrayBuffer) return value
  const view = value as ArrayBufferView
  return view.buffer.slice(view.byteOffset, view.byteOffset + view.byteLength)
}

const bufferToBase64Url = (buffer: ArrayBufferLike | null): string | null => {
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
    if (ua.includes('Brave/')) return 'Brave'
    if (ua.includes('SamsungBrowser/')) return 'Samsung Internet'
    if (ua.includes('OPR/')) return 'Opera'
    if (ua.includes('Edg/')) return 'Edge'
    if (ua.includes('Firefox/')) return 'Firefox'
    if (ua.includes('Chrome/')) return 'Chrome'
    if (ua.includes('Safari/')) return 'Safari'
    return 'Browser'
  })()

  return platform ? `${browser} on ${platform}` : browser
}

const getPushAvailabilityInternal = (): PushAvailability => {
  if (typeof window === 'undefined') {
    return { code: 'service_worker_unsupported', supported: false }
  }

  if (!isPushConfigEnabled()) {
    return { code: 'disabled_by_config', supported: false }
  }

  if (!window.isSecureContext) {
    return { code: 'insecure_context', supported: false }
  }

  if (!('Notification' in window)) {
    return { code: 'notification_unsupported', supported: false }
  }

  if (!('serviceWorker' in navigator)) {
    return { code: 'service_worker_unsupported', supported: false }
  }

  if (isIosDevice() && !isStandaloneDisplayMode()) {
    return { code: 'ios_home_screen_required', supported: false }
  }

  if (!('PushManager' in window)) {
    return { code: 'push_unsupported', supported: false }
  }

  if (!vapidPublicKey) {
    return { code: 'missing_vapid_key', supported: false }
  }

  return { code: 'ok', supported: true }
}

const isApiSupported = () => {
  const availability = getPushAvailabilityInternal()
  return availability.code !== 'disabled_by_config'
}

const normalizeBase64Url = (value: string) => value.replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '')

const hasMismatchedApplicationServerKey = (subscription: PushSubscription, expectedVapidKey: string) => {
  const existingKey = bufferToBase64Url(
    toArrayBuffer(
      subscription.options?.applicationServerKey as unknown as ArrayBuffer | ArrayBufferView | null | undefined,
    ),
  )
  if (!existingKey) return false
  return normalizeBase64Url(existingKey) !== normalizeBase64Url(expectedVapidKey)
}

export const getPushAvailability = (): PushAvailability => getPushAvailabilityInternal()

export const isPushFeatureEnabled = () => getPushAvailabilityInternal().code === 'ok'

export const getPushPermissionState = (): PushPermission => {
  if (!isApiSupported()) return 'unsupported'
  return Notification.permission
}

export const registerPushServiceWorker = async (): Promise<ServiceWorkerRegistration | null> => {
  const availability = getPushAvailabilityInternal()
  if (availability.code !== 'ok' && availability.code !== 'missing_vapid_key') {
    return null
  }

  if (!registrationPromise) {
    registrationPromise = navigator.serviceWorker
      .register('/sw.js')
      .then(async (registration) => {
        await navigator.serviceWorker.ready.catch(() => registration)
        return registration
      })
      .catch((error) => {
        registrationPromise = null
        console.error('Service worker registration failed', error)
        return null
      })
  }

  return registrationPromise
}

const getRegistration = async () => {
  if (!isApiSupported()) return null
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
  const availability = getPushAvailabilityInternal()
  if (availability.code !== 'ok') {
    if (availability.code === 'missing_vapid_key') {
      throw new Error('Missing VAPID public key configuration.')
    }
    if (availability.code === 'ios_home_screen_required') {
      throw new Error('On iPhone, install this app to Home Screen first and then enable push.')
    }
    if (availability.code === 'insecure_context') {
      throw new Error('Push requires HTTPS. Open the app on an https:// URL.')
    }
    if (availability.code === 'disabled_by_config') {
      throw new Error('Push notifications are disabled in this environment.')
    }
    throw new Error('Push notifications are not supported in this browser environment.')
  }

  const vapidKeyBytes = base64UrlToUint8Array(vapidPublicKey)

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
  if (subscription && hasMismatchedApplicationServerKey(subscription, vapidPublicKey)) {
    await subscription.unsubscribe().catch(() => undefined)
    subscription = null
  }

  if (!subscription) {
    try {
      subscription = await registration.pushManager.subscribe({
        userVisibleOnly: true,
        applicationServerKey: vapidKeyBytes as unknown as BufferSource,
      })
    } catch (error) {
      const raw = (error as Error)?.message ?? ''
      if (raw.includes('Registration failed - push service error')) {
        throw new Error(
          'Browser push service rejected registration. Verify browser push services are enabled and try again.',
        )
      }
      throw error
    }
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

  if (!endpoint && isApiSupported()) {
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
