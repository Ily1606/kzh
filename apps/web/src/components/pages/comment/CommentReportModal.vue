<script setup lang="ts">
import { ref, watch, onUnmounted } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { Button } from '@/components/ui/button'
import { X, ShieldAlert, CheckCircle2 } from '@lucide/vue'
import { useAuth } from '@/composables/useAuth'
import CommentReportForm from './CommentReportForm.vue'

const props = defineProps<{
  show: boolean
  pluginId: string
  commentId: string
  authorName?: string
  commentContent?: string
}>()

const emit = defineEmits<{
  (e: 'close'): void
}>()

const auth = useAuth()
const route = useRoute()

const isSuccess = ref(false)
const isSubmitting = ref(false)

function handleClose() {
  if (isSubmitting.value) return
  isSuccess.value = false
  emit('close')
}

function handleSuccess() {
  isSuccess.value = true
  setTimeout(() => {
    handleClose()
  }, 1400)
}

function onKeydown(e: KeyboardEvent) {
  if (e.key === 'Escape' && props.show) {
    handleClose()
  }
}

watch(
  () => props.show,
  (newVal) => {
    if (newVal) {
      isSuccess.value = false
      if (typeof window !== 'undefined') {
        window.addEventListener('keydown', onKeydown)
      }
    } else {
      if (typeof window !== 'undefined') {
        window.removeEventListener('keydown', onKeydown)
      }
    }
  }
)

onUnmounted(() => {
  if (typeof window !== 'undefined') {
    window.removeEventListener('keydown', onKeydown)
  }
})
</script>

<template>
  <Teleport to="body">
    <Transition
      enter-active-class="transition duration-200 ease-out"
      enter-from-class="opacity-0"
      leave-active-class="transition duration-150 ease-in"
      leave-to-class="opacity-0"
    >
      <div
        v-if="show"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-xs p-4"
        @click.self="handleClose"
      >
        <div
          class="bg-card w-full max-w-md rounded-2xl shadow-2xl border border-border p-6 flex flex-col gap-4 relative animate-in fade-in zoom-in-95 duration-150 max-h-[90vh] overflow-y-auto"
          role="dialog"
          aria-modal="true"
        >
          <!-- Close button -->
          <button
            type="button"
            @click="handleClose"
            class="absolute top-4 right-4 p-1.5 rounded-lg text-muted-foreground hover:text-foreground hover:bg-muted transition-colors cursor-pointer"
            aria-label="Close"
          >
            <X class="size-4" />
          </button>

          <!-- ── Success State ────────────────────────────────────────── -->
          <div v-if="isSuccess" class="py-6 text-center space-y-3">
            <div class="size-12 rounded-full bg-primary/10 text-primary mx-auto flex items-center justify-center">
              <CheckCircle2 class="size-6 text-primary" />
            </div>
            <h3 class="text-base font-semibold text-foreground">Report Received</h3>
            <p class="text-xs text-muted-foreground max-w-xs mx-auto leading-relaxed">
              Thank you for helping keep our community safe. Our moderation team will review this comment shortly.
            </p>
          </div>

          <!-- ── Unauthenticated State ────────────────────────────────── -->
          <div v-else-if="!auth.isAuthenticated" class="space-y-4 pt-2">
            <div class="p-4 rounded-xl bg-muted/50 border border-border/80 text-center space-y-2">
              <div class="size-10 rounded-full bg-amber-500/10 text-amber-500 mx-auto flex items-center justify-center">
                <ShieldAlert class="size-5" />
              </div>
              <h4 class="font-semibold text-sm text-foreground">Sign In Required</h4>
              <p class="text-xs text-muted-foreground leading-relaxed">
                You need to be signed in to submit a comment report. Please sign in to help us maintain a safe community.
              </p>
            </div>
            <div class="flex justify-end gap-2.5 pt-1">
              <Button variant="ghost" size="sm" @click="handleClose">Cancel</Button>
              <RouterLink :to="{ name: 'login', query: { redirect: route.fullPath } }">
                <Button size="sm">Sign In</Button>
              </RouterLink>
            </div>
          </div>

          <!-- ── Active Form State ────────────────────────────────────── -->
          <CommentReportForm
            v-else
            :plugin-id="pluginId"
            :comment-id="commentId"
            :author-name="authorName"
            v-model:submitting="isSubmitting"
            @cancel="handleClose"
            @success="handleSuccess"
          />
        </div>
      </div>
    </Transition>
  </Teleport>
</template>
