<script setup lang="ts">
import { computed, ref } from 'vue'
import { Check, Copy, Terminal } from 'lucide-vue-next'

const props = defineProps<{
  /** Full package name as published, e.g. `@dsh/guardrails`. */
  name: string
}>()

const copied = ref(false)
const shortName = computed(() => props.name.split('/').pop() ?? props.name)

async function copyInstallCommand() {
  try {
    await navigator.clipboard.writeText(`npx dsh add ${props.name}`)
    copied.value = true
    setTimeout(() => {
      copied.value = false
    }, 2000)
  } catch {
    // ignore
  }
}
</script>

<template>
  <div class="flex items-center justify-between rounded-lg border border-border/50 bg-muted/50 p-3 font-mono text-sm">
    <span class="flex items-center gap-2 truncate">
      <Terminal class="size-4 shrink-0 text-muted-foreground" />
      npx dsh add {{ shortName }}
    </span>
    <button
      @click="copyInstallCommand"
      class="inline-flex items-center gap-1.5 rounded-md p-1 transition-colors"
      :class="copied ? 'text-primary' : 'text-muted-foreground hover:text-foreground'"
      :title="copied ? 'Copied' : 'Copy command'"
    >
      <Check v-if="copied" class="size-4" />
      <Copy v-else class="size-4" />
    </button>
  </div>
</template>
