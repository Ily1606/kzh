<script setup lang="ts">
import { ref, computed, inject } from 'vue'
import type { CommentResource } from '@/api/generated/model'
import { formatRelativeDate } from '@/utils/date'
import { useAuth } from '@/composables/useAuth'
import { CornerDownRight, ChevronDown, ChevronUp, Flag, Loader2 } from '@lucide/vue'
import CommentReplyForm from './CommentReplyForm.vue'
import CommentReportModal from './CommentReportModal.vue'
import { useCommentReplies } from '@/composables/useCommentReplies'
import { commentConfig } from '@/config/comments'

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
const incrementCommentCount = inject<() => void>('incrementCommentCount', () => {})

const showReplyForm = ref(false)
const showReportModal = ref(false)

const canReport = computed(() => {
  if (!auth.isAuthenticated) return true
  if (!auth.user?.id || !props.comment.author?.id) return true
  return auth.user.id !== props.comment.author.id
})

function openReportModal() {
  if (auth.isAuthenticated && auth.user?.id && props.comment.author?.id && auth.user.id === props.comment.author.id) {
    return
  }
  showReportModal.value = true
}

function toggleReplyBox() {
  if (props.depth >= commentConfig.maxDepth) return
  showReplyForm.value = !showReplyForm.value
}

const {
  replies,
  showReplies,
  isLoadingReplies,
  isFetchingMoreReplies,
  currentReplyPage,
  lastReplyPage,
  toggleReplies,
  loadMoreReplies,
  handleReplyCreated
} = useCommentReplies(props.pluginId, props.comment)

async function onReplySuccess(createdComment?: CommentResource) {
  await handleReplyCreated(createdComment)
  showReplyForm.value = false
  incrementCommentCount()
}

// Avatar width (w-9 = 36px), half = 18px; gap-3 = 12px
// Horizontal connector width = gap(12px) + half-avatar(18px) = 30px
const CONNECTOR_WIDTH = '30px'
</script>

<template>
  <div class="flex gap-3">
    <!-- ── Avatar column ─────────────────────────────────────────── -->
    <div class="relative flex flex-col items-center shrink-0 w-9">
      <!-- Horizontal connector for child comments (depth > 1) -->
      <div
        v-if="depth > 1"
        class="absolute top-4.25 h-px bg-border"
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
        v-if="(showReplies || showReplyForm) && depth < commentConfig.maxDepth"
        class="w-0.5 flex-1 mt-2 rounded-full bg-border/70"
      ></div>
    </div>

    <!-- ── Content column ────────────────────────────────────────── -->
    <div class="flex-1 min-w-0 pb-3">
      <!-- Author + timestamp + report button -->
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

          <span class="text-xs text-muted-foreground">&bull; {{ formatRelativeDate(comment.created_at) }}</span>
        </div>

        <button
          v-if="canReport"
          @click="openReportModal"
          title="Report this comment"
          class="text-muted-foreground hover:text-destructive transition-colors p-1 rounded-md hover:bg-destructive/10 cursor-pointer"
        >
          <Flag class="size-3.5" />
        </button>
      </div>

      <!-- Comment text -->
      <p class="text-sm text-foreground/90 whitespace-pre-wrap leading-relaxed">{{ comment.content }}</p>

      <!-- Action buttons -->
      <div class="flex items-center gap-4 mt-2 text-xs">
        <button
          v-if="depth < commentConfig.maxDepth"
          @click="toggleReplyBox"
          class="font-semibold text-muted-foreground hover:text-foreground transition-colors cursor-pointer"
        >
          Reply
        </button>

        <button
          v-if="depth < commentConfig.maxDepth && comment.replies_count && Number(comment.replies_count) > 0"
          @click="toggleReplies(depth)"
          class="inline-flex items-center gap-1 font-semibold text-primary hover:text-primary/80 transition-colors cursor-pointer"
        >
          <component :is="showReplies ? ChevronUp : ChevronDown" class="size-3.5" />
          {{ showReplies ? 'Hide' : 'View' }} {{ comment.replies_count }} {{ Number(comment.replies_count) === 1 ? 'reply' : 'replies' }}
        </button>
      </div>

      <!-- ── Inline Reply Form ──────────────────────────────────── -->
      <CommentReplyForm
        v-if="showReplyForm && depth < commentConfig.maxDepth"
        :plugin-id="pluginId"
        :parent-comment-id="comment.id"
        @reply-success="onReplySuccess"
        @cancel="toggleReplyBox"
      />

      <!-- ── Recursive Children ─────────────────────────────────── -->
      <div v-if="showReplies && depth < commentConfig.maxDepth" class="mt-3 space-y-1">
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
            :depth="Math.min(depth + 1, commentConfig.maxDepth)"
          />

          <!-- Load more replies -->
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
    </div>
  </div>

  <!-- Report Modal -->
  <CommentReportModal
    v-model:open="showReportModal"
    :plugin-id="pluginId"
    :comment-id="comment.id"
    :author-name="comment.author?.name"
    :author-id="comment.author?.id"
  />
</template>
