<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { Button } from '@/components/ui/button'
import { Textarea } from '@/components/ui/textarea'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Flag, Loader2 } from '@lucide/vue'
import { getPluginReport } from '@/api/generated/endpoints'
import { notifySuccess, notifyError } from '@/utils/toast'
import { getApiErrorMessage } from '@/utils/error'

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

const reportContent = ref('')
const errorMessage = ref('')
const textareaRef = ref<any>(null)

const MIN_LENGTH = 10
const MAX_LENGTH = 1000

const isTooShort = computed(() => {
  const len = reportContent.value.trim().length
  return len > 0 && len < MIN_LENGTH
})

const canSubmit = computed(() => {
  const len = reportContent.value.trim().length
  return len >= MIN_LENGTH && len <= MAX_LENGTH && !isSubmitting.value
})

async function submitReport() {
  if (!canSubmit.value || isSubmitting.value) return
  errorMessage.value = ''
  isSubmitting.value = true

  try {
    const res = await pluginReportStore(props.pluginId, {
      reason: reportContent.value.trim(),
    })
    const msg =
      (res as any)?.message ||
      'Plugin reported successfully. Our moderators will review it shortly.'
    notifySuccess(msg)
    emit('success', msg)
  } catch (err: any) {
    if (err?.response?.status === 409) {
      errorMessage.value =
        err?.response?.data?.message || 'You have already reported this plugin.'
    } else if (err?.response?.status === 401) {
      errorMessage.value = 'Please sign in to report this plugin.'
    } else {
      errorMessage.value = getApiErrorMessage(err, {
        default: 'Failed to submit report. Please try again.',
      })
    }
    notifyError(errorMessage.value)
  } finally {
    isSubmitting.value = false
  }
}

onMounted(() => {
  setTimeout(() => {
    const el = textareaRef.value?.$el ?? textareaRef.value
    el?.focus?.()
  }, 100)
})
</script>

<template>
  <form class="flex flex-col gap-4" @submit.prevent="submitReport">
    <!-- Header -->
    <div class="flex items-center gap-3 pr-8">
      <div class="size-9 rounded-full bg-destructive/10 text-destructive flex items-center justify-center shrink-0">
        <Flag class="size-4.5" />
      </div>
      <div>
        <h3 class="text-base font-semibold text-foreground">Report Plugin</h3>
        <p class="text-xs text-muted-foreground">
          Reporting plugin: <strong class="text-foreground">{{ pluginName || 'this plugin' }}</strong>
        </p>
      </div>
    </div>

    <!-- Reason Textarea -->
    <div class="space-y-1.5">
      <div class="flex items-center justify-between text-xs">
        <label for="plugin-report-reason" class="font-medium text-foreground/80">
          Reason for report <span class="text-destructive">*</span>
        </label>
        <span
          class="tabular-nums"
          :class="reportContent.length > MAX_LENGTH ? 'text-destructive font-semibold' : 'text-muted-foreground'"
        >
          {{ reportContent.length }} / {{ MAX_LENGTH }}
        </span>
      </div>

      <Textarea
        id="plugin-report-reason"
        ref="textareaRef"
        v-model="reportContent"
        rows="4"
        maxlength="1000"
        placeholder="Please provide details about why you are reporting this plugin (minimum 10 characters)..."
        class="resize-none"
        :disabled="isSubmitting"
        required
      />

      <p v-if="isTooShort" class="text-xs text-amber-500">
        Reason must be at least {{ MIN_LENGTH }} characters (need {{ MIN_LENGTH - reportContent.trim().length }} more).
      </p>
    </div>

    <!-- Inline Error -->
    <Alert v-if="errorMessage" variant="destructive" class="py-2 px-3 text-xs">
      <AlertDescription>{{ errorMessage }}</AlertDescription>
    </Alert>

    <!-- Footer Actions -->
    <div class="flex items-center justify-end gap-2.5 pt-2">
      <Button
        type="button"
        variant="ghost"
        size="sm"
        :disabled="isSubmitting"
        @click="emit('cancel')"
      >
        Cancel
      </Button>

      <Button
        type="submit"
        variant="destructive"
        size="sm"
        :disabled="!canSubmit || isSubmitting"
        class="gap-1.5"
      >
        <Loader2 v-if="isSubmitting" class="size-3.5 animate-spin" />
        {{ isSubmitting ? 'Submitting...' : 'Submit Report' }}
      </Button>
    </div>
  </form>
</template>

