<script setup lang="ts">
import { ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { getCommentReport } from '@/api/generated/endpoints'
import { Button } from '@/components/ui/button'
import { Textarea } from '@/components/ui/textarea'
import { useAuth } from '@/composables/useAuth'
import { Check, Loader2 } from '@lucide/vue'

const props = defineProps<{
  open: boolean
  pluginId: string
  commentId: string
  authorName?: string
  authorId?: string
}>()

const emit = defineEmits<{
  (e: 'update:open', val: boolean): void
  (e: 'reported'): void
}>()

const auth = useAuth()
const route = useRoute()
const { commentReportStore } = getCommentReport()

const reportContent = ref('')
const isSubmittingReport = ref(false)
const reportErrorMessage = ref('')
const isReportSubmitted = ref(false)
const reportSuccessMessage = ref('')

function resetModal() {
  reportContent.value = ''
  reportErrorMessage.value = ''
  isReportSubmitted.value = false
  reportSuccessMessage.value = ''
}

watch(
  () => props.open,
  (isOpen) => {
    if (isOpen) {
      resetModal()
    }
  }
)

function closeModal() {
  emit('update:open', false)
  resetModal()
}

async function submitReport() {
  if (!auth.isAuthenticated) return
  if (auth.user?.id && props.authorId && auth.user.id === props.authorId) {
    reportErrorMessage.value = 'You cannot report your own comment.'
    return
  }
  const reason = reportContent.value.trim()
  if (!reason || reason.length < 10) return

  isSubmittingReport.value = true
  reportErrorMessage.value = ''

  try {
    const res = await commentReportStore(props.pluginId, props.commentId, { reason })
    reportSuccessMessage.value = res?.message || 'Comment reported successfully. Our moderators will review it shortly.'
    isReportSubmitted.value = true
    emit('reported')
  } catch (err: any) {
    reportErrorMessage.value = err?.response?.data?.message || 'An error occurred while submitting the report.'
  } finally {
    isSubmittingReport.value = false
  }
}
</script>

<template>
  <div
    v-if="open"
    class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm"
    @click.self="closeModal"
  >
    <div class="bg-card w-full max-w-md p-6 rounded-xl shadow-xl border border-border flex flex-col gap-4 animate-in fade-in zoom-in-95 duration-150">
      <!-- Success State -->
      <div v-if="isReportSubmitted" class="py-4 flex flex-col items-center text-center gap-3">
        <div class="size-12 rounded-full bg-primary/10 text-primary flex items-center justify-center">
          <Check class="size-6" />
        </div>
        <div>
          <h3 class="text-lg font-semibold text-foreground">Report Submitted</h3>
          <p class="text-sm text-muted-foreground mt-1 max-w-xs">
            {{ reportSuccessMessage }}
          </p>
        </div>
        <Button class="mt-2 w-full sm:w-auto min-w-28" @click="closeModal">
          Done
        </Button>
      </div>

      <!-- Unauthenticated guard -->
      <div v-else-if="!auth.isAuthenticated" class="space-y-4">
        <div>
          <h3 class="text-lg font-semibold text-foreground">Report Comment</h3>
          <p class="text-sm text-muted-foreground mt-1">
            Please provide a reason for reporting this comment by <strong>{{ authorName || 'this user' }}</strong>.
          </p>
        </div>
        <div class="text-xs text-muted-foreground bg-muted/50 rounded-lg p-3 border border-border/50 flex items-center justify-between gap-3">
          <span>Please sign in to report this comment.</span>
          <router-link
            :to="{ name: 'login', query: { redirect: route.fullPath } }"
            class="text-primary hover:underline font-semibold shrink-0"
          >
            Sign In &rarr;
          </router-link>
        </div>
        <div class="flex justify-end">
          <Button variant="ghost" @click="closeModal">Close</Button>
        </div>
      </div>

      <!-- Authenticated Form -->
      <template v-else>
        <div>
          <h3 class="text-lg font-semibold text-foreground">Report Comment</h3>
          <p class="text-sm text-muted-foreground mt-1">
            Please provide a reason for reporting this comment by <strong>{{ authorName || 'this user' }}</strong>.
          </p>
        </div>

        <!-- Inline error alert -->
        <div
          v-if="reportErrorMessage"
          class="text-xs text-destructive bg-destructive/10 border border-destructive/20 rounded-lg p-3 flex items-start gap-2"
        >
          <span class="font-medium flex-1">{{ reportErrorMessage }}</span>
        </div>

        <Textarea
          v-model="reportContent"
          rows="4"
          maxlength="1000"
          placeholder="Reason for reporting..."
          class="resize-none"
        />
        <div class="flex items-center justify-between text-xs text-muted-foreground -mt-2">
          <span :class="{ 'text-destructive': reportContent.trim().length > 0 && reportContent.trim().length < 10 }">
            {{ reportContent.trim().length > 0 && reportContent.trim().length < 10 ? `Minimum 10 characters required (${reportContent.trim().length}/10)` : '' }}
          </span>
          <span class="ml-auto">{{ reportContent.length }}/1000</span>
        </div>
        <div class="flex justify-end gap-3 mt-1">
          <Button variant="ghost" @click="closeModal">Cancel</Button>
          <Button
            variant="destructive"
            :disabled="isSubmittingReport || reportContent.trim().length < 10"
            @click="submitReport"
          >
            <Loader2 v-if="isSubmittingReport" class="size-4 mr-2 animate-spin" />
            {{ isSubmittingReport ? 'Submitting...' : 'Submit Report' }}
          </Button>
        </div>
      </template>
    </div>
  </div>
</template>

