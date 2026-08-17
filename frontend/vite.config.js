import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

// The PHP backend runs on :8099 in development. The Vite dev server proxies
// API calls and uploads to it so everything stays same-origin (session cookie
// + CSRF work without extra config). The client always calls /api.php,
// /api_actions.php and /uploads/... — the same URLs it will use in production,
// so no environment-specific URL switching is needed.
export default defineConfig({
  plugins: [react()],
  // Relative base so the built SPA works when deployed at a subpath
  // (e.g. http://localhost/kyc-v4/) and not just at the domain root.
  base: './',
  server: {
    port: 5173,
    proxy: {
      '/api.php': {
        target: 'http://127.0.0.1:8099',
        changeOrigin: true,
      },
      '/api_actions.php': {
        target: 'http://127.0.0.1:8099',
        changeOrigin: true,
      },
      '/uploads': {
        target: 'http://127.0.0.1:8099',
        changeOrigin: true,
      },
    },
  },
  build: {
    outDir: '../dist',
    emptyOutDir: true,
  },
})
