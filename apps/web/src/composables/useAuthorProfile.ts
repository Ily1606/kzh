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
  // profileGen: per profile load or retry
  const profileGen = latestOnly()
  // pluginGen: per username/page load
  const pluginGen = latestOnly()

  async function loadProfile(username: string): Promise<void> {
    // Get tickets for both generations
    const isLatestProfile = profileGen()
    const isLatestUsername = usernameGen()

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

  async function loadPlugins(username: string, page = 1): Promise<void> {
    // Get tickets for both generations
    const isLatestPlugin = pluginGen()
    const isLatestUsername = usernameGen()

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
    // Each load function manages its own generation; retry creates new generations
    await Promise.all([
      loadProfile(username),
      loadPlugins(username, page),
    ])
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
  }
}
