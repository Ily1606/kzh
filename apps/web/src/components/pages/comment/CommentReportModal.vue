<script setup lang="ts">
import { ref } from 'vue'
import ReportModal from '@/components/common/ReportModal.vue'
import CommentReportForm from './CommentReportForm.vue'

defineProps<{
  show: boolean
  pluginId: string
  commentId: string
  authorName?: string
  commentContent?: string
}>()

const emit = defineEmits<{
  (e: 'close'): void
}>()

const isSubmitting = ref(false)
</script>

<template>
  <ReportModal
    :show="show"
    :submitting="isSubmitting"
    unauth-message="You need to be signed in to submit a comment report. Please sign in to help us maintain a safe community."
    success-message="Thank you for helping keep our community safe. Our moderation team will review this comment shortly."
    @close="emit('close')"
  >
    <template #default="{ handleClose, handleSuccess }">
      <CommentReportForm
        :plugin-id="pluginId"
        :comment-id="commentId"
        :author-name="authorName"
        v-model:submitting="isSubmitting"
        @cancel="handleClose"
        @success="handleSuccess"
      />
    </template>
  </ReportModal>
</template>
