<script setup lang="ts">
import { Motion } from 'motion-v'
import { Package, ShieldCheck, Zap } from '@lucide/vue'
import ThemeToggle from '@/components/common/ThemeToggle.vue'

/** Left-panel selling points, paired with the install command below them. */
const highlights = [
  { icon: Zap, text: 'Install any plugin with a single npx command.' },
  { icon: ShieldCheck, text: 'Every submission is reviewed before it ships.' },
  { icon: Package, text: 'Versioned releases that never break an install.' },
]
</script>

<template>
  <div class="relative flex min-h-screen overflow-hidden bg-background">
    <!-- Backdrop: the same aurora language as the landing page, so signing in
         never feels like leaving the product for a different site. -->
    <div class="pointer-events-none absolute inset-0 -z-10">
      <div class="aurora absolute inset-0 animate-aurora-pan" />
      <div
        class="grid-dots absolute inset-0 opacity-35 mask-[radial-gradient(ellipse_80%_60%_at_50%_0%,black,transparent)]"
      />
    </div>

    <aside
      class="relative hidden w-[44%] flex-col justify-between overflow-hidden border-r border-primary-foreground/15 bg-primary p-12 text-primary-foreground lg:flex"
    >
      <!-- A brighter sweep over the flat primary panel gives the sidebar depth
           without introducing a second brand colour. -->
      <div class="pointer-events-none absolute inset-0 -z-10">
        <div
          class="absolute -left-24 -top-24 size-96 rounded-full bg-white/15 blur-3xl animate-float-slow"
        />
        <div
          class="absolute -bottom-32 -right-16 size-80 rounded-full bg-fuchsia-400/25 blur-3xl animate-float"
        />
      </div>

      <RouterLink to="/" class="inline-flex items-center gap-2.5 text-2xl font-bold tracking-tight">
        <span
          class="grid size-9 place-items-center rounded-xl bg-white/15 text-lg font-bold backdrop-blur"
          >D</span
        >
        DSH
      </RouterLink>

      <div>
        <Motion
          :initial="{ opacity: 0, y: 20 }"
          :animate="{ opacity: 1, y: 0 }"
          :transition="{ duration: 0.6, ease: [0.22, 1, 0.36, 1] }"
        >
          <p class="max-w-sm text-3xl font-semibold leading-tight tracking-tight">
            Ship your plugins to every DeepSeek Harness.
          </p>
          <ul class="mt-8 space-y-3.5">
            <li
              v-for="highlight in highlights"
              :key="highlight.text"
              class="flex items-start gap-3 text-sm text-primary-foreground/85"
            >
              <span
                class="mt-0.5 grid size-6 shrink-0 place-items-center rounded-full bg-white/15"
              >
                <component :is="highlight.icon" class="size-3.5" />
              </span>
              {{ highlight.text }}
            </li>
          </ul>
          <code
            class="mt-8 inline-block rounded-lg bg-black/25 px-3 py-2 font-mono text-xs text-primary-100"
            >npx dsh add @dsh/hello-world</code
          >
        </Motion>
      </div>

      <p class="text-xs text-primary-foreground/60">
        The registry for DeepSeek Harness plugins.
      </p>
    </aside>

    <main class="relative flex min-h-screen flex-1 items-center justify-center p-5 sm:p-8">
      <div class="absolute right-5 top-5 lg:right-8 lg:top-8">
        <ThemeToggle />
      </div>

      <Motion
        class="w-full max-w-sm"
        :initial="{ opacity: 0, y: 18 }"
        :animate="{ opacity: 1, y: 0 }"
        :transition="{ delay: 0.08, duration: 0.55, ease: [0.22, 1, 0.36, 1] }"
      >
        <RouterLink
          to="/"
          class="mb-10 flex items-center justify-center gap-2 text-center text-2xl font-bold tracking-tight text-primary lg:hidden"
        >
          <span
            class="grid size-8 place-items-center rounded-lg bg-primary text-sm font-bold text-primary-foreground"
            >D</span
          >
          DSH
        </RouterLink>
        <slot />
      </Motion>
    </main>
  </div>
</template>
