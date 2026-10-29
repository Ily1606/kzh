import { describe, it, expect } from 'vitest'
import { createPluginFieldsSchema } from '@/schemas/plugin.schema'

describe('createPluginFieldsSchema regex validation', () => {
  const schema = createPluginFieldsSchema(['Integrations'], ['Tag1'])

  const baseValidData = {
    name: 'valid-plugin_name.123',
    title: 'Valid Title',
    license: 'MIT',
    category: 'Integrations',
    tags: [],
    source_link: 'https://github.com/example/repo',
  }

  it('accepts valid plugin names with alphanumeric, dots, underscores, and hyphens', () => {
    const validNames = [
      'my-plugin',
      'plugin_name',
      'plugin.name',
      'Plugin123',
      'a.b-c_d',
    ]

    for (const name of validNames) {
      const result = schema.safeParse({ ...baseValidData, name })
      expect(result.success).toBe(true)
    }
  })

  it('rejects invalid plugin names (such as ".", "..", spaces, special characters)', () => {
    const invalidNames = [
      '.',
      '..',
      'my plugin',
      'plugin@name',
      'plugin/name',
      'plugin#name',
      'plugin$name',
      'plugin!name',
      '',
    ]

    for (const name of invalidNames) {
      const result = schema.safeParse({ ...baseValidData, name })
      expect(result.success).toBe(false)
    }
  })
})

