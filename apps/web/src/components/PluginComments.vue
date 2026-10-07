<script setup lang="ts">
import { ref, onMounted, computed, provide } from 'vue'
import { getComment } from '@/api/generated/endpoints'
import type { CommentResource } from '@/api/generated/model'
import { Button } from '@/components/ui/button'
import { useAuth } from '@/composables/useAuth'
import { MessageSquare, Loader2, ChevronDown } from 'lucide-vue-next'
import CommentItem from '@/components/CommentItem.vue'
import { commentConfig } from '@/config/comments'

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
const commentTextarea = ref<HTMLTextAreaElement | null>(null)

function autoResize(e: Event) {
  const el = e.target as HTMLTextAreaElement
  el.style.height = 'auto'
  el.style.height = el.scrollHeight + 'px'
}

const displayCommentCount = computed(() => {
  if (typeof props.totalCommentCount === 'number') {
    return props.totalCommentCount
  }
  return comments.value.length
})

const currentPage = ref(1)
const lastPage = ref(1)
const isFetchingMore = ref(false)

async function fetchComments(page = 1, append = false) {
  if (!append) {
    isLoading.value = true
  } else {
    isFetchingMore.value = true
  }
  error.value = ''
  
  try {
    const res = await commentIndex(props.pluginId, { 
      page,
      per_page: commentConfig.perPage 
    } as any)
    const body = res as any
    const newComments = body?.data?.comments || body?.comments || (Array.isArray(body?.data) ? body.data : [])
    
    if (append) {
      comments.value = [...comments.value, ...newComments]
    } else {
      comments.value = newComments
    }
    
    currentPage.value = body?.meta?.current_page || page
    lastPage.value = body?.meta?.last_page || 1
  } catch (err) {
    error.value = 'Failed to load comments.'
  } finally {
    isLoading.value = false
    isFetchingMore.value = false
  }
}

function loadMore() {
  if (currentPage.value < lastPage.value) {
    fetchComments(currentPage.value + 1, true)
  }
}

async function submitComment() {
  if (!newComment.value.trim()) return

  isSubmitting.value = true
  try {
    const res = await commentStore(props.pluginId, { content: newComment.value })
    const body = res as any
    const createdComment = body?.data?.comment || body?.comment
    
    newComment.value = ''
    if (commentTextarea.value) {
      commentTextarea.value.style.height = 'auto'
    }
    emit('commentAdded')
    
    if (createdComment) {
      comments.value.unshift(createdComment)
    } else {
      await fetchComments(1, false)
    }
  } catch (err: any) {
    alert(err?.response?.data?.message || 'Error posting comment')
  } finally {
    isSubmitting.value = false
  }
}

onMounted(() => {
  fetchComments(1, false)
})
</script>

<template>
  <div id="comments" class="mt-12 pt-8 border-t">
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
          ref="commentTextarea"
          v-model="newComment"
          rows="3"
          :maxlength="commentConfig.maxLength"
          placeholder="Leave a comment..."
          class="flex w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50 resize-none overflow-hidden"
          @input="autoResize"
          required
        ></textarea>
        <div class="flex items-center justify-between mt-2">
          <span class="text-xs text-muted-foreground">
            {{ newComment.length }} / {{ commentConfig.maxLength }}
          </span>
          <Button type="submit" :disabled="isSubmitting || !newComment.trim()">
            {{ isSubmitting ? 'Posting...' : 'Post Comment' }}
          </Button>
        </div>
      </form>
    </div>
    <div v-else class="mb-8 p-4 bg-muted/30 rounded-md border text-center text-sm text-muted-foreground">
      Please <router-link :to="{ name: 'login', query: { redirect: $route.path + '#comments' } }" class="text-primary hover:underline font-medium">sign in</router-link> to leave a comment.
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
      
      <!-- Nút tải thêm bình luận gốc -->
      <!-- Nút tải thêm bình luận gốc -->
      <div v-if="currentPage < lastPage" class="relative flex items-center justify-center pt-8 pb-4">
        <div class="absolute inset-x-0 top-1/2 flex items-center pt-4" aria-hidden="true">
          <div class="w-full border-t border-border/50"></div>
        </div>
        <Button 
          variant="outline" 
          size="sm" 
          @click="loadMore" 
          :disabled="isFetchingMore"
          class="relative bg-background rounded-full px-6 shadow-sm border-border/60 hover:bg-muted/50 text-muted-foreground hover:text-foreground transition-all duration-200"
        >
          <Loader2 v-if="isFetchingMore" class="mr-2 size-4 animate-spin" />
          <ChevronDown v-else class="mr-2 size-4" />
          {{ isFetchingMore ? 'Loading older comments...' : 'Show more comments' }}
        </Button>
      </div>
    </div>
  </div>
</template>
