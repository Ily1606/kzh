import { describe, it, expect, vi, beforeEach } from 'vitest'
import { ref } from 'vue'
import {
  toRawGithubUrls,
  usePluginReadme,
  resolveRelativeGithubAssetUrl,
} from '@/composables/usePluginReadme'

describe('toRawGithubUrls', () => {
  it('returns an empty array for empty, whitespace, or invalid URLs', () => {
    expect(toRawGithubUrls('')).toEqual([])
    expect(toRawGithubUrls('   ')).toEqual([])
    expect(toRawGithubUrls('not a url')).toEqual([])
    expect(toRawGithubUrls('javascript:alert(1)')).toEqual([])
  })

  it('strictly rejects non-GitHub domains even if they end in .md', () => {
    expect(toRawGithubUrls('https://attacker.com/evil.md')).toEqual([])
    expect(toRawGithubUrls('https://tracking-server.org/logger.md')).toEqual([])
    expect(toRawGithubUrls('https://gitlab.com/owner/repo/raw/main/README.md')).toEqual([])
    expect(toRawGithubUrls('https://bitbucket.org/owner/repo/raw/main/README.md')).toEqual([])
  })

  it('strictly rejects domain spoofing attempts', () => {
    expect(toRawGithubUrls('https://raw.githubusercontent.com.attacker.com/file.md')).toEqual([])
    expect(toRawGithubUrls('https://github.com.attacker.com/owner/repo')).toEqual([])
    expect(toRawGithubUrls('https://attacker.com/raw.githubusercontent.com/file.md')).toEqual([])
    expect(toRawGithubUrls('https://attacker.com/?ref=raw.githubusercontent.com')).toEqual([])
    expect(toRawGithubUrls('https://attacker.com/github.com/owner/repo')).toEqual([])
  })

  it('accepts valid raw.githubusercontent.com URLs', () => {
    const rawUrl = 'https://raw.githubusercontent.com/dsh/my-plugin/main/README.md'
    expect(toRawGithubUrls(rawUrl)).toEqual([rawUrl])
  })

  it('rejects raw.githubusercontent.com URLs with insufficient path segments', () => {
    expect(toRawGithubUrls('https://raw.githubusercontent.com/')).toEqual([])
    expect(toRawGithubUrls('https://raw.githubusercontent.com/owner')).toEqual([])
  })

  it('converts GitHub repo URL to main and master README candidates', () => {
    expect(toRawGithubUrls('https://github.com/dsh/my-plugin')).toEqual([
      'https://raw.githubusercontent.com/dsh/my-plugin/main/README.md',
      'https://raw.githubusercontent.com/dsh/my-plugin/master/README.md',
    ])

    expect(toRawGithubUrls('https://github.com/dsh/my-plugin.git')).toEqual([
      'https://raw.githubusercontent.com/dsh/my-plugin/main/README.md',
      'https://raw.githubusercontent.com/dsh/my-plugin/master/README.md',
    ])

    expect(toRawGithubUrls('https://github.com/dsh/my-plugin/')).toEqual([
      'https://raw.githubusercontent.com/dsh/my-plugin/main/README.md',
      'https://raw.githubusercontent.com/dsh/my-plugin/master/README.md',
    ])
  })

  it('converts GitHub blob URLs to raw.githubusercontent.com URL', () => {
    expect(
      toRawGithubUrls('https://github.com/dsh/my-plugin/blob/v1.0.0/docs/README.md')
    ).toEqual(['https://raw.githubusercontent.com/dsh/my-plugin/v1.0.0/docs/README.md'])
  })

  it('converts GitHub raw URLs to raw.githubusercontent.com URL', () => {
    expect(
      toRawGithubUrls('https://github.com/dsh/my-plugin/raw/main/README.md')
    ).toEqual(['https://raw.githubusercontent.com/dsh/my-plugin/main/README.md'])
  })

  it('converts GitHub tree URLs to raw.githubusercontent.com URL', () => {
    expect(
      toRawGithubUrls('https://github.com/dsh/my-plugin/tree/main')
    ).toEqual(['https://raw.githubusercontent.com/dsh/my-plugin/main/README.md'])

    expect(
      toRawGithubUrls('https://github.com/dsh/my-plugin/tree/main/')
    ).toEqual(['https://raw.githubusercontent.com/dsh/my-plugin/main/README.md'])

    expect(
      toRawGithubUrls('https://github.com/dsh/my-plugin/tree/main/packages/core')
    ).toEqual(['https://raw.githubusercontent.com/dsh/my-plugin/main/packages/core/README.md'])

    expect(
      toRawGithubUrls('https://github.com/dsh/my-plugin/tree/main/packages/core/')
    ).toEqual(['https://raw.githubusercontent.com/dsh/my-plugin/main/packages/core/README.md'])
  })
})

describe('usePluginReadme security', () => {
  beforeEach(() => {
    vi.restoreAllMocks()
  })

  it('does not fetch from untrusted external URLs and sets error', async () => {
    const fetchSpy = vi.spyOn(globalThis, 'fetch')
    const sourceLink = ref('https://malicious-tracking-site.com/logger.md')
    const container = ref(null)

    const { html, error, load } = usePluginReadme(sourceLink, container)
    await load()

    expect(fetchSpy).not.toHaveBeenCalled()
    expect(html.value).toBe('')
    expect(error.value).toBe('No README available for this plugin.')
  })

  it('fetches only from allowed GitHub raw URL when given a GitHub source link', async () => {
    const fetchSpy = vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce({
      ok: true,
      text: async () => '# Hello Plugin\nThis is a readme.',
    } as Response)

    const sourceLink = ref('https://github.com/dsh/test-plugin')
    const container = ref(null)

    const { html, error, load } = usePluginReadme(sourceLink, container)
    await load()

    expect(fetchSpy).toHaveBeenCalledWith(
      'https://raw.githubusercontent.com/dsh/test-plugin/main/README.md'
    )
    expect(error.value).toBe('')
    expect(html.value).toContain('Hello Plugin')
  })

  it('rewrites relative image paths (markdown and HTML) to raw GitHub URLs', async () => {
    const markdownWithImages = `
# Readme with images
![Markdown Banner](assets/readme/banner.png)
![Relative Dot Banner](./assets/readme/dot-banner.png)
<img src="assets/readme/html-banner.png" alt="HTML Banner" />
<img src="/assets/root-banner.png" alt="Root Banner" />
![External](https://example.com/external.png)
`
    vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce({
      ok: true,
      text: async () => markdownWithImages,
    } as Response)

    const sourceLink = ref('https://github.com/dsh/test-plugin')
    const container = ref(null)

    const { html, load } = usePluginReadme(sourceLink, container)
    await load()

    const rawBase = 'https://raw.githubusercontent.com/dsh/test-plugin/main/'

    // Markdown image relative path
    expect(html.value).toContain(`src="${rawBase}assets/readme/banner.png"`)
    // ./ relative path
    expect(html.value).toContain(`src="${rawBase}assets/readme/dot-banner.png"`)
    // HTML <img> tag relative path
    expect(html.value).toContain(`src="${rawBase}assets/readme/html-banner.png"`)
    // Leading slash repo-root relative path
    expect(html.value).toContain(`src="${rawBase}assets/root-banner.png"`)
    // Absolute external image remains unchanged
    expect(html.value).toContain('src="https://example.com/external.png"')
  })

  it('correctly resolves repo-root relative image paths for refs/heads raw URLs', async () => {
    const markdown = '<img src="/docs/a.png" alt="Doc image" />'
    vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce({
      ok: true,
      text: async () => markdown,
    } as Response)

    const sourceLink = ref('https://raw.githubusercontent.com/o/r/refs/heads/main/README.md')
    const container = ref(null)

    const { html, load } = usePluginReadme(sourceLink, container)
    await load()

    expect(html.value).toContain(
      'src="https://raw.githubusercontent.com/o/r/refs/heads/main/docs/a.png"'
    )
  })
})

describe('resolveRelativeGithubAssetUrl', () => {
  it('correctly resolves root-relative paths for refs/heads/ branch URLs', () => {
    expect(
      resolveRelativeGithubAssetUrl(
        '/docs/a.png',
        'https://raw.githubusercontent.com/o/r/refs/heads/main/README.md'
      )
    ).toBe('https://raw.githubusercontent.com/o/r/refs/heads/main/docs/a.png')

    expect(
      resolveRelativeGithubAssetUrl(
        '/docs/a.png',
        'https://raw.githubusercontent.com/o/r/refs/heads/main/'
      )
    ).toBe('https://raw.githubusercontent.com/o/r/refs/heads/main/docs/a.png')
  })

  it('correctly resolves root-relative paths for refs/tags/ URLs', () => {
    expect(
      resolveRelativeGithubAssetUrl(
        '/docs/a.png',
        'https://raw.githubusercontent.com/o/r/refs/tags/v1.0.0/README.md'
      )
    ).toBe('https://raw.githubusercontent.com/o/r/refs/tags/v1.0.0/docs/a.png')
  })

  it('correctly resolves root-relative paths for classic branch URLs', () => {
    expect(
      resolveRelativeGithubAssetUrl(
        '/docs/a.png',
        'https://raw.githubusercontent.com/o/r/main/README.md'
      )
    ).toBe('https://raw.githubusercontent.com/o/r/main/docs/a.png')
  })
})


