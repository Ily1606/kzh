<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'

const route = useRoute()
const title = computed(() => route.meta.title || 'Welcome')

const subtitle = computed(() =>
  route.name === 'register'
    ? 'Create an account to publish your plugins.'
    : 'Sign in to publish and manage your plugins.'
)
</script>

<template>
  <div class="relative flex min-h-screen overflow-hidden bg-muted/30">
    <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_top_left,_var(--color-primary-100),_transparent_42%)] dark:bg-[radial-gradient(circle_at_top_left,_#3b0764,_transparent_40%)]" />
    <aside class="relative hidden w-[44%] flex-col justify-between border-r bg-primary p-12 text-primary-foreground lg:flex">
      <RouterLink to="/" class="text-2xl font-bold tracking-tight">DSH</RouterLink>
      <div>
        <p class="max-w-sm text-3xl font-semibold leading-tight tracking-tight">Ship your plugins to every DeepSeek Harness.</p>
        <code class="mt-6 inline-block rounded-lg bg-black/20 px-3 py-2 font-mono text-xs text-primary-100">npx dsh add @dsh/hello-world</code>
      </div>
    </aside>
    <main class="relative flex min-h-screen flex-1 items-center justify-center p-5 sm:p-8">
      <div class="w-full max-w-sm">
        <RouterLink to="/" class="mb-10 block text-center text-2xl font-bold tracking-tight text-primary lg:hidden">DSH</RouterLink>
        <div class="mb-7">
          <h1 class="text-2xl font-semibold tracking-tight text-foreground">{{ title }}</h1>
          <p class="mt-1.5 text-sm text-muted-foreground">{{ subtitle }}</p>
        </div>
        <slot />
      </div>
    </main>
  </div>
</template>
