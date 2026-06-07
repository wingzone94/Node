import { defineConfig } from 'vite';
import { resolve } from 'path';

export default defineConfig({
  base: './',
  build: {
    manifest: true,
    outDir: 'assets',
    emptyOutDir: false,
    rollupOptions: {
      input: {
        main: resolve(__dirname, 'src/main.js'),
        style: resolve(__dirname, 'src/styles/style.css'),
        editor: resolve(__dirname, 'src/editor.js')
      },
      output: {
        entryFileNames: `js/[name].[hash].js`,
        chunkFileNames: `js/[name].[hash].js`,
        assetFileNames: (assetInfo) => {
          if (assetInfo.name.endsWith('.css')) {
            return 'css/[name].[ext]';
          }
          return '[ext]/[name].[ext]';
        },
        manualChunks: (id) => {
          if (id.includes('node_modules')) {
            if (
              id.includes('node_modules/colorthief')
              || id.includes('node_modules/@lokesh.dhakar')
              || id.includes('node_modules/ndarray')
              || id.includes('node_modules/cwise-compiler')
              || id.includes('node_modules/uniq')
              || id.includes('node_modules/iota-array')
            ) {
              return 'color-tools';
            }
            return 'vendor';
          }
        }
      }
    }
  }
});
