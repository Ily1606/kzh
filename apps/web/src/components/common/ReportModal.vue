<script setup lang="ts">
import { ref, computed, watch, onUnmounted } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { Button } from '@/components/ui/button'
import { X, ShieldAlert, CheckCircle2 } from '@lucide/vue'
import { useAuth } from '@/composables/useAuth'
import ReportForm from './ReportForm.vue'

const props = withDefaults(
  defineProps<{
    show: boolean
    submitting?: boolean
    title?: string
    targetDescription?: string
    submit?: (reason: string) => Promise<any>
    unauthTitle?: string
    unauthMessage?: string
    successTitle?: string
    successMessage?: string
    alreadyReportedMessage?: string
    textareaId?: string
    placeholder?: string
  }>(),
  {
    submitting: undefined,
    title: '',
    targetDescription: '',
    submit: undefined,
    unauthTitle: 'Sign In Required',
    unauthMessage: 'You need to be signed in to submit a report. Please sign in to help us maintain a safe community.',
    successTitle: 'Report Received',
    successMessage: 'Thank you for helping keep our community safe. Our moderation team will review this shortly.',
    alreadyReportedMessage: 'You have already reported this item.',
    textareaId: 'report-reason',
    placeholder: 'Please provide details about why you are reporting this (minimum 10 characters)...',
  }
)

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'success', message: string): void
}>()

const auth = useAuth()
const route = useRoute()

const isSuccess = ref(false)
const internalSubmitting = ref(false)
const isSubmitting = computed(() => props.submitting ?? internalSubmitting.value)
let closeTimer: ReturnType<typeof setTimeout> | undefined

function handleClose() {
  if (isSubmitting.value) return
  clearTimeout(closeTimer)
  isSuccess.value = false
  emit('close')
}

function handleSuccess(msg?: string) {
  isSuccess.value = true
  if (msg) {
    emit('success', msg)
  }
  closeTimer = setTimeout(handleClose, 1400)
}

function onKeydown(e: KeyboardEvent) {
  if (e.key === 'Escape' && props.show) {
    handleClose()
  }
}

watch(
  () => props.show,
  (newVal) => {
    clearTimeout(closeTimer)
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
  clearTimeout(closeTimer)
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
            <h3 class="text-base font-semibold text-foreground">{{ successTitle }}</h3>
            <p class="text-xs text-muted-foreground max-w-xs mx-auto leading-relaxed">
              {{ successMessage }}
            </p>
          </div>

          <!-- ── Unauthenticated State ────────────────────────────────── -->
          <div v-else-if="!auth.isAuthenticated" class="space-y-4 pt-2">
            <div class="p-4 rounded-xl bg-muted/50 border border-border/80 text-center space-y-2">
              <div class="size-10 rounded-full bg-amber-500/10 text-amber-500 mx-auto flex items-center justify-center">
                <ShieldAlert class="size-5" />
              </div>
              <h4 class="font-semibold text-sm text-foreground">{{ unauthTitle }}</h4>
              <p class="text-xs text-muted-foreground leading-relaxed">
                {{ unauthMessage }}
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
          <div v-else>
            <slot
              :is-submitting="isSubmitting"
              :handle-close="handleClose"
              :handle-success="handleSuccess"
            >
              <ReportForm
                v-if="submit && title"
                :title="title"
                :target-description="targetDescription"
                :submit="submit"
                v-model:submitting="internalSubmitting"
                :already-reported-message="alreadyReportedMessage"
                :unauthenticated-message="unauthMessage"
                :success-message="successMessage"
                :textarea-id="textareaId"
                :placeholder="placeholder"
                @cancel="handleClose"
                @success="handleSuccess"
              >
                <template v-if="$slots['target-description']" #target-description>
                  <slot name="target-description" />
                </template>
              </ReportForm>
            </slot>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

