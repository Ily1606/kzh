<script setup lang="ts">
import { PackageOpen } from "@lucide/vue";

const productLinks = [
  { to: "/plugins", label: "Browse plugins" },
  { to: "/publish", label: "Publish a plugin" },
  { to: "/resources", label: "Your resources" },
];

const accountLinks = [
  { to: "/login", label: "Sign in" },
  { to: "/register", label: "Create account" },
  { to: "/settings", label: "Profile settings" },
];
</script>

<template>
  <footer class="mt-20 border-t bg-muted/20">
    <div class="mx-auto max-w-6xl px-4 py-12 sm:px-6 lg:px-8">
      <div class="grid gap-10 md:grid-cols-[1.5fr_1fr_1fr]">
        <div>
          <RouterLink to="/" class="flex items-center gap-2 font-semibold tracking-tight">
            <span
              class="grid size-8 place-items-center rounded-lg bg-primary text-sm font-bold text-primary-foreground shadow-sm shadow-primary/30"
            >
              D
            </span>
            <span>DSH</span>
          </RouterLink>
          <p class="mt-4 max-w-xs text-sm leading-6 text-muted-foreground">
            The registry for DeepSeek Harness plugins. Discover community
            packages, publish your own, and install with one command.
          </p>
          <code
            class="mt-4 inline-block rounded-md border bg-background px-2.5 py-1.5 font-mono text-xs text-muted-foreground"
            >npx dsh add @dsh/hello-world</code
          >
        </div>

        <nav>
          <h3 class="text-sm font-semibold">Product</h3>
          <ul class="mt-4 space-y-2.5">
            <li v-for="link in productLinks" :key="link.to">
              <RouterLink
                :to="link.to"
                class="text-sm text-muted-foreground transition-colors hover:text-foreground"
                >{{ link.label }}</RouterLink
              >
            </li>
          </ul>
        </nav>

        <nav>
          <h3 class="text-sm font-semibold">Account</h3>
          <ul class="mt-4 space-y-2.5">
            <li v-for="link in accountLinks" :key="link.to">
              <RouterLink
                :to="link.to"
                class="text-sm text-muted-foreground transition-colors hover:text-foreground"
                >{{ link.label }}</RouterLink
              >
            </li>
          </ul>
        </nav>
      </div>

      <div
        class="mt-10 flex flex-col gap-3 border-t pt-6 text-xs text-muted-foreground sm:flex-row sm:items-center sm:justify-between"
      >
        <span class="inline-flex items-center gap-1.5">
          <PackageOpen class="size-3.5 text-primary" />
          DeepSeek Harness plugin registry
        </span>
        <span>Built for the community.</span>
      </div>
    </div>
  </footer>
</template>
