import { ref } from 'vue'
import { getComment } from '@/api/generated/endpoints'
import { commentConfig } from '@/config/comments'
import type { CommentResource } from '@/api/generated/model'

export function useCommentReplies(pluginId: string, commentId: string) {
  const { commentReplies } = getComment()

  const showReplies = ref(false)
  const replies = ref<CommentResource[]>([])
  const isLoadingReplies = ref(false)
  const isFetchingMoreReplies = ref(false)
  const hasFetchedReplies = ref(false)
  const currentReplyPage = ref(1)
  const lastReplyPage = ref(1)

  let inFlightRepliesPromise: Promise<void> | null = null

  async function fetchReplies(page = 1, append = false) {
    if (!append && inFlightRepliesPromise) {
      return inFlightRepliesPromise
    }

    const promise = (async () => {
      if (!append) {
        isLoadingReplies.value = true
      } else {
        isFetchingMoreReplies.value = true
      }
      try {
        const res = await commentReplies(pluginId, commentId, {
          page,
          per_page: commentConfig.replyPerPage
        } as any)
        const body = res as any
        const newReplies = body?.data?.comments || body?.comments || (Array.isArray(body?.data) ? body.data : [])

        if (append) {
          const existingIds = new Set(replies.value.map(r => r.id))
          const uniqueNew = newReplies.filter((r: any) => !existingIds.has(r.id))
          replies.value = [...replies.value, ...uniqueNew]
        } else {
          // If local comments (e.g. newly unshifted) exist, keep them instead of overwriting
          if (replies.value.length > 0) {
            const newIds = new Set(newReplies.map((r: any) => r.id))
            const localOnly = replies.value.filter(r => !newIds.has(r.id))
            replies.value = [...localOnly, ...newReplies]
          } else {
            replies.value = newReplies
          }
        }
        currentReplyPage.value = body?.meta?.current_page || page
        lastReplyPage.value = body?.meta?.last_page || 1
        hasFetchedReplies.value = true
      } catch (err) {
        console.error('Failed to load replies', err)
      } finally {
        isLoadingReplies.value = false
        isFetchingMoreReplies.value = false
        if (!append) {
          inFlightRepliesPromise = null
        }
      }
    })()

    if (!append) {
      inFlightRepliesPromise = promise
    }

    return promise
  }

  async function toggleReplies(depth = 1) {
    if (depth >= 3) return
    if (showReplies.value) {
      showReplies.value = false
      return
    }
    showReplies.value = true
    if (!hasFetchedReplies.value) {
      await fetchReplies(1, false)
    }
  }

  function loadMoreReplies() {
    if (currentReplyPage.value < lastReplyPage.value) {
      fetchReplies(currentReplyPage.value + 1, true)
    }
  }

  return {
    showReplies,
    replies,
    isLoadingReplies,
    isFetchingMoreReplies,
    hasFetchedReplies,
    currentReplyPage,
    lastReplyPage,
    fetchReplies,
    toggleReplies,
    loadMoreReplies
  }
}

