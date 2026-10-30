<script setup lang="ts">
import type { PluginResource } from '@/api/generated/model';
import BasePluginRow from '@/components/common/BasePluginRow.vue'

defineProps<{
  plugin: PluginResource
}>()
</script>

<template>
  <BasePluginRow :plugin="plugin" />
</template>
