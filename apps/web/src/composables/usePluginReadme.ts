import { nextTick, ref, type Ref } from 'vue'
import { marked } from 'marked'
import DOMPurify from 'dompurify'

/**
 * Where the rendered README is cut off and the "read the rest" fade begins.
 * The measurement and the clamp have to agree, so both sides read it from here.
 */
export const README_COLLAPSED_MAX_HEIGHT = 1200

/**
 * Every URL a repository's README might live at, best guess first.
 *
 * Restricts fetches strictly to GitHub (github.com and raw.githubusercontent.com)
 * to prevent third-party IP harvesting and tracking of plugin viewers.
 */
export function toRawGithubUrls(url: string): string[] {
  if (!url) return []
  const cleanUrl = url.trim()

  try {
    const parsed = new URL(cleanUrl)
    if (parsed.protocol !== 'https:' && parsed.protocol !== 'http:') {
      return []
    }

    const hostname = parsed.hostname.toLowerCase()

    // 1. Direct raw.githubusercontent.com URLs
    if (hostname === 'raw.githubusercontent.com') {
      const parts = parsed.pathname.split('/').filter(Boolean)
      if (parts.length >= 3) {
        return [cleanUrl]
      }
      return []
    }

    // 2. github.com (or www.github.com) URLs
    if (hostname === 'github.com' || hostname === 'www.github.com') {
      const blobMatch = parsed.pathname.match(/^\/([^/]+)\/([^/]+)\/blob\/([^/]+)\/(.+)$/)
      if (blobMatch) {
        const [, owner, repo, branch, path] = blobMatch
        return [`https://raw.githubusercontent.com/${owner}/${repo}/${branch}/${path}`]
      }

      const rawMatch = parsed.pathname.match(/^\/([^/]+)\/([^/]+)\/raw\/([^/]+)\/(.+)$/)
      if (rawMatch) {
        const [, owner, repo, branch, path] = rawMatch
        return [`https://raw.githubusercontent.com/${owner}/${repo}/${branch}/${path}`]
      }

      const treeMatch = parsed.pathname.match(/^\/([^/]+)\/([^/]+)\/tree\/([^/]+?)(?:\/(.*))?$/)
      if (treeMatch) {
        const [, owner, repo, branch, path] = treeMatch
        const filePath = path ? (path.endsWith('.md') ? path : `${path}/README.md`) : 'README.md'
        return [`https://raw.githubusercontent.com/${owner}/${repo}/${branch}/${filePath}`]
      }

      const repoMatch = parsed.pathname.match(/^\/([^/]+)\/([^/]+?)(?:\.git|\/)?$/)
      if (repoMatch) {
        const [, owner, repo] = repoMatch
        return [
          `https://raw.githubusercontent.com/${owner}/${repo}/main/README.md`,
          `https://raw.githubusercontent.com/${owner}/${repo}/master/README.md`,
        ]
      }

      return []
    }

    return []
  } catch {
    return []
  }
}

/**
 * Resolves a relative image path to a full raw GitHub URL based on the README's location.
 */
export function resolveRelativeGithubAssetUrl(src: string, baseUrl: string): string {
  if (!src || !baseUrl) return src
  const trimmed = src.trim()

  // Already absolute URL, protocol-relative, or inline data URI
  if (/^(https?:|\/\/|data:)/i.test(trimmed)) {
    return trimmed
  }

  try {
    // If path starts with '/', GitHub resolves relative to the repository branch root
    if (trimmed.startsWith('/')) {
      const match = baseUrl.match(/^(https?:\/\/raw\.githubusercontent\.com\/[^/]+\/[^/]+\/[^/]+)(?:\/|$)/)
      if (match) {
        return `${match[1]}/${trimmed.replace(/^\/+/, '')}`
      }
    }

    // Relative to the directory containing the README
    return new URL(trimmed, baseUrl).href
  } catch {
    return trimmed
  }
}

/**
 * Sanitizes markdown-rendered HTML and rewrites relative image URLs to raw GitHub URLs.
 */
export function sanitizeReadmeHtml(rawHtml: string, baseUrl?: string): string {
  if (baseUrl) {
    DOMPurify.addHook('afterSanitizeAttributes', (node) => {
      if ((node as Element).nodeName?.toUpperCase() === 'IMG' && (node as Element).hasAttribute('src')) {
        const src = (node as Element).getAttribute('src')
        if (src) {
          ;(node as Element).setAttribute('src', resolveRelativeGithubAssetUrl(src, baseUrl))
        }
      }
    })
  }

  try {
    return DOMPurify.sanitize(rawHtml, { ADD_ATTR: ['target'] })
  } finally {
    if (baseUrl) {
      DOMPurify.removeHook('afterSanitizeAttributes')
    }
  }
}

/**
 * Load and render a plugin's README, and remember whether it is long enough to
 * be worth collapsing.
 *
 * This is browser-network work with no relation to the app's own API — GitHub
 * does not authenticate these fetches and no plugin state is involved — so it
 * stays in a composable instead of the store. The page decides when to call
 * `load()`; this only knows how to get the markdown onto the screen.
 */
export function usePluginReadme(sourceLink: Ref<string>, container: Ref<HTMLElement | null>) {
  const html = ref('')
  const isLoading = ref(false)
  const error = ref('')
  const isExpanded = ref(false)
  const isLong = ref(false)

  async function load(): Promise<void> {
    isExpanded.value = false
    isLong.value = false
    html.value = ''

    // A plugin with no source link has nothing to fetch. Left blank on purpose:
    // the caller renders the same empty state it shows for a failed fetch.
    if (!sourceLink.value) return

    isLoading.value = true
    error.value = ''

    let markdownText = ''
    let resolvedBaseUrl = ''

    for (const candidate of toRawGithubUrls(sourceLink.value)) {
      try {
        const res = await fetch(candidate)
        if (res.ok) {
          markdownText = await res.text()
          resolvedBaseUrl = candidate.substring(0, candidate.lastIndexOf('/') + 1)
          break
        }
      } catch {
        // thử link kế tiếp nếu có
      }
    }

    if (markdownText) {
      try {
        const parsed = await marked.parse(markdownText)
        html.value = sanitizeReadmeHtml(parsed, resolvedBaseUrl)
      } catch {
        error.value = 'Failed to parse README markdown.'
      }
    } else {
      error.value = 'No README available for this plugin.'
    }

    isLoading.value = false

    await nextTick()
    if (container.value && container.value.scrollHeight > README_COLLAPSED_MAX_HEIGHT) {
      isLong.value = true
    }
  }

  function expand(): void {
    isExpanded.value = true
  }

  function collapse(): void {
    isExpanded.value = false
  }

  return {
    html,
    isLoading,
    error,
    isExpanded,
    isLong,
    load,
    expand,
    collapse,
  }
}
