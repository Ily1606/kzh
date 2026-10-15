<script setup lang="ts">
import { useAuth } from '@/composables/useAuth'
import AuthLayout from '@/layouts/AuthLayout.vue'
import DefaultLayout from '@/layouts/DefaultLayout.vue'
import { computed, nextTick, onMounted, watch } from 'vue'
import { RouterView, useRoute } from 'vue-router'

const route = useRoute()
const auth = useAuth()

onMounted(() => {
  auth.fetchUser()
})

const layout = computed(() => {
  if (route.meta.layout === 'auth') return AuthLayout
  return DefaultLayout
})

/**
 * Scrolling while the outgoing page is still fading would show it slide up, so
 * the reset waits for the swap to finish. `instant` because `html` scrolls
 * smoothly: an animated reset is the very thing being hidden.
 */
function scrollToTop() {
  window.scrollTo({ top: 0, behavior: 'instant' })
}

// Changing layout tears the transition down, so there is no after-leave to wait for.
watch(layout, async () => {
  await nextTick()
  scrollToTop()
})
</script>

<template>
  <component :is="layout">
    <RouterView v-slot="{ Component }">
      <Transition name="page" mode="out-in" @after-leave="scrollToTop">
        <component :is="Component" :key="route.path" />
      </Transition>
    </RouterView>
  </component>
</template>

<style scoped>
.page-enter-active,
.page-leave-active {
  transition: opacity 0.25s ease, transform 0.25s ease;
}
.page-enter-from {
  opacity: 0;
  transform: translateY(8px);
}
.page-leave-to {
  opacity: 0;
  transform: translateY(-4px);
}
</style>
