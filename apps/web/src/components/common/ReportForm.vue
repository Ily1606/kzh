<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { Button } from '@/components/ui/button'
import { Textarea } from '@/components/ui/textarea'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Flag, Loader2 } from '@lucide/vue'
import { notifySuccess, notifyError } from '@/utils/toast'
import { getApiErrorMessage } from '@/utils/error'

const props = withDefaults(
  defineProps<{
    title: string
    targetDescription?: string
    submit: (reason: string) => Promise<any>
    alreadyReportedMessage?: string
    unauthenticatedMessage?: string
    successMessage?: string
    textareaId?: string
    placeholder?: string
    minLength?: number
    maxLength?: number
  }>(),
  {
    targetDescription: '',
    alreadyReportedMessage: 'You have already reported this item.',
    unauthenticatedMessage: 'Please sign in to submit a report.',
    successMessage: 'Report submitted successfully. Our moderators will review it shortly.',
    textareaId: 'report-reason',
    placeholder: 'Please provide details about why you are reporting this (minimum 10 characters)...',
    minLength: 10,
    maxLength: 1000,
  }
)

const emit = defineEmits<{
  (e: 'cancel'): void
  (e: 'success', message: string): void
}>()

const isSubmitting = defineModel<boolean>('submitting', { default: false })

const reportContent = ref('')
const errorMessage = ref('')
const textareaRef = ref<any>(null)

const isTooShort = computed(() => {
  const len = reportContent.value.trim().length
  return len > 0 && len < props.minLength
})

const canSubmit = computed(() => {
  const len = reportContent.value.trim().length
  return len >= props.minLength && len <= props.maxLength && !isSubmitting.value
})

async function submitReport() {
  if (!canSubmit.value || isSubmitting.value) return
  errorMessage.value = ''
  isSubmitting.value = true

  try {
    const res = await props.submit(reportContent.value.trim())
    const msg = (res as any)?.message || props.successMessage
    notifySuccess(msg)
    emit('success', msg)
  } catch (err: any) {
    if (err?.response?.status === 409) {
      errorMessage.value =
        err?.response?.data?.message || props.alreadyReportedMessage
    } else if (err?.response?.status === 401) {
      errorMessage.value = props.unauthenticatedMessage
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
        <h3 class="text-base font-semibold text-foreground">{{ title }}</h3>
        <p v-if="$slots['target-description'] || targetDescription" class="text-xs text-muted-foreground">
          <slot name="target-description">
            {{ targetDescription }}
          </slot>
        </p>
      </div>
    </div>

    <!-- Reason Textarea -->
    <div class="space-y-1.5">
      <div class="flex items-center justify-between text-xs">
        <label :for="textareaId" class="font-medium text-foreground/80">
          Reason for report <span class="text-destructive">*</span>
        </label>
        <span
          class="tabular-nums"
          :class="reportContent.length > maxLength ? 'text-destructive font-semibold' : 'text-muted-foreground'"
        >
          {{ reportContent.length }} / {{ maxLength }}
        </span>
      </div>

      <Textarea
        :id="textareaId"
        ref="textareaRef"
        v-model="reportContent"
        rows="4"
        :maxlength="maxLength"
        :placeholder="placeholder"
        class="min-h-24 max-h-48 resize-none overflow-y-auto"
        :disabled="isSubmitting"
        required
      />

      <p v-if="isTooShort" class="text-xs text-amber-500">
        Reason must be at least {{ minLength }} characters (need {{ minLength - reportContent.trim().length }} more).
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
        :disabled="!canSubmit"
      >
        <Loader2 v-if="isSubmitting" class="size-3.5 animate-spin mr-1.5" />
        <Flag v-else class="size-3.5 mr-1.5" />
        {{ isSubmitting ? 'Submitting...' : 'Submit Report' }}
      </Button>
    </div>
  </form>
</template>

