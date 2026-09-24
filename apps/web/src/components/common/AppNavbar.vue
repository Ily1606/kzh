<script setup lang="ts">
import { RouterLink } from 'vue-router'
import { useAuth } from '@/composables/useAuth'
import { useRouter } from 'vue-router'
import { LogOut, UserRound } from "lucide-vue-next"
import { Button } from "@/components/ui/button"

const auth = useAuth()
const router = useRouter()

async function handleLogout() {
  await auth.logout()
  router.push('/login')
}
</script>

<template>
  <nav class="sticky top-0 z-40 w-full border-b bg-background/80 backdrop-blur-xl">
    <div class="mx-auto flex h-16 max-w-6xl items-center justify-between px-4 sm:px-6 lg:px-8">
      <div class="flex items-center gap-8">
        <RouterLink to="/" class="flex items-center gap-2 font-semibold tracking-tight text-foreground">
          <span class="grid size-8 place-items-center rounded-lg bg-primary text-sm font-bold text-primary-foreground shadow-sm shadow-primary/30">D</span>
          <span>DSH</span>
        </RouterLink>
        <RouterLink to="/" class="hidden text-sm font-medium text-muted-foreground transition-colors hover:text-foreground md:inline-flex">Registry</RouterLink>
      </div>
      <div class="flex items-center gap-2 sm:gap-3">
        <template v-if="auth.isAuthenticated">
          <div class="hidden items-center gap-2 rounded-full border bg-muted/50 py-1 pl-1 pr-3 text-sm sm:flex">
            <span class="grid size-7 place-items-center rounded-full bg-primary/10 text-primary"><UserRound class="size-3.5" /></span>
            <span class="text-muted-foreground">Hello, <span class="font-medium text-foreground">{{ auth.user?.name }}</span></span>
          </div>
          <span class="grid size-8 place-items-center rounded-full bg-primary/10 text-primary sm:hidden"><UserRound class="size-4" /></span>
          <Button variant="outline" size="sm" @click="handleLogout"><LogOut /> <span class="hidden sm:inline">Logout</span></Button>
        </template>
        <template v-else>
          <Button as-child size="sm"><RouterLink to="/register">Get started</RouterLink></Button>
        </template>
      </div>
    </div>
  </nav>
</template>
