import path from 'path';

export default ({ command }) => ({
  build: {
    emptyOutDir: true,
    manifest: false,
    outDir: './src/web/assets/dist',
    rollupOptions: {
      input: {
        'monet': './src/web/assets/src/js/Monet.js'
      },
      output: {
        sourcemap: true,
        entryFileNames: `assets/[name].js`,
        chunkFileNames: `assets/[name].js`,
        assetFileNames: `assets/[name].[ext]`
      }
    }
  },
  publicDir: './src/web/assets/public',
  resolve: {
    alias: {
      '@': path.resolve(__dirname, './src'),
      'vue': 'vue/dist/vue.esm.js',
    },
    preserveSymlinks: true,
  }
})