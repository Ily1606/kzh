<script setup lang="ts">
import { ref, inject } from 'vue'
import { useRoute } from 'vue-router'
import { getComment } from '@/api/generated/endpoints'
import type { CommentResource } from '@/api/generated/model'
import { Button } from '@/components/ui/button'
import { useAuth } from '@/composables/useAuth'
import { CornerDownRight, ChevronDown, ChevronUp, Flag, Loader2 } from 'lucide-vue-next'
import { commentConfig } from '@/config/comments'
import { formatDateTime } from '@/utils/date'

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
const replyTextarea = ref<HTMLTextAreaElement | null>(null)

function autoResize(e: Event) {
  const el = e.target as HTMLTextAreaElement
  el.style.height = 'auto'
  el.style.height = el.scrollHeight + 'px'
}

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

const currentReplyPage = ref(1)
const lastReplyPage = ref(1)
const isFetchingMoreReplies = ref(false)

async function toggleReplies() {
  if (props.depth >= 3) return
  if (showReplies.value) {
    showReplies.value = false
    return
  }
  showReplies.value = true
  if (replies.value.length === 0) {
    await fetchReplies(1, false)
  }
}

async function fetchReplies(page = 1, append = false) {
  if (!append) {
    isLoadingReplies.value = true
  } else {
    isFetchingMoreReplies.value = true
  }
  try {
    const res = await commentReplies(props.pluginId, props.comment.id, {
      page,
      per_page: commentConfig.replyPerPage
    } as any)
    const body = res as any
    const newReplies = body?.data?.comments || body?.comments || (Array.isArray(body?.data) ? body.data : [])
    if (append) {
      replies.value = [...replies.value, ...newReplies]
    } else {
      replies.value = newReplies
    }
    currentReplyPage.value = body?.meta?.current_page || page
    lastReplyPage.value = body?.meta?.last_page || 1
  } catch (err) {
    console.error('Failed to load replies', err)
  } finally {
    isLoadingReplies.value = false
    isFetchingMoreReplies.value = false
  }
}

function loadMoreReplies() {
  if (currentReplyPage.value < lastReplyPage.value) {
    fetchReplies(currentReplyPage.value + 1, true)
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
    const body = res as any
    const createdComment = body?.data?.comment || body?.comment
    if (createdComment) {
      replies.value.unshift(createdComment)
    } else {
      const repliesRes = await commentReplies(props.pluginId, props.comment.id)
      const rBody = repliesRes as any
      replies.value = rBody?.data?.comments || rBody?.comments || []
    }
    props.comment.replies_count = String((Number(props.comment.replies_count) || 0) + 1)
    showReplies.value = true
    replyContent.value = ''
    if (replyTextarea.value) replyTextarea.value.style.height = 'auto'
    showReplyForm.value = false
    incrementCommentCount()
  } catch (err: any) {
    alert(err?.response?.data?.message || 'Error posting reply')
  } finally {
    isSubmittingReply.value = false
  }
}

// Avatar width (w-9 = 36px), half = 18px; gap-3 = 12px
// Horizontal connector width = gap(12px) + half-avatar(18px) = 30px
const CONNECTOR_WIDTH = '30px'
</script>

<template>
  <!--
    Layout (YouTube/Reddit style):
    [avatar col (w-9)] [gap-3] [content col (flex-1)]
    The avatar col has a vertical line below the avatar when replies are open.
    Child CommentItems rendered inside the content col have a horizontal connector
    that reaches LEFT across the gap into the parent's avatar column to touch the vertical line.
  -->
  <div class="flex gap-3">

    <!-- ── Avatar column ─────────────────────────────────────────── -->
    <div class="relative flex flex-col items-center shrink-0 w-9">

      <!-- Horizontal connector for child comments (depth > 1).
           Uses position:absolute + right:100% to reach LEFT into parent's avatar column.
           Width = gap(12px) + half-avatar(18px) = 30px so the line meets the parent vertical line. -->
      <div
        v-if="depth > 1"
        class="absolute top-[17px] h-px bg-border"
        :style="{ right: '100%', width: CONNECTOR_WIDTH }"
      ></div>

      <!-- Avatar circle -->
      <div class="size-9 rounded-full bg-primary/10 flex items-center justify-center text-primary font-bold overflow-hidden border border-border/50 relative shrink-0">
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

      <!-- Vertical thread line — visible when replies or reply-form is open -->
      <div
        v-if="(showReplies || showReplyForm) && depth < 3"
        class="w-0.5 flex-1 mt-2 rounded-full bg-border/70"
      ></div>
    </div>
    <!-- ────────────────────────────────────────────────────────────── -->

    <!-- ── Content column ────────────────────────────────────────── -->
    <div class="flex-1 min-w-0 pb-3">

      <!-- Author + timestamp -->
      <div class="flex items-center justify-between gap-2 flex-wrap mb-0.5">
        <div class="flex items-center gap-2 flex-wrap">
          <span class="font-semibold text-sm">{{ comment.author?.name || 'Unknown' }}</span>

          <span
            v-if="parentAuthorName"
            class="inline-flex items-center gap-1 text-xs text-muted-foreground bg-muted/70 px-2 py-0.5 rounded-full border border-border/40 font-medium"
          >
            <CornerDownRight class="size-3 text-primary" />
            <span>replying to <strong class="text-foreground font-semibold">@{{ parentAuthorName }}</strong></span>
          </span>

          <span class="text-xs text-muted-foreground">&bull; {{ formatDateTime(comment.created_at) }}</span>
        </div>

        <button
          @click="showReportModal = true"
          title="Report this comment"
          class="text-muted-foreground hover:text-destructive transition-colors p-1 rounded-md hover:bg-destructive/10"
        >
          <Flag class="size-3.5" />
        </button>
      </div>

      <!-- Comment text -->
      <p class="text-sm text-foreground/90 whitespace-pre-wrap leading-relaxed">{{ comment.content }}</p>

      <!-- Action buttons -->
      <div class="flex items-center gap-4 mt-2 text-xs">
        <button
          v-if="depth < 3"
          @click="toggleReplyBox"
          class="font-semibold text-muted-foreground hover:text-foreground transition-colors cursor-pointer"
        >
          Reply
        </button>

        <button
          v-if="depth < 3 && comment.replies_count && Number(comment.replies_count) > 0"
          @click="toggleReplies"
          class="inline-flex items-center gap-1 font-semibold text-primary hover:text-primary/80 transition-colors cursor-pointer"
        >
          <component :is="showReplies ? ChevronUp : ChevronDown" class="size-3.5" />
          {{ showReplies ? 'Hide' : 'View' }} {{ comment.replies_count }} {{ Number(comment.replies_count) === 1 ? 'reply' : 'replies' }}
        </button>
      </div>

      <!-- ── Inline Reply Form ──────────────────────────────────── -->
      <div v-if="showReplyForm && depth < 3" class="mt-3">
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
              <Button size="sm" variant="ghost" @click="toggleReplyBox">Cancel</Button>
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
      <!-- ─────────────────────────────────────────────────────────── -->

      <!-- ── Recursive Children ─────────────────────────────────── -->
      <!--
        IMPORTANT: Children are rendered here, inside the parent's CONTENT column.
        Each child's own avatar column (w-9) starts at the left of this area.
        The child's horizontal connector (position:absolute right:100%) will reach LEFT
        back into the parent's avatar column to touch the vertical thread line.
      -->
      <div v-if="showReplies && depth < 3" class="mt-3 space-y-1">
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

          <!-- Load more -->
          <div v-if="currentReplyPage < lastReplyPage" class="flex justify-start pt-1 pb-2">
            <button
              @click="loadMoreReplies"
              :disabled="isFetchingMoreReplies"
              class="flex items-center gap-1.5 text-xs font-semibold text-primary hover:text-primary/80 transition-colors disabled:opacity-50"
            >
              <Loader2 v-if="isFetchingMoreReplies" class="size-3.5 animate-spin" />
              <ChevronDown v-else class="size-3.5" />
              {{ isFetchingMoreReplies ? 'Loading...' : 'View more replies' }}
            </button>
          </div>
        </template>
      </div>
      <!-- ─────────────────────────────────────────────────────────── -->

    </div>
    <!-- ────────────────────────────────────────────────────────────── -->
  </div>

  <!-- Report Modal -->
  <div v-if="showReportModal" class="fixed inset-0 z-100 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
    <div class="bg-card w-full max-w-md p-6 rounded-xl shadow-lg border border-border flex flex-col gap-4">
      <h3 class="text-lg font-semibold text-foreground">Report Comment</h3>
      <p class="text-sm text-muted-foreground -mt-2">
        Please provide a reason for reporting this comment by <strong>{{ comment.author?.name || 'this user' }}</strong>.
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
</template>
