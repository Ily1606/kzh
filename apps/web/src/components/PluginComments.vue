<script setup lang="ts">
import { ref, onMounted, computed, provide } from 'vue'
import { getComment } from '@/api/generated/endpoints'
import type { CommentResource } from '@/api/generated/model'
import { Button } from '@/components/ui/button'
import { useAuth } from '@/composables/useAuth'
import { MessageSquare } from 'lucide-vue-next'
import CommentItem from '@/components/CommentItem.vue'

const props = defineProps<{
  pluginId: string
  totalCommentCount?: number
}>()

const emit = defineEmits<{
  (e: 'commentAdded'): void
}>()

provide('incrementCommentCount', () => {
  emit('commentAdded')
})

const auth = useAuth()
const { commentIndex, commentStore } = getComment()

const comments = ref<CommentResource[]>([])
const isLoading = ref(true)
const isSubmitting = ref(false)
const newComment = ref('')
const error = ref('')

const displayCommentCount = computed(() => {
  if (typeof props.totalCommentCount === 'number') {
    return props.totalCommentCount
  }
  return comments.value.length
})

async function fetchComments() {
  isLoading.value = true
  error.value = ''
  try {
    const res = await commentIndex(props.pluginId)
    const body = res.data as any
    comments.value = body?.data?.comments || body?.comments || (Array.isArray(body?.data) ? body.data : [])
  } catch (err) {
    error.value = 'Failed to load comments.'
  } finally {
    isLoading.value = false
  }
}

async function submitComment() {
  if (!newComment.value.trim()) return

  isSubmitting.value = true
  try {
    await commentStore(props.pluginId, { content: newComment.value })
    newComment.value = ''
    emit('commentAdded')
    await fetchComments()
  } catch (err: any) {
    alert(err?.response?.data?.message || 'Error posting comment')
  } finally {
    isSubmitting.value = false
  }
}

onMounted(() => {
  fetchComments()
})
</script>

<template>
  <div class="mt-12 pt-8 border-t">
    <div class="flex items-center justify-between mb-6">
      <div class="flex items-center gap-3">
        <h3 class="text-2xl font-bold flex items-center gap-2">
          <MessageSquare class="size-6 text-primary" />
          Comments
        </h3>
        <span class="px-2.5 py-0.5 rounded-full bg-primary/10 text-primary text-sm font-semibold border border-primary/20">
          {{ displayCommentCount }}
        </span>
      </div>
      <span class="text-sm text-muted-foreground">{{ displayCommentCount }} discussion{{ displayCommentCount === 1 ? '' : 's' }}</span>
    </div>

    <!-- Form viết bình luận gốc -->
    <div v-if="auth.isAuthenticated" class="mb-8">
      <form @submit.prevent="submitComment" class="space-y-3">
        <textarea
          v-model="newComment"
          rows="3"
          placeholder="Leave a comment..."
          class="flex w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
          required
        ></textarea>
        <div class="flex justify-end">
          <Button type="submit" :disabled="isSubmitting || !newComment.trim()">
            {{ isSubmitting ? 'Posting...' : 'Post Comment' }}
          </Button>
        </div>
      </form>
    </div>
    <div v-else class="mb-8 p-4 bg-muted/30 rounded-md border text-center text-sm text-muted-foreground">
      Please <router-link :to="{ name: 'login' }" class="text-primary hover:underline font-medium">sign in</router-link> to leave a comment.
    </div>

    <!-- Danh sách bình luận -->
    <div v-if="isLoading" class="text-center py-6 text-muted-foreground">
      Loading comments...
    </div>
    <div v-else-if="error" class="text-center py-6 text-destructive">
      {{ error }}
    </div>
    <div v-else-if="comments.length === 0" class="text-center py-8 text-muted-foreground italic">
      No comments yet. Be the first to share your thoughts!
    </div>
    <div v-else class="space-y-6">
      <!-- Mỗi bình luận gốc được quản lý đệ quy qua CommentItem (Giới hạn tối đa 3 cấp) -->
      <CommentItem
        v-for="comment in comments"
        :key="comment.id"
        :comment="comment"
        :plugin-id="pluginId"
        :depth="1"
      />
    </div>
  </div>
</template>
