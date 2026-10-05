import { defineConfig } from 'orval';
import { loadEnv } from 'vite';

const env = loadEnv('development', process.cwd(), '');
const apiUrl = env.VITE_API_URL || 'http://localhost:8000';

export default defineConfig({
  api: {
    input: `${apiUrl}/docs/api.json`,
    output: {
      mode: 'tags-split',
      target: 'src/api/generated/endpoints',
      schemas: 'src/api/generated/model',
      client: 'axios',
      override: {
        mutator: {
          path: 'src/services/api-mutator.ts',
          name: 'customInstance',
        },
      },
    },
  },
  zod: {
    input: `${apiUrl}/docs/api.json`,
    output: {
      mode: 'tags-split',
      client: 'zod',
      target: 'src/api/generated/zod',
    },
  },
});
