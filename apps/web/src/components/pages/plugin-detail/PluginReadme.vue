<script setup lang="ts">
import { toRef, watch } from 'vue'
import { FileText } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import { README_COLLAPSED_MAX_HEIGHT, usePluginReadme } from '@/composables/usePluginReadme'

const props = defineProps<{
  sourceLink: string
}>()

const emit = defineEmits<{
  /** The reader put the page back to the shortened form; the page may want to scroll. */
  (e: 'collapsed'): void
  /**
   * The README settled — rendered, empty, or failed. A link to an anchor inside
   * the README can only be honoured once the markdown is on the page and its
   * images have had a chance to reflow it.
   */
  (e: 'loaded'): void
}>()

const { html, isLoading, error, isExpanded, isLong, load, expand, collapse } =
  usePluginReadme(toRef(props, 'sourceLink'))

// A `watch` on the prop rather than `onMounted`: a detail page reached before
// its plugin resolves mounts this component only once the plugin is known, and
// watching also covers the case where the same component is reused for another
// plugin without a full remount.
watch(
  () => props.sourceLink,
  async () => {
    await load()
    emit('loaded')
  },
  { immediate: true },
)

function onCollapse() {
  collapse()
  emit('collapsed')
}
</script>

<template>
  <section class="space-y-4">
    <div class="flex items-center justify-between">
      <h2 class="text-xl font-semibold">About this plugin</h2>
      <span v-if="html" class="flex items-center gap-1 font-mono text-xs text-muted-foreground">
        <FileText class="size-3.5" /> README.md
      </span>
    </div>

    <div
      v-if="isLoading"
      class="flex flex-col items-center justify-center space-y-3 rounded-xl border border-border/50 bg-muted/20 p-12 text-muted-foreground"
    >
      <div class="h-6 w-6 animate-spin rounded-full border-2 border-primary border-t-transparent"></div>
      <span class="text-sm">Loading README from repository...</span>
    </div>

    <div v-else-if="html" class="group relative">
      <div
        ref="container"
        class="prose dark:prose-invert prose-headings:font-semibold prose-a:text-primary prose-a:underline hover:prose-a:text-primary/80 prose-pre:border prose-pre:bg-muted prose-pre:text-foreground prose-img:rounded-lg relative max-w-none rounded-xl border border-border/60 bg-card p-6 shadow-sm transition-all duration-300 md:p-8"
        :style="isLong && !isExpanded ? { maxHeight: `${README_COLLAPSED_MAX_HEIGHT}px`, overflow: 'hidden' } : { overflowX: 'auto' }"
        v-html="html"
      ></div>

      <div
        v-if="isLong && !isExpanded"
        class="absolute inset-x-0 bottom-0 flex h-40 items-end justify-center rounded-b-xl bg-linear-to-t from-background via-background/80 to-transparent pb-6"
      >
        <Button
          variant="outline"
          size="sm"
          @click="expand"
          class="rounded-full border-border/80 bg-background/95 px-6 font-medium shadow-md backdrop-blur-sm hover:bg-muted"
        >
          Read full documentation
        </Button>
      </div>

      <!-- Collapse button -->
      <div v-else-if="isLong && isExpanded" class="mt-4 flex justify-center">
        <Button
          variant="ghost"
          size="sm"
          @click="onCollapse"
          class="text-muted-foreground hover:text-foreground"
        >
          Show less
        </Button>
      </div>
    </div>

    <div
      v-else
      class="flex min-h-40 flex-col items-center justify-center space-y-2 rounded-xl border border-border/50 bg-muted/30 p-8 text-muted-foreground"
    >
      <FileText class="size-8 stroke-[1.5] text-muted-foreground/60" />
      <p class="text-sm">{{ error || 'No README available for this plugin.' }}</p>
    </div>
  </section>
</template>
