<script setup lang="ts">
import { ref, computed, inject } from 'vue'
import type { CommentResource } from '@/api/generated/model'
import { formatRelativeDate } from '@/utils/date'
import { CornerDownRight, ChevronDown, ChevronUp, Flag, Loader2 } from '@lucide/vue'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Avatar, AvatarImage, AvatarFallback } from '@/components/ui/avatar'
import CommentReportModal from './CommentReportModal.vue'
import CommentReplyForm from './CommentReplyForm.vue'
import { useCommentReplies } from './useCommentReplies'

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

const incrementCommentCount = inject<() => void>('incrementCommentCount', () => {})

const showReplyForm = ref(false)
const showReportModal = ref(false)
const isExpanded = ref(false)

const formattedContent = computed(() => {
  return props.comment.content?.replace(/\n{3,}/g, '\n\n') || ''
})

const isLongComment = computed(() => {
  const content = formattedContent.value
  const lineCount = (content.match(/\n/g) || []).length
  return lineCount >= 4 || content.length > 280
})

const {
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
} = useCommentReplies(props.pluginId, props.comment.id)

function toggleReplyBox() {
  if (props.depth >= 3) return
  showReplyForm.value = !showReplyForm.value
}

async function handleReplySubmitted(createdComment: CommentResource | null) {
  if ((!hasFetchedReplies.value && Number(props.comment.replies_count) > 0) || !createdComment) {
    await fetchReplies(1, false)
  }
  if (createdComment && !replies.value.some(r => r.id === createdComment.id)) {
    replies.value.unshift(createdComment)
  }

  props.comment.replies_count = String((Number(props.comment.replies_count) || 0) + 1)
  hasFetchedReplies.value = true
  showReplies.value = true
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
      <Avatar class="size-9 border border-border/50 bg-primary/10 text-primary font-bold shrink-0">
        <AvatarImage
          v-if="comment.author?.avatar_url"
          :src="comment.author.avatar_url"
          :alt="comment.author?.name || 'Avatar'"
        />
        <AvatarFallback class="bg-primary/10 text-primary text-sm font-bold">
          {{ comment.author?.name ? comment.author.name.charAt(0).toUpperCase() : 'U' }}
        </AvatarFallback>
      </Avatar>

      <!-- Vertical thread line — visible when replies or reply-form is open -->
      <div
        v-if="(showReplies || showReplyForm) && depth < 3"
        class="w-0.5 flex-1 mt-2 rounded-full bg-border/70"
      ></div>
    </div>

    <!-- ── Content column ────────────────────────────────────────── -->
    <div class="flex-1 min-w-0 pb-3">
      <!-- Author + timestamp -->
      <div class="flex items-center justify-between gap-2 flex-wrap mb-0.5">
        <div class="flex items-center gap-2 flex-wrap">
          <span class="font-semibold text-sm">{{ comment.author?.name || 'Unknown' }}</span>

          <Badge
            v-if="parentAuthorName"
            variant="outline"
            class="inline-flex items-center gap-1 text-xs text-muted-foreground bg-muted/70 px-2 py-0.5 rounded-full border-border/40 font-normal"
          >
            <CornerDownRight class="size-3 text-primary" />
            <span>replying to <strong class="text-foreground font-semibold">@{{ parentAuthorName }}</strong></span>
          </Badge>

          <span class="text-xs text-muted-foreground">&bull; {{ formatRelativeDate(comment.created_at) }}</span>
        </div>

        <Button
          variant="ghost"
          size="icon-xs"
          class="text-muted-foreground hover:text-destructive hover:bg-destructive/10 cursor-pointer"
          title="Report this comment"
          aria-label="Report this comment"
          @click="showReportModal = true"
        >
          <Flag class="size-3.5" />
        </Button>
      </div>

      <!-- Comment text -->
      <div class="mt-0.5">
        <p
          class="text-sm text-foreground/90 whitespace-pre-wrap leading-relaxed wrap-break-words"
          :class="{ 'line-clamp-4': isLongComment && !isExpanded }"
        >{{ formattedContent }}</p>

        <Button
          v-if="isLongComment"
          variant="ghost"
          size="xs"
          class="h-auto p-0 text-xs font-semibold text-primary hover:text-primary/80 hover:bg-transparent mt-1 cursor-pointer"
          @click="isExpanded = !isExpanded"
        >
          {{ isExpanded ? 'Show less' : 'Read more' }}
        </Button>
      </div>

      <!-- Action buttons -->
      <div class="flex items-center gap-4 mt-2 text-xs">
        <Button
          v-if="depth < 3"
          variant="ghost"
          size="xs"
          class="h-auto p-0 font-semibold text-muted-foreground hover:text-foreground hover:bg-transparent transition-colors cursor-pointer"
          @click="toggleReplyBox"
        >
          Reply
        </Button>

        <Button
          v-if="depth < 3 && comment.replies_count && Number(comment.replies_count) > 0"
          variant="ghost"
          size="xs"
          class="h-auto p-0 inline-flex items-center gap-1 font-semibold text-primary hover:text-primary/80 hover:bg-transparent transition-colors cursor-pointer"
          @click="toggleReplies(depth)"
        >
          <component :is="showReplies ? ChevronUp : ChevronDown" class="size-3.5" />
          {{ showReplies ? 'Hide' : 'View' }} {{ comment.replies_count }} {{ Number(comment.replies_count) === 1 ? 'reply' : 'replies' }}
        </Button>
      </div>

      <!-- ── Inline Reply Form Subcomponent ─────────────────────── -->
      <CommentReplyForm
        v-if="showReplyForm && depth < 3"
        :plugin-id="pluginId"
        :comment-id="comment.id"
        @cancel="toggleReplyBox"
        @submitted="handleReplySubmitted"
      />

      <!-- ── Recursive Children ─────────────────────────────────── -->
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
            <Button
              variant="ghost"
              size="xs"
              :disabled="isFetchingMoreReplies"
              class="h-auto p-0 flex items-center gap-1.5 text-xs font-semibold text-primary hover:text-primary/80 hover:bg-transparent transition-colors disabled:opacity-50 cursor-pointer"
              @click="loadMoreReplies"
            >
              <Loader2 v-if="isFetchingMoreReplies" class="size-3.5 animate-spin" />
              <ChevronDown v-else class="size-3.5" />
              {{ isFetchingMoreReplies ? 'Loading...' : 'View more replies' }}
            </Button>
          </div>
        </template>
      </div>
    </div>
  </div>

  <!-- ── Report Modal Subcomponent ──────────────────────────────── -->
  <CommentReportModal
    :show="showReportModal"
    :plugin-id="pluginId"
    :comment-id="comment.id"
    :author-name="comment.author?.name"
    :comment-content="comment.content"
    @close="showReportModal = false"
  />
</template>
