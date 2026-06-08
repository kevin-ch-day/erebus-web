import { defineConfig } from 'vite';
import path from 'node:path';

export default defineConfig({
  publicDir: false,
  build: {
    outDir: path.resolve(__dirname, 'public/assets/build'),
    emptyOutDir: true,
    sourcemap: false,
    lib: {
      entry: path.resolve(__dirname, 'frontend/app-shell.ts'),
      formats: ['iife'],
      name: 'ErebusWebShell',
      fileName: () => 'app-shell.js',
    },
    rollupOptions: {
      output: {
        extend: true,
      },
    },
  },
});
