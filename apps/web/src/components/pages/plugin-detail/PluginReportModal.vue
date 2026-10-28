<script setup lang="ts">
import { ref } from 'vue'
import ReportModal from '@/components/common/ReportModal.vue'
import PluginReportForm from './PluginReportForm.vue'

defineProps<{
  show: boolean
  pluginId: string
  pluginName?: string
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
    unauth-message="You need to be signed in to submit a report for this plugin. Please sign in to help us maintain a safe community."
    success-message="Thank you for helping keep our community safe. Our moderation team will review this plugin shortly."
    @close="emit('close')"
  >
    <template #default="{ handleClose, handleSuccess }">
      <PluginReportForm
        :plugin-id="pluginId"
        :plugin-name="pluginName"
        v-model:submitting="isSubmitting"
        @cancel="handleClose"
        @success="handleSuccess"
      />
    </template>
  </ReportModal>
</template>
