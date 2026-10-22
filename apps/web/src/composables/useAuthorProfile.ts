import { ref } from 'vue'
import { getAuthor } from '@/api/generated/endpoints'
import type { AuthorResource, PluginResource } from '@/api/generated/model'
import { pluginConfig } from '@/config/plugins'
import { getApiErrorMessage } from '@/utils/error'

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
  const notFound = ref(false)
  const error = ref('')
  const currentPage = ref(1)
  const lastPage = ref(1)
  const total = ref(0)

  async function loadProfile(username: string): Promise<void> {
    isLoadingProfile.value = true
    error.value = ''

    try {
      const res = await authorShow(username)

      author.value = res.data
    } catch (err) {
      author.value = null
      notFound.value = statusOf(err) === 404

      if (!notFound.value) {
        error.value = getApiErrorMessage(err, 'Failed to load the profile.')
      }
    } finally {
      isLoadingProfile.value = false
    }
  }

  async function loadPlugins(username: string, page = 1): Promise<void> {
    isLoadingPlugins.value = true
    error.value = ''

    try {
      const res = await authorPlugins(username, {
        page,
        per_page: pluginConfig.numberPluginPerPage,
      })

      plugins.value = res.data
      currentPage.value = res.meta.current_page
      lastPage.value = res.meta.last_page
      total.value = res.meta.total
    } catch (err) {
      plugins.value = []
      notFound.value = statusOf(err) === 404

      if (!notFound.value) {
        error.value = getApiErrorMessage(err, 'Failed to load the published plugins.')
      }
    } finally {
      isLoadingPlugins.value = false
    }
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
  }
}
