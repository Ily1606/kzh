<script setup lang="ts">
import { ref } from 'vue'
import { Button } from '@/components/ui/button'

defineProps<{
  show: boolean
  authorName?: string
}>()

const emit = defineEmits<{
  (e: 'close'): void
}>()

const reportContent = ref('')
const isSubmittingReport = ref(false)

async function submitReport() {
  if (!reportContent.value.trim()) return
  isSubmittingReport.value = true
  try {
    await new Promise(r => setTimeout(r, 600))
    alert('Your report has been submitted successfully.')
    reportContent.value = ''
    emit('close')
  } catch {
    alert('An error occurred while submitting the report.')
  } finally {
    isSubmittingReport.value = false
  }
}
</script>

<template>
  <div v-if="show" class="fixed inset-0 z-100 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
    <div class="bg-card w-full max-w-md p-6 rounded-xl shadow-lg border border-border flex flex-col gap-4">
      <h3 class="text-lg font-semibold text-foreground">Report Comment</h3>
      <p class="text-sm text-muted-foreground -mt-2">
        Please provide a reason for reporting this comment by <strong>{{ authorName || 'this user' }}</strong>.
      </p>
      <textarea
        v-model="reportContent"
        rows="4"
        placeholder="Reason for reporting..."
        class="flex w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
      ></textarea>
      <div class="flex justify-end gap-3 mt-2">
        <Button variant="ghost" @click="emit('close')">Cancel</Button>
        <Button variant="destructive" :disabled="isSubmittingReport || !reportContent.trim()" @click="submitReport">
          {{ isSubmittingReport ? 'Submitting...' : 'Submit Report' }}
        </Button>
      </div>
    </div>
  </div>
</template>

