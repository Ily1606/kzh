<script setup lang="ts">
import { ref, inject } from 'vue'
import { useRoute } from 'vue-router'
import { getComment } from '@/api/generated/endpoints'
import type { CommentResource } from '@/api/generated/model'
import { Button } from '@/components/ui/button'
import { useAuth } from '@/composables/useAuth'
import { CornerDownRight, ChevronDown, ChevronUp, Flag } from 'lucide-vue-next'

const props = withDefaults(
  defineProps<{
    comment: CommentResource
    pluginId: string
    parentAuthorName?: string
    depth?: number
  }>(),
  {
    depth: 1
  }
)

const auth = useAuth()
const route = useRoute()
const incrementCommentCount = inject<() => void>('incrementCommentCount', () => {})
const { commentReplies, commentStore } = getComment()

const showReplyForm = ref(false)
const replyContent = ref('')
const isSubmittingReply = ref(false)

const showReportModal = ref(false)
const reportContent = ref('')
const isSubmittingReport = ref(false)

const showReplies = ref(false)
const isLoadingReplies = ref(false)
const replies = ref<CommentResource[]>([])

function toggleReplyBox() {
  if (props.depth >= 3) return
  showReplyForm.value = !showReplyForm.value
  replyContent.value = ''
}

async function submitReport() {
  if (!reportContent.value.trim()) return
  isSubmittingReport.value = true
  try {
    // API mock: Chưa có endpoint thật trong orval/OpenAPI
    await new Promise(r => setTimeout(r, 600))
    alert('Your report has been submitted successfully.')
    showReportModal.value = false
    reportContent.value = ''
  } catch (err) {
    alert('An error occurred while submitting the report.')
  } finally {
    isSubmittingReport.value = false
  }
}

async function toggleReplies() {
  if (props.depth >= 3) return
  if (showReplies.value) {
    showReplies.value = false
    return
  }

  showReplies.value = true
  if (replies.value.length === 0) {
    isLoadingReplies.value = true
    try {
      const res = await commentReplies(props.pluginId, props.comment.id)
      const body = res.data as any
      replies.value = body?.data?.comments || body?.comments || (Array.isArray(body?.data) ? body.data : [])
    } catch (err) {
      console.error('Failed to load replies', err)
    } finally {
      isLoadingReplies.value = false
    }
  }
}

async function submitReply() {
  if (props.depth >= 3 || !replyContent.value.trim()) return

  isSubmittingReply.value = true
  try {
    const res = await commentStore(props.pluginId, {
      content: replyContent.value,
      parent_comment_id: props.comment.id
    })
    const body = res.data as any
    const createdComment = body?.data?.comment || body?.comment

    if (createdComment) {
      replies.value.push(createdComment)
    } else {
      const repliesRes = await commentReplies(props.pluginId, props.comment.id)
      const rBody = repliesRes.data as any
      replies.value = rBody?.data?.comments || rBody?.comments || []
    }

    props.comment.replies_count = String((Number(props.comment.replies_count) || 0) + 1)
    showReplies.value = true
    replyContent.value = ''
    showReplyForm.value = false
    incrementCommentCount()
  } catch (err: any) {
    alert(err?.response?.data?.message || 'Error posting reply')
  } finally {
    isSubmittingReply.value = false
  }
}
</script>

<template>
  <div class="p-4 rounded-xl bg-card/70 border border-border/60 shadow-sm space-y-3">
    <!-- Header & Content -->
    <div class="flex gap-3">
      <!-- Avatar -->
      <div class="size-9 rounded-full bg-primary/10 flex items-center justify-center shrink-0 text-primary font-bold overflow-hidden border border-border/50 relative">
        <span class="absolute inset-0 flex items-center justify-center text-sm">
          {{ comment.author?.name ? comment.author.name.charAt(0).toUpperCase() : 'U' }}
        </span>
        <img
          v-if="comment.author?.avatar_url"
          :src="comment.author.avatar_url"
          :alt="comment.author?.name || 'Avatar'"
          class="size-full object-cover relative z-10"
          @error="(e) => (e.target as HTMLElement).style.display = 'none'"
        />
      </div>

      <!-- Main info -->
      <div class="flex-1 space-y-1.5">
        <div class="flex items-center justify-between gap-2 flex-wrap w-full">
          <div class="flex items-center gap-2 flex-wrap">
            <span class="font-semibold text-sm">{{ comment.author?.name || 'Unknown' }}</span>

            <!-- Badge hiển thị người đang được reply -->
            <span
              v-if="parentAuthorName"
              class="inline-flex items-center gap-1 text-xs text-muted-foreground bg-muted/70 px-2 py-0.5 rounded-full border border-border/40 font-medium"
            >
              <CornerDownRight class="size-3 text-primary" />
              <span>replying to <strong class="text-foreground font-semibold">@{{ parentAuthorName }}</strong></span>
            </span>

            <span class="text-xs text-muted-foreground">&bull; {{ comment.created_at ? comment.created_at.split('T')[0] : 'Just now' }}</span>
          </div>

          <button
            @click="showReportModal = true"
            title="Report this comment"
            class="text-muted-foreground hover:text-destructive transition-colors p-1 rounded-md hover:bg-destructive/10"
          >
            <Flag class="size-3.5" />
          </button>
        </div>

        <p class="text-sm text-foreground/90 whitespace-pre-wrap leading-relaxed">{{ comment.content }}</p>

        <!-- Actions: Reply button (chỉ hiển thị khi cấp độ < 3) & View replies toggle -->
        <div class="flex items-center gap-3 pt-2 text-xs">
          <button
            v-if="depth < 3"
            @click="toggleReplyBox"
            class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md font-medium text-foreground/80 hover:text-primary hover:bg-muted transition-colors border border-border/60 bg-background/50 cursor-pointer"
          >
            <CornerDownRight class="size-3.5 text-primary" />
            Reply
          </button>

          <button
            v-if="depth < 3 && comment.replies_count && Number(comment.replies_count) > 0"
            @click="toggleReplies"
            class="font-semibold text-primary hover:underline flex items-center gap-1 cursor-pointer"
          >
            <component :is="showReplies ? ChevronUp : ChevronDown" class="size-3.5" />
            {{ showReplies ? 'Hide' : 'View' }} {{ comment.replies_count }} {{ Number(comment.replies_count) === 1 ? 'reply' : 'replies' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Inline Form viết Reply (chỉ cho phép ở cấp 1 và cấp 2) -->
    <div v-if="showReplyForm && depth < 3" class="mt-2 pl-3 border-l-2 border-primary/40 space-y-2">
      <div v-if="auth.isAuthenticated" class="space-y-2">
        <div class="flex items-center gap-1.5 text-xs text-muted-foreground font-medium pb-0.5">
          <CornerDownRight class="size-3.5 text-primary" />
          <span>Replying to <span class="text-primary font-semibold">@{{ comment.author?.name || 'user' }}</span></span>
        </div>
        <textarea
          v-model="replyContent"
          rows="2"
          placeholder="Write a reply..."
          class="flex w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
          required
        ></textarea>
        <div class="flex justify-end gap-2">
          <Button size="sm" variant="ghost" @click="toggleReplyBox">Cancel</Button>
          <Button size="sm" :disabled="isSubmittingReply || !replyContent.trim()" @click="submitReply">
            {{ isSubmittingReply ? 'Replying...' : 'Send Reply' }}
          </Button>
        </div>
      </div>
      <div v-else class="p-3 bg-muted/40 rounded-lg border text-sm text-muted-foreground flex items-center justify-between">
        <span>Please sign in to reply to this comment.</span>
        <router-link :to="{ name: 'login', query: { redirect: route.path + '#comments' } }">
          <Button size="sm" variant="default">Sign In</Button>
        </router-link>
      </div>
    </div>

    <!-- Recursive Children (chỉ hiển thị ở cấp 1 và cấp 2) -->
    <div
      v-if="showReplies && depth < 3"
      class="space-y-3 pt-2 pl-3 sm:pl-4 border-l-2 border-primary/20 ml-2.5 sm:ml-5"
    >
      <div v-if="isLoadingReplies" class="text-sm text-muted-foreground py-2 italic">
        Loading replies...
      </div>
      <div v-else-if="replies.length === 0" class="text-xs text-muted-foreground py-1 italic">
        No replies yet.
      </div>
      <template v-else>
        <CommentItem
          v-for="childReply in replies"
          :key="childReply.id"
          :comment="childReply"
          :plugin-id="pluginId"
          :parent-author-name="comment.author?.name || 'user'"
          :depth="Math.min(depth + 1, 3)"
        />
      </template>
    </div>

    <!-- Report Modal -->
    <div v-if="showReportModal" class="fixed inset-0 z-100 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
      <div class="bg-card w-full max-w-md p-6 rounded-xl shadow-lg border border-border flex flex-col gap-4">
        <h3 class="text-lg font-semibold text-foreground">Report Comment</h3>
        <p class="text-sm text-muted-foreground -mt-2">
          Please provide a reason for reporting this comment by <strong>{{ comment.author?.name || 'this user' }}</strong> (e.g., adult content, spam, harassment...).
        </p>
        <textarea
          v-model="reportContent"
          rows="4"
          placeholder="Reason for reporting..."
          class="flex w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
        ></textarea>
        <div class="flex justify-end gap-3 mt-2">
          <Button variant="ghost" @click="showReportModal = false">Cancel</Button>
          <Button variant="destructive" :disabled="isSubmittingReport || !reportContent.trim()" @click="submitReport">
            {{ isSubmittingReport ? 'Submitting...' : 'Submit Report' }}
          </Button>
        </div>
      </div>
    </div>
  </div>
</template>

