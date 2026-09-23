<script setup lang="ts">
import { cn } from '@/utils/cn'

interface Props {
  modelValue?: string | number
  label?: string
  error?: string
  helperText?: string
  id?: string
  type?: string
  placeholder?: string
  disabled?: boolean
  required?: boolean
  class?: string
}

const props = withDefaults(defineProps<Props>(), {
  type: 'text',
  disabled: false,
  required: false,
})

const emit = defineEmits<{
  'update:modelValue': [value: string | number]
}>()

const onInput = (event: Event) => {
  const target = event.target as HTMLInputElement
  emit('update:modelValue', target.value)
}

const inputId = props.id || `input-${Math.random().toString(36).slice(2, 9)}`
</script>

<template>
  <div :class="cn('w-full', props.class)">
    <label v-if="label" :for="inputId" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
      {{ label }} <span v-if="required" class="text-red-500">*</span>
    </label>

    <div class="relative">
      <input
        :id="inputId"
        :type="type"
        :value="modelValue"
        @input="onInput"
        :placeholder="placeholder"
        :disabled="disabled"
        :required="required"
        :class="cn(
          'flex h-10 w-full rounded-md border border-gray-300 bg-transparent px-3 py-2 text-sm placeholder:text-gray-400 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-700 dark:text-gray-100',
          error && 'border-red-500 focus:ring-red-500'
        )"
      />
    </div>

    <p v-if="error" class="mt-1 text-sm text-red-500">{{ error }}</p>
    <p v-else-if="helperText" class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ helperText }}</p>
  </div>
</template>

