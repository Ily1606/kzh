<script setup lang="ts">
import { ref } from 'vue'
import { useRoute } from 'vue-router'
import { useAuth } from '@/composables/useAuth'
import { getComment } from '@/api/generated/endpoints'
import { Button } from '@/components/ui/button'
import { commentConfig } from '@/config/comments'
import type { CommentResource } from '@/api/generated/model'

const props = defineProps<{
  pluginId: string
  commentId: string
}>()

const emit = defineEmits<{
  (e: 'cancel'): void
  (e: 'submitted', comment: CommentResource | null): void
}>()

const auth = useAuth()
const route = useRoute()
const { commentStore } = getComment()

const replyContent = ref('')
const isSubmittingReply = ref(false)
const replyTextarea = ref<HTMLTextAreaElement | null>(null)

function autoResize(e: Event) {
  const el = e.target as HTMLTextAreaElement
  el.style.height = 'auto'
  el.style.height = el.scrollHeight + 'px'
}

async function submitReply() {
  if (!replyContent.value.trim()) return
  isSubmittingReply.value = true
  try {
    const res = await commentStore(props.pluginId, {
      content: replyContent.value,
      parent_comment_id: props.commentId
    })
    const body = res as any
    const createdComment = body?.data?.comment || body?.comment || null

    replyContent.value = ''
    if (replyTextarea.value) replyTextarea.value.style.height = 'auto'
    emit('submitted', createdComment)
  } catch (err: any) {
    alert(err?.response?.data?.message || 'Error posting reply')
  } finally {
    isSubmittingReply.value = false
  }
}
</script>

<template>
  <div class="mt-3">
    <div v-if="auth.isAuthenticated" class="space-y-2">
      <textarea
        ref="replyTextarea"
        v-model="replyContent"
        rows="2"
        :maxlength="commentConfig.maxLength"
        placeholder="Write a reply..."
        class="flex w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring resize-none overflow-hidden"
        @input="autoResize"
        required
      ></textarea>
      <div class="flex items-center justify-between gap-2">
        <span class="text-xs text-muted-foreground pl-1">{{ replyContent.length }} / {{ commentConfig.maxLength }}</span>
        <div class="flex gap-2">
          <Button size="sm" variant="ghost" @click="emit('cancel')">Cancel</Button>
          <Button size="sm" :disabled="isSubmittingReply || !replyContent.trim()" @click="submitReply">
            {{ isSubmittingReply ? 'Replying...' : 'Reply' }}
          </Button>
        </div>
      </div>
    </div>
    <div v-else class="p-3 bg-muted/40 rounded-lg border text-sm text-muted-foreground flex items-center justify-between">
      <span>Please sign in to reply.</span>
      <router-link :to="{ name: 'login', query: { redirect: route.path + '#comments' } }">
        <Button size="sm" variant="default">Sign In</Button>
      </router-link>
    </div>
  </div>
</template>

