/**
 * Stubs for the two taxonomy endpoints, which the forms and the category filter
 * call on mount.
 *
 * They belong in each spec's own `@/api/generated/endpoints` mock: a spec that
 * mocks that module replaces it wholesale, so a mock in the test setup would be
 * overridden exactly where it is needed.
 */
export const TAXONOMY_CATEGORIES = [
  'UI & workspace',
  'Vision',
  'Multi-agent',
  'Developer tools',
  'Integrations',
  'Security & diagnostics',
]

export const TAXONOMY_TAGS = [
  'Manifest checked',
  'May read files',
  'May write files',
  'May run commands',
  'May access network',
  'Version not locked',
  'Needs credentials',
]

export const getCategory = () => ({
  categoryIndex: async () => ({
    data: TAXONOMY_CATEGORIES.map((name, index) => ({ id: index + 1, name })),
  }),
})

export const getTag = () => ({
  tagIndex: async () => ({
    data: TAXONOMY_TAGS.map((name, index) => ({ id: index + 1, name })),
  }),
})