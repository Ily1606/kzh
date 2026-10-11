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
 * A publisher pastes one of three things: a raw URL, a GitHub blob URL, or
 * just the repo. All three are common, so each is turned into the candidate(s)
 * it implies rather than guessing from the shape.
 */
export function toRawGithubUrls(url: string): string[] {
  if (!url) return []
  const cleanUrl = url.trim()

  if (cleanUrl.includes('raw.githubusercontent.com') || cleanUrl.endsWith('.md')) {
    return [cleanUrl]
  }

  const blobMatch = cleanUrl.match(/^https?:\/\/github\.com\/([^/]+)\/([^/]+)\/blob\/([^/]+)\/(.+)$/)
  if (blobMatch) {
    const [, owner, repo, branch, path] = blobMatch
    return [`https://raw.githubusercontent.com/${owner}/${repo}/${branch}/${path}`]
  }

  const repoMatch = cleanUrl.match(/^https?:\/\/github\.com\/([^/]+)\/([^/]+?)(?:\.git|\/)?$/)
  if (repoMatch) {
    const [, owner, repo] = repoMatch
    return [
      `https://raw.githubusercontent.com/${owner}/${repo}/main/README.md`,
      `https://raw.githubusercontent.com/${owner}/${repo}/master/README.md`
    ]
  }

  return [cleanUrl]
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
export function usePluginReadme(sourceLink: Ref<string>) {
  const html = ref('')
  const isLoading = ref(false)
  const error = ref('')
  const isExpanded = ref(false)
  const isLong = ref(false)
  const container = ref<HTMLElement | null>(null)

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

    for (const candidate of toRawGithubUrls(sourceLink.value)) {
      try {
        const res = await fetch(candidate)
        if (res.ok) {
          markdownText = await res.text()
          break
        }
      } catch {
        // thử link kế tiếp nếu có
      }
    }

    if (markdownText) {
      try {
        const parsed = await marked.parse(markdownText)
        html.value = DOMPurify.sanitize(parsed, { ADD_ATTR: ['target'] })
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
    container,
    load,
    expand,
    collapse,
  }
}