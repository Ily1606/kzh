<script setup lang="ts">
import { getCommentReport } from '@/api/generated/endpoints'
import ReportForm from '@/components/common/ReportForm.vue'

const props = defineProps<{
  pluginId: string
  commentId: string
  authorName?: string
}>()

const emit = defineEmits<{
  (e: 'cancel'): void
  (e: 'success', message: string): void
}>()

const isSubmitting = defineModel<boolean>('submitting', { default: false })
const { commentReportStore } = getCommentReport()

function submit(reason: string) {
  return commentReportStore(props.pluginId, props.commentId, { reason })
}
</script>

<template>
  <ReportForm
    title="Report Comment"
    textarea-id="report-reason"
    placeholder="Please provide details about why you are reporting this comment (minimum 10 characters)..."
    already-reported-message="You have already reported this comment."
    unauthenticated-message="Please sign in to report this comment."
    success-message="Comment reported successfully. Our moderators will review it shortly."
    :submit="submit"
    v-model:submitting="isSubmitting"
    @cancel="emit('cancel')"
    @success="(msg) => emit('success', msg)"
  >
    <template #target-description>
      Reporting comment by <strong class="text-foreground">@{{ authorName || 'user' }}</strong>
    </template>
  </ReportForm>
</template>
