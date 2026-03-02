/**
 * Sentry browser SDK integration.
 *
 * Initialised only when VITE_SENTRY_DSN is set. No PII (email, name, phone)
 * is ever attached to Sentry events — only user_id and role.
 *
 * Usage: call initSentry(app) once in main.ts before app.mount().
 */
import type { App } from 'vue'

// eslint-disable-next-line @typescript-eslint/no-explicit-any
type SentryModule = any

let sentry: SentryModule | null = null

export async function initSentry(app: App): Promise<void> {
  const dsn = import.meta.env.VITE_SENTRY_DSN
  if (!dsn) return

  try {
    // @ts-ignore — optional peer-dep; npm install required before use
    sentry = await import('@sentry/vue')
    sentry.init({
      app,
      dsn,
      environment: import.meta.env.VITE_SENTRY_ENVIRONMENT ?? import.meta.env.MODE,
      release: import.meta.env.VITE_SENTRY_RELEASE,
      // Do not send full URL since it may contain sensitive query params.
      sendDefaultPii: false,
      integrations: [
        sentry.browserTracingIntegration(),
      ],
      // Low sample rate for performance tracing; errors always reported.
      tracesSampleRate: Number(import.meta.env.VITE_SENTRY_TRACES_SAMPLE_RATE ?? 0.1),
    })
  } catch {
    // Sentry SDK unavailable — do not break the app.
  }
}

/**
 * Set user context on the active Sentry scope.
 * Call after successful login. Pass null to clear the user on logout.
 */
export function setSentryUser(user: { id: number; role: string } | null): void {
  if (!sentry) return
  if (user) {
    sentry.setUser({ id: user.id, segment: user.role })
  } else {
    sentry.setUser(null)
  }
}

/**
 * Capture an unexpected error in Sentry (best-effort, silent on failure).
 */
export function captureException(error: unknown): void {
  if (!sentry) return
  try {
    sentry.captureException(error)
  } catch {
    // intentionally silent
  }
}
