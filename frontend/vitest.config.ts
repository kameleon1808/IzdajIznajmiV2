import { defineConfig } from 'vitest/config'
import vue from '@vitejs/plugin-vue'
import nodeCrypto from 'node:crypto'

if (!(nodeCrypto as any).hash) {
  ;(nodeCrypto as any).hash = (algorithm: string, data: any, encoding: any) =>
    nodeCrypto.createHash(algorithm).update(data).digest(encoding)
}

export default defineConfig({
  plugins: [vue()],
  test: {
    globals: true,
    environment: 'jsdom',
    include: ['tests/**/*.spec.ts'],
  },
})
