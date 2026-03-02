import { createApp } from 'vue'
import { createPinia } from 'pinia'
import App from './App.vue'
import router from './router'
import './assets/main.css'
import { registerAuthHandlers } from './services/apiClient'
import { registerPushServiceWorker } from './services/push'
import { initSentry } from './services/sentry'
import { useAuthStore } from './stores/auth'

const app = createApp(App)

const pinia = createPinia()
app.use(pinia)
app.use(router)

const auth = useAuthStore(pinia)
registerAuthHandlers({
  onUnauthorized: () => auth.handleUnauthorized(),
})

if (!auth.isMockMode) {
  void registerPushServiceWorker()
}

// Sentry is initialised async before mount. If VITE_SENTRY_DSN is unset it
// is a no-op. The app.mount() does not wait for the SDK to load.
void initSentry(app)

app.mount('#app')
