self.addEventListener('install', () => {
  self.skipWaiting()
})

self.addEventListener('activate', (event) => {
  event.waitUntil(self.clients.claim())
})

const resolveUrl = (rawUrl) => {
  const fallback = new URL('/notifications', self.location.origin)
  if (!rawUrl || typeof rawUrl !== 'string') {
    return fallback
  }

  try {
    return new URL(rawUrl, self.location.origin)
  } catch (_) {
    return fallback
  }
}

self.addEventListener('push', (event) => {
  let payload = {}

  try {
    payload = event.data ? event.data.json() : {}
  } catch (_) {
    payload = { title: 'New notification', body: event.data?.text() ?? '' }
  }

  const title = payload.title || 'New notification'
  const options = {
    body: payload.body || '',
    icon: payload.icon || '/vite.svg',
    badge: payload.badge || '/vite.svg',
    data: {
      url: payload.url || '/notifications',
      ...(payload.data || {}),
    },
  }

  event.waitUntil(self.registration.showNotification(title, options))
})

self.addEventListener('notificationclick', (event) => {
  event.notification.close()

  const target = resolveUrl(event.notification.data?.url)

  event.waitUntil(
    self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((windowClients) => {
      for (const client of windowClients) {
        const clientUrl = new URL(client.url)
        if (clientUrl.origin === target.origin) {
          client.focus()
          if (clientUrl.pathname !== target.pathname || clientUrl.search !== target.search || clientUrl.hash !== target.hash) {
            client.navigate(target.toString())
          }
          return
        }
      }

      return self.clients.openWindow(target.toString())
    }),
  )
})
