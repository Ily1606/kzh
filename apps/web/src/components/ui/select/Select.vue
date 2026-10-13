<script setup lang="ts">
import { SelectRoot, type SelectRootProps } from 'reka-ui'

defineOptions({ inheritAttrs: false })
defineProps<Omit<SelectRootProps, 'modelValue'>>()
// Named, because reka-ui's prop is `modelValue` and a bare defineModel() would
// bind `model`, which SelectRoot never sees.
const model = defineModel<SelectRootProps['modelValue']>('modelValue')
</script>

<template>
  <SelectRoot v-slot="slotProps" v-model="model" data-slot="select" v-bind="$attrs">
    <slot v-bind="slotProps" />
  </SelectRoot>
</template>