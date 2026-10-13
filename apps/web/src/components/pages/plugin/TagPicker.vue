<script setup lang="ts">
import { computed } from 'vue'
import { Check, ChevronDown } from 'lucide-vue-next'
import { SubmitPluginRequestTagsItem } from '@/api/generated/model'
import { pluginStoreBodyTagsMax } from '@/api/generated/zod'
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover'

const props = withDefaults(defineProps<{
  modelValue?: string[]
  error?: string
}>(), { modelValue: () => [] })

const emit = defineEmits<{
  (e: 'update:modelValue', value: string[]): void
}>()

const allTags = Object.values(SubmitPluginRequestTagsItem)

// The API caps the list, so the last slot is closed here instead of sending a
// sixth tag and letting the server reject the whole submission.
const atLimit = computed(() => props.modelValue.length >= pluginStoreBodyTagsMax)

function toggle(tag: string) {
  if (props.modelValue.includes(tag)) {
    emit('update:modelValue', props.modelValue.filter((chosen) => chosen !== tag))
  } else if (!atLimit.value) {
    emit('update:modelValue', [...props.modelValue, tag])
  }
}
</script>

<template>
  <Popover>
    <PopoverTrigger
      data-test="tags-trigger"
      class="flex h-11 w-full cursor-pointer items-center justify-between gap-2 rounded-xl border border-border/60 bg-muted/40 px-3 py-2 text-sm text-foreground transition-colors focus-visible:bg-background focus-visible:border-ring focus-visible:ring-2 focus-visible:ring-ring/40"
      :class="{ 'border-destructive focus-visible:ring-destructive': error }"
    >
      <span v-if="modelValue.length === 0" class="text-muted-foreground">Select tags...</span>
      <span v-else class="truncate">{{ modelValue.join(', ') }}</span>
      <span class="flex shrink-0 items-center gap-2">
        <span class="text-xs text-muted-foreground">{{ modelValue.length }}/{{ pluginStoreBodyTagsMax }}</span>
        <ChevronDown class="size-4 text-muted-foreground" />
      </span>
    </PopoverTrigger>

    <PopoverContent align="start" class="w-[var(--reka-popover-trigger-width)] p-1">
      <div class="max-h-[var(--reka-popover-content-available-height)] overflow-y-auto">
        <button
          v-for="tag in allTags"
          :key="tag"
          type="button"
          :data-test="`tag-option-${tag}`"
          class="flex w-full cursor-pointer items-center justify-between gap-2 rounded-md px-2 py-2 text-left text-sm transition-colors hover:bg-accent hover:text-accent-foreground"
          :class="{ 'opacity-50': atLimit && !modelValue.includes(tag) }"
          @click="toggle(tag)"
        >
          <span>{{ tag }}</span>
          <Check v-if="modelValue.includes(tag)" class="size-4 text-primary" />
        </button>
      </div>

      <p v-if="atLimit" class="mt-1 flex items-center gap-1.5 border-t px-2 pt-2 text-xs text-muted-foreground">
        <Check class="size-3.5" />
        Limit reached — remove a tag to pick another.
      </p>
    </PopoverContent>
  </Popover>
</template>