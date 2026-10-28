import { rmSync } from 'node:fs'
import { fileURLToPath, URL } from 'node:url'
import { defineConfig } from 'vitest/config'
import react from '@vitejs/plugin-react'
import tailwindcss from '@tailwindcss/vite'

export default defineConfig({
  plugins: [
    react(),
    tailwindcss(),
    {
      // Integration contract, section 12: the mock API is never part of the production build.
      // The mock code is already left out (src/main.tsx loads it only when VITE_API_MOCK is true),
      // but Vite copies everything in public/, so its service worker file is removed after the build.
      name: 'remove-mock-service-worker',
      apply: 'build',
      closeBundle() {
        rmSync(fileURLToPath(new URL('./dist/mockServiceWorker.js', import.meta.url)), { force: true })
      },
    },
  ],
  build: {
    rolldownOptions: {
      output: {
        // The libraries go in their own files, apart from our code. They change rarely, so after an update
        // of the site the browser keeps them from its cache and downloads only our changed code.
        // React and the router are the largest, so they get one file and the other libraries another.
        codeSplitting: {
          groups: [
            { name: 'react', test: /node_modules[\\/](react|react-dom|react-router|scheduler)[\\/]/ },
            // The PDF library (react-pdf, the Technical Plan's viewer) is large and used by the document viewer only.
            // It is left out of "vendor", so it stays with the viewer, which loads only when it is shown.
            { name: 'vendor', test: /node_modules[\\/](?!react-pdf[\\/]|pdfjs-dist[\\/])/ },
          ],
        },
      },
    },
  },
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
    // The test files run side by side, each in its own fake browser page. On a busy computer a page test
    // can take more than the default 5 seconds without anything being wrong, so each test gets 15.
    testTimeout: 15_000,
  },
})