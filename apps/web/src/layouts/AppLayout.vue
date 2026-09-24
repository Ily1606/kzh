<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import DefaultLayout from './DefaultLayout.vue'
import AuthLayout from './AuthLayout.vue'

const route = useRoute()

const layout = computed(() => {
  if (route.meta.layout === 'auth') {
    return AuthLayout
  }
  return DefaultLayout
})
</script>

<template>
  <component :is="layout">
    <RouterView />
  </component>
</template>

