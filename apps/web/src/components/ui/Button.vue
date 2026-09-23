<script setup lang="ts">
import { computed } from 'vue'
import { cn } from '@/utils/cn'

interface Props {
  variant?: 'primary' | 'secondary' | 'outline' | 'ghost' | 'danger'
  size?: 'sm' | 'md' | 'lg'
  disabled?: boolean
  loading?: boolean
  type?: 'button' | 'submit' | 'reset'
  class?: string
}

const props = withDefaults(defineProps<Props>(), {
  variant: 'primary',
  size: 'md',
  disabled: false,
  loading: false,
  type: 'button',
})

const buttonClass = computed(() => {
  return cn(
    'inline-flex items-center justify-center rounded-md text-sm font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary disabled:pointer-events-none disabled:opacity-50',
    {
      'bg-primary text-white hover:bg-primary-hover': props.variant === 'primary',
      'bg-gray-100 text-gray-900 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-100 dark:hover:bg-gray-700': props.variant === 'secondary',
      'border border-gray-300 bg-transparent hover:bg-gray-100 dark:border-gray-700 dark:hover:bg-gray-800': props.variant === 'outline',
      'hover:bg-gray-100 dark:hover:bg-gray-800 text-gray-700 dark:text-gray-300': props.variant === 'ghost',
      'bg-red-500 text-white hover:bg-red-600': props.variant === 'danger',

      'h-8 px-3 text-xs': props.size === 'sm',
      'h-10 px-4 py-2': props.size === 'md',
      'h-12 px-8 text-lg': props.size === 'lg',
    },
    props.class
  )
})
</script>

<template>
  <button :type="type" :disabled="disabled || loading" :class="buttonClass">
    <svg v-if="loading" class="mr-2 h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
      <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
    </svg>
    <slot></slot>
  </button>
</template>

