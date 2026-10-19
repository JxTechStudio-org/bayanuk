import { fileURLToPath, URL } from 'node:url'
import { defineConfig } from 'vitest/config'
import react from '@vitejs/plugin-react'
import tailwindcss from '@tailwindcss/vite'

export default defineConfig({
  plugins: [react(), tailwindcss()],
  resolve: {
    alias: {
      // "@/..." means "src/..."
      '@': fileURLToPath(new URL('./src', import.meta.url)),
    },
  },
  server: {
    // Integration contract, section 2: in development, the API and the CSRF cookie come from
    // the backend on port 8000 through this server, so both sides share one origin and cookies work.
    proxy: {
      '/api': 'http://localhost:8000',
      '/sanctum': 'http://localhost:8000',
    },
  },
  test: {
    // jsdom gives the tests a fake browser page to render into.
    environment: 'jsdom',
    setupFiles: ['./src/test/setup.ts'],
  },
})