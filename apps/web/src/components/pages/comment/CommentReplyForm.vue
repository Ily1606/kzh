<script setup lang="ts">
import { ref } from 'vue'
import { useRoute } from 'vue-router'
import { useAuth } from '@/composables/useAuth'
import { getComment } from '@/api/generated/endpoints'
import { Button } from '@/components/ui/button'
import { Textarea } from '@/components/ui/textarea'
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
const replyTextarea = ref<any>(null)

function autoResize(e: Event) {
  const el = e.target as HTMLTextAreaElement
  if (!el) return
  el.style.height = 'auto'
  el.style.height = el.scrollHeight + 'px'
}

async function submitReply() {
  const content = replyContent.value.trim().replace(/\n{3,}/g, '\n\n')
  if (!content) return
  isSubmittingReply.value = true
  try {
    const res = await commentStore(props.pluginId, {
      content,
      parent_comment_id: props.commentId
    })
    const body = res as any
    const createdComment = body?.data?.comment || body?.comment || null

    replyContent.value = ''
    const el = (replyTextarea.value as any)?.$el ?? replyTextarea.value
    if (el) el.style.height = 'auto'
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
      <Textarea
        ref="replyTextarea"
        v-model="replyContent"
        rows="2"
        :maxlength="commentConfig.maxLength"
        placeholder="Write a reply..."
        class="min-h-16 max-h-40 resize-none overflow-y-auto"
        @input="autoResize"
        required
      />
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

