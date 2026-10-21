import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';
import { defineConfig } from 'vite';

export default defineConfig({
  plugins: [react(), tailwindcss()],
  resolve: {
    alias: {
      '@': import.meta.dirname + '/src',
    },
  },
  build: {
    chunkSizeWarningLimit: 1000, // Silenciar advertencia para el chunk vendor grande (es cacheable)
    rolldownOptions: {
      output: {
        codeSplitting: {
          minSize: 20000,
          groups: [
            {
              name: 'react-vendor',
              test: /node_modules[\\/](?:react|react-dom|scheduler)(?:[\\/]|$)/,
              priority: 30,
            },
            {
              name: 'ui-vendor',
              test: /node_modules[\\/](?:@radix-ui|lucide-react|sonner|qrcode\.react|@tanstack)(?:[\\/]|$)/,
              priority: 20,
            },
            {
              name: 'vendor',
              test: /node_modules[\\/]/,
              priority: 10,
            },
          ],
        },
      },
    },
  },
});
