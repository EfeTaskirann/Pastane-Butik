import { defineConfig } from 'vite';
import legacy from '@vitejs/plugin-legacy';
import compression from 'vite-plugin-compression';
import path from 'path';

export default defineConfig({
  // Build configuration
  build: {
    outDir: 'dist',
    assetsDir: 'assets',
    manifest: true,
    sourcemap: false, // Enable in development if needed
    minify: 'terser',
    terserOptions: {
      compress: {
        drop_console: true,
        drop_debugger: true,
      },
    },
    rollupOptions: {
      input: {
        // Main bundles
        main: path.resolve(__dirname, 'assets/js/main.js'),
        admin: path.resolve(__dirname, 'assets/js/admin.js'),

        // Styles
        styles: path.resolve(__dirname, 'assets/scss/main.scss'),
        adminStyles: path.resolve(__dirname, 'assets/scss/admin.scss'),
      },
      output: {
        entryFileNames: 'js/[name].[hash].js',
        chunkFileNames: 'js/chunks/[name].[hash].js',
        assetFileNames: (assetInfo) => {
          const extType = assetInfo.name.split('.').pop();
          if (/css/i.test(extType)) {
            return 'css/[name].[hash][extname]';
          }
          if (/png|jpe?g|svg|gif|webp|ico/i.test(extType)) {
            return 'images/[name].[hash][extname]';
          }
          if (/woff2?|eot|ttf|otf/i.test(extType)) {
            return 'fonts/[name].[hash][extname]';
          }
          return 'assets/[name].[hash][extname]';
        },
        manualChunks: {
          // Vendor chunks
          vendor: ['chart.js', 'flatpickr', 'sortablejs', 'sweetalert2'],
        },
      },
    },
    // CSS code splitting
    cssCodeSplit: true,
    // Asset size warnings
    chunkSizeWarningLimit: 500,
  },

  // Development server
  server: {
    port: 3000,
    proxy: {
      '/api': {
        target: 'http://localhost',
        changeOrigin: true,
      },
    },
  },

  // CSS configuration
  css: {
    preprocessorOptions: {
      scss: {
        additionalData: `@import "@/scss/_variables.scss";`,
      },
    },
    postcss: {
      plugins: [
        require('autoprefixer'),
        require('cssnano')({
          preset: 'default',
        }),
      ],
    },
  },

  // Resolve aliases
  resolve: {
    alias: {
      '@': path.resolve(__dirname, 'assets'),
    },
  },

  // Plugins
  plugins: [
    // Legacy browser support
    legacy({
      targets: ['> 1%', 'last 2 versions', 'not dead'],
      additionalLegacyPolyfills: ['regenerator-runtime/runtime'],
    }),

    // Gzip compression
    compression({
      algorithm: 'gzip',
      ext: '.gz',
      threshold: 10240,
    }),

    // Brotli compression
    compression({
      algorithm: 'brotliCompress',
      ext: '.br',
      threshold: 10240,
    }),
  ],
});
