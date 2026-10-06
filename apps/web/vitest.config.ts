import { defineConfig, mergeConfig } from 'vitest/config'
import viteConfig from './vite.config'

export default mergeConfig(
  viteConfig,
  defineConfig({
    test: {
      environment: 'jsdom',
      exclude: ['node_modules', 'dist'],
      globals: true,
      setupFiles: ['./tests/setup.ts'],
    }
  })
)
