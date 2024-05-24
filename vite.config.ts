import {defineConfig} from 'vite';
import react from '@vitejs/plugin-react';
import tsconfigPaths from 'vite-tsconfig-paths';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
  plugins: [laravel(['resources/ts/app.tsx']), react(), tsconfigPaths()],
  build: {
    outDir: 'resources/dist',
    emptyOutDir: true,
    rollupOptions: {
      output: {
        entryFileNames: '[name].js',
        chunkFileNames: '[name].js',
        assetFileNames: '[name][extname]',
      },
    },
  },
  resolve: {
    alias: {
      '@': 'resources/ts',
    },
  },
});
