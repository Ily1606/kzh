import { computed, type Ref } from 'vue'
import { PluginStatus } from '@/api/generated/model'
import type { PluginResource } from '@/api/generated/model'
import { useAuth } from '@/composables/useAuth'

/**
 * What one plugin's review state allows this visitor to see and do.
 */
export function usePluginDetailAccess(plugin: Ref<PluginResource | null>) {
  const auth = useAuth()

  const isOwner = computed(
    () => Boolean(auth.user) && plugin.value?.user_id === auth.user?.id,
  )

  const isApproved = computed(() => plugin.value?.status === PluginStatus.approved)

  return {
    isOwner,
    isApproved,
    showStatusBadge: computed(() => Boolean(plugin.value) && !isApproved.value),

    /** Approved-only on the API as well, so the UI mirrors that exactly. */
    showComments: isApproved,
    showStar: isApproved,
    showInstallCommand: isApproved,

    /**
     * Whether this visitor may edit this plugin right now.
     */
    canEdit: computed(
      () => isOwner.value && plugin.value?.status === PluginStatus.pending,
    ),

    /**
     * Owner only, and only once the plugin has actually loaded
     */
    showHistoryLink: computed(() => Boolean(plugin.value) && isOwner.value),
  }
}
