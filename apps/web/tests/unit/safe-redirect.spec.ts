import { describe, it, expect } from 'vitest'
import { safeRedirect } from '@/utils/safe-redirect'

describe('safeRedirect', () => {
  it('returns valid internal paths', () => {
    expect(safeRedirect('/plugins')).toBe('/plugins')
    expect(safeRedirect('/plugins/1?sort=latest')).toBe('/plugins/1?sort=latest')
    expect(safeRedirect('/plugins/1#comments')).toBe('/plugins/1#comments')
  })

  it('falls back to "/" for non-string or empty inputs', () => {
    expect(safeRedirect(null)).toBe('/')
    expect(safeRedirect(undefined)).toBe('/')
    expect(safeRedirect('')).toBe('/')
    expect(safeRedirect(['/plugins'])).toBe('/')
    expect(safeRedirect(123)).toBe('/')
  })

  it('rejects external URLs and protocol-relative URLs', () => {
    expect(safeRedirect('https://evil.com')).toBe('/')
    expect(safeRedirect('http://evil.com')).toBe('/')
    expect(safeRedirect('//evil.com')).toBe('/')
    expect(safeRedirect('///evil.com')).toBe('/')
    expect(safeRedirect('/\\evil.com')).toBe('/')
    expect(safeRedirect('\\evil.com')).toBe('/')
  })

  it('rejects loop redirects back to auth pages', () => {
    expect(safeRedirect('/login')).toBe('/')
    expect(safeRedirect('/login?redirect=/plugins')).toBe('/')
    expect(safeRedirect('/register')).toBe('/')
    expect(safeRedirect('/forgot-password')).toBe('/')
    expect(safeRedirect('/reset-password')).toBe('/')
  })

  it('uses custom fallback if provided', () => {
    expect(safeRedirect('https://evil.com', '/home')).toBe('/home')
    expect(safeRedirect(null, '/plugins')).toBe('/plugins')
  })
})

