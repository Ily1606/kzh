<script setup lang="ts">
import { getPluginReport } from '@/api/generated/endpoints'
import ReportForm from '@/components/common/ReportForm.vue'

const props = defineProps<{
  pluginId: string
  pluginName?: string
}>()

const emit = defineEmits<{
  (e: 'cancel'): void
  (e: 'success', message: string): void
}>()

const isSubmitting = defineModel<boolean>('submitting', { default: false })
const { pluginReportStore } = getPluginReport()

function submit(reason: string) {
  return pluginReportStore(props.pluginId, { reason })
}
</script>

<template>
  <ReportForm
    title="Report Plugin"
    textarea-id="plugin-report-reason"
    placeholder="Please provide details about why you are reporting this plugin (minimum 10 characters)..."
    already-reported-message="You have already reported this plugin."
    unauthenticated-message="Please sign in to report this plugin."
    success-message="Plugin reported successfully. Our moderators will review it shortly."
    :submit="submit"
    v-model:submitting="isSubmitting"
    @cancel="emit('cancel')"
    @success="(msg) => emit('success', msg)"
  >
    <template #target-description>
      Reporting plugin: <strong class="text-foreground">{{ pluginName || 'this plugin' }}</strong>
    </template>
  </ReportForm>
</template>
