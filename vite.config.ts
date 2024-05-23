import {defineConfig} from 'vite';
import react from '@vitejs/plugin-react';
import tsconfigPaths from 'vite-tsconfig-paths';

export default defineConfig({
  plugins: [react(), tsconfigPaths()],
  build: {
    outDir: 'resources/dist',
    emptyOutDir: true,
    manifest: 'manifest.json',
    rollupOptions: {
      input: {
        app: 'resources/ts/main.tsx',
      },
    },
  },
  resolve: {
    alias: {
      '@': 'resources/ts',
    },
  },
});
