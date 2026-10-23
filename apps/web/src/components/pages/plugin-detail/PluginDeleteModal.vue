<script setup lang="ts">
import { watch, onUnmounted } from 'vue'
import { AlertTriangle, Loader2, X } from '@lucide/vue'
import { Button } from '@/components/ui/button'

const props = withDefaults(
  defineProps<{
    show: boolean
    pluginName: string
    isDeleting?: boolean
  }>(),
  {
    isDeleting: false,
  },
)

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'confirm'): void
}>()

function handleClose() {
  if (props.isDeleting) return
  emit('close')
}

function handleConfirm() {
  if (props.isDeleting) return
  emit('confirm')
}

function onKeydown(e: KeyboardEvent) {
  if (e.key === 'Escape' && props.show && !props.isDeleting) {
    handleClose()
  }
}

watch(
  () => props.show,
  (newVal) => {
    if (newVal) {
      if (typeof window !== 'undefined') {
        window.addEventListener('keydown', onKeydown)
      }
    } else {
      if (typeof window !== 'undefined') {
        window.removeEventListener('keydown', onKeydown)
      }
    }
  },
  { immediate: true },
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
          aria-labelledby="delete-dialog-title"
          aria-describedby="delete-dialog-desc"
        >
          <!-- Close button -->
          <button
            type="button"
            @click="handleClose"
            :disabled="isDeleting"
            class="absolute top-4 right-4 p-1.5 rounded-lg text-muted-foreground hover:text-foreground hover:bg-muted transition-colors cursor-pointer disabled:pointer-events-none disabled:opacity-50"
            aria-label="Close"
          >
            <X class="size-4" />
          </button>

          <div class="flex items-start gap-4 pt-1">
            <div class="size-11 rounded-full bg-destructive/10 text-destructive flex items-center justify-center shrink-0">
              <AlertTriangle class="size-5" />
            </div>
            <div class="space-y-1.5 min-w-0 flex-1">
              <h3 id="delete-dialog-title" class="text-lg font-semibold text-foreground">
                Delete Plugin
              </h3>
              <p id="delete-dialog-desc" class="text-sm text-muted-foreground leading-relaxed">
                Are you sure you want to delete <span class="font-medium text-foreground">{{ pluginName }}</span>?
                This action will permanently remove the plugin and cascade delete all associated comments and stars. This action cannot be undone.
              </p>
            </div>
          </div>

          <div class="flex justify-end gap-3 pt-2">
            <Button
              type="button"
              variant="outline"
              size="sm"
              :disabled="isDeleting"
              @click="handleClose"
            >
              Cancel
            </Button>
            <Button
              type="button"
              variant="destructive"
              size="sm"
              :disabled="isDeleting"
              @click="handleConfirm"
            >
              <Loader2 v-if="isDeleting" class="size-4 animate-spin" />
              <span>{{ isDeleting ? 'Deleting...' : 'Delete Plugin' }}</span>
            </Button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>
