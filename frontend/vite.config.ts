import { defineConfig, loadEnv } from 'vite'
import vue from '@vitejs/plugin-vue'

// https://vite.dev/config/
export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, process.cwd(), '')
  const apiTarget = env.API_PROXY_TARGET || env.VITE_API_BASE_URL || 'http://localhost:8000'
  const rawAllowedHosts =
    process.env.VITE_ALLOWED_HOSTS || env.VITE_ALLOWED_HOSTS || 'localhost,127.0.0.1,.trycloudflare.com'
  const allowedHosts = rawAllowedHosts
    .split(',')
    .map((host) => host.trim())
    .filter(Boolean)

  return {
    plugins: [vue()],
    server: {
      allowedHosts,
      proxy: {
        '/api': {
          target: apiTarget,
          changeOrigin: true,
          secure: false,
        },
        '/broadcasting': {
          target: apiTarget,
          changeOrigin: true,
          secure: false,
        },
        '/sanctum': {
          target: apiTarget,
          changeOrigin: true,
          secure: false,
        },
        '/storage': {
          target: apiTarget,
          changeOrigin: true,
          secure: false,
        },
      },
    },
    preview: {
      allowedHosts,
    },
  }
})
