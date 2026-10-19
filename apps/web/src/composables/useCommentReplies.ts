import { ref } from 'vue'
import { getComment } from '@/api/generated/endpoints'
import type { CommentReplies200, CommentRepliesParams, CommentResource } from '@/api/generated/model'
import { commentConfig } from '@/config/comments'

export function useCommentReplies(pluginId: string, comment: CommentResource) {
  const { commentReplies } = getComment()

  const showReplies = ref(false)
  const isLoadingReplies = ref(false)
  const isFetchingMoreReplies = ref(false)
  const replies = ref<CommentResource[]>([])
  const currentReplyPage = ref(1)
  const lastReplyPage = ref(1)

  async function toggleReplies(depth: number) {
    if (depth >= commentConfig.maxDepth) return
    if (showReplies.value) {
      showReplies.value = false
      return
    }

    showReplies.value = true
    if (replies.value.length === 0) {
      isLoadingReplies.value = true
      try {
        const params: CommentRepliesParams = {
          page: 1,
          per_page: commentConfig.replyPerPage
        }
        const res: CommentReplies200 = await commentReplies(pluginId, comment.id, params)
        replies.value = res.data.comments
        currentReplyPage.value = res.meta?.current_page ?? 1
        lastReplyPage.value = res.meta?.last_page ?? 1
      } catch {
        // Silently handled: empty state will be shown
      } finally {
        isLoadingReplies.value = false
      }
    }
  }

  async function loadMoreReplies() {
    if (currentReplyPage.value >= lastReplyPage.value || isFetchingMoreReplies.value) return

    isFetchingMoreReplies.value = true
    try {
      const nextPage = currentReplyPage.value + 1
      const params: CommentRepliesParams = {
        page: nextPage,
        per_page: commentConfig.replyPerPage
      }
      const res: CommentReplies200 = await commentReplies(pluginId, comment.id, params)
      replies.value = [...replies.value, ...res.data.comments]
      currentReplyPage.value = res.meta?.current_page ?? nextPage
      lastReplyPage.value = res.meta?.last_page ?? lastReplyPage.value
    } catch {
      // Silently handled
    } finally {
      isFetchingMoreReplies.value = false
    }
  }

  async function handleReplyCreated(createdComment?: CommentResource) {
    if (createdComment) {
      replies.value.unshift(createdComment)
    } else {
      const repliesRes: CommentReplies200 = await commentReplies(pluginId, comment.id)
      replies.value = repliesRes.data.comments
    }
    comment.replies_count = String((Number(comment.replies_count) || 0) + 1)
    showReplies.value = true
  }

  return {
    replies,
    showReplies,
    isLoadingReplies,
    isFetchingMoreReplies,
    currentReplyPage,
    lastReplyPage,
    toggleReplies,
    loadMoreReplies,
    handleReplyCreated
  }
}

