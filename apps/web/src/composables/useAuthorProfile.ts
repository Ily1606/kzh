import { ref, computed } from 'vue'
import { getAuthor } from '@/api/generated/endpoints'
import type { AuthorResource, PluginResource } from '@/api/generated/model'
import { pluginConfig } from '@/config/plugins'
import { getApiErrorMessage } from '@/utils/error'
import { latestOnly } from '@/stores/plugins/helpers'

function statusOf(error: unknown): number | undefined {
  return (error as { response?: { status?: number } })?.response?.status
}

/**
 * One author's public profile and the plugins they have published.
 */
export function useAuthorProfile() {
  const { authorShow, authorPlugins } = getAuthor()

  const author = ref<AuthorResource | null>(null)
  const plugins = ref<PluginResource[]>([])
  const isLoadingProfile = ref(false)
  const isLoadingPlugins = ref(false)
  const currentPage = ref(1)
  const lastPage = ref(1)
  const total = ref(0)

  // Separate error/notFound state for profile and plugins to avoid cross-contamination
  const profileError = ref('')
  const profileNotFound = ref(false)
  const pluginsError = ref('')
  const pluginsNotFound = ref(false)

  // Computed for template backward compatibility
  const error = computed(() => profileError.value || pluginsError.value)
  const notFound = computed(() => profileNotFound.value || pluginsNotFound.value)

  // Generation counters for race condition handling
  // usernameGen: monotonic, shared by both loaders (increments only when username changes)
  const usernameGen = latestOnly()
  // Current username ticket (updated by onUsernameChange, read by loaders)
  const currentUsernameTicket = ref<(() => boolean) | null>(null)
  // profileGen: per profile load or retry
  const profileGen = latestOnly()
  // pluginGen: per username/page load
  const pluginGen = latestOnly()

  async function loadProfile(username: string, usernameTicket?: () => boolean): Promise<void> {
    // Get tickets for both generations
    const isLatestProfile = profileGen()
    const isLatestUsername = usernameTicket ?? usernameGen()

    isLoadingProfile.value = true
    profileError.value = ''
    profileNotFound.value = false

    try {
      const res = await authorShow(username)

      // Guard: both profile generation and username generation must still be latest
      if (!isLatestProfile() || !isLatestUsername()) return

      author.value = res.data
    } catch (err) {
      // Guard: both profile generation and username generation must still be latest
      if (!isLatestProfile() || !isLatestUsername()) return

      author.value = null
      profileNotFound.value = statusOf(err) === 404

      if (!profileNotFound.value) {
        profileError.value = getApiErrorMessage(err, 'Failed to load the profile.')
      }
    } finally {
      // Only reset loading if this is still the latest profile request
      if (isLatestProfile()) {
        isLoadingProfile.value = false
      }
    }
  }

  async function loadPlugins(username: string, page = 1, usernameTicket?: () => boolean): Promise<void> {
    // Get tickets for both generations
    const isLatestPlugin = pluginGen()
    // Use provided ticket, or current username ticket, or create new one (for backward compat)
    const isLatestUsername = usernameTicket ?? currentUsernameTicket.value ?? usernameGen()

    isLoadingPlugins.value = true
    pluginsError.value = ''
    pluginsNotFound.value = false

    try {
      const res = await authorPlugins(username, {
        page,
        per_page: pluginConfig.numberPluginPerPage,
      })

      // Guard: both plugin generation and username generation must still be latest
      if (!isLatestPlugin() || !isLatestUsername()) return

      plugins.value = res.data
      currentPage.value = res.meta.current_page
      lastPage.value = res.meta.last_page
      total.value = res.meta.total
    } catch (err) {
      // Guard: both plugin generation and username generation must still be latest
      if (!isLatestPlugin() || !isLatestUsername()) return

      plugins.value = []
      pluginsNotFound.value = statusOf(err) === 404

      if (!pluginsNotFound.value) {
        pluginsError.value = getApiErrorMessage(err, 'Failed to load the published plugins.')
      }
    } finally {
      // Only reset loading if this is still the latest plugin request
      if (isLatestPlugin()) {
        isLoadingPlugins.value = false
      }
    }
  }

  async function retry(username: string, page: number): Promise<void> {
    // Pass current username ticket to both loaders so they share the same generation.
    // Only onUsernameChange should advance usernameGen.
    const ticket = currentUsernameTicket.value ?? undefined
    await Promise.all([
      loadProfile(username, ticket),
      loadPlugins(username, page, ticket),
    ])
  }

  function onUsernameChange(): () => boolean {
    author.value = null
    plugins.value = []
    // Increment username generation so any in-flight requests for the old username become stale.
    // Store and return the ticket checker for the new generation.
    const ticket = usernameGen()
    currentUsernameTicket.value = ticket
    return ticket
  }

  return {
    author,
    plugins,
    isLoadingProfile,
    isLoadingPlugins,
    notFound,
    error,
    currentPage,
    lastPage,
    total,
    loadProfile,
    loadPlugins,
    retry,
    onUsernameChange,
  }
}
