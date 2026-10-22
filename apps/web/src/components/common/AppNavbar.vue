<script setup lang="ts">
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { useAuth } from '@/composables/useAuth'
import {
  LogOut,
  UserRound,
  Menu,
  X,
  Package,
  Layers,
  UploadCloud,
  ChevronRight,
} from "@lucide/vue"
import { Button } from "@/components/ui/button"
import ThemeToggle from "@/components/common/ThemeToggle.vue"

const auth = useAuth()
const router = useRouter()
const route = useRoute()

const redirectQuery = computed(() => {
  const currentPath = route?.fullPath || ''
  const isGuestOnly = Boolean(route?.meta?.guestOnly)

  return !isGuestOnly && currentPath && currentPath !== '/'
    ? { redirect: currentPath }
    : undefined
})

const isOpen = ref(false)

async function handleLogout() {
  await router.push('/login')
  await auth.logout()
}

// Close drawer when route changes
watch(
  () => route.fullPath,
  () => {
    isOpen.value = false
  }
)

// Prevent background body scroll when drawer is open
watch(isOpen, (open) => {
  if (typeof document !== 'undefined') {
    document.body.style.overflow = open ? 'hidden' : ''
  }
})

const onKeydown = (e: KeyboardEvent) => {
  if (e.key === 'Escape' && isOpen.value) {
    isOpen.value = false
  }
}

let mql: MediaQueryList | null = null

const handleMediaChange = (e: { matches: boolean }) => {
  if (e.matches && isOpen.value) {
    isOpen.value = false
  }
}

onMounted(() => {
  if (typeof window !== 'undefined') {
    window.addEventListener('keydown', onKeydown)
    if (window.matchMedia) {
      mql = window.matchMedia('(min-width: 768px)')
      if (mql.matches && isOpen.value) {
        isOpen.value = false
      }
      if (mql.addEventListener) {
        mql.addEventListener('change', handleMediaChange)
      } else if (mql.addListener) {
        mql.addListener(handleMediaChange)
      }
    }
  }
})

onUnmounted(() => {
  if (typeof window !== 'undefined') {
    window.removeEventListener('keydown', onKeydown)
    if (mql) {
      if (mql.removeEventListener) {
        mql.removeEventListener('change', handleMediaChange)
      } else if (mql.removeListener) {
        mql.removeListener(handleMediaChange)
      }
      mql = null
    }
    document.body.style.overflow = ''
  }
})
</script>

<template>
  <nav class="sticky top-0 z-40 w-full border-b bg-background/80 backdrop-blur-xl">
    <div class="mx-auto flex h-16 max-w-6xl items-center justify-between px-4 sm:px-6 lg:px-8">
      <!-- Left side: Mobile Hamburger + Brand Logo + Desktop Nav -->
      <div class="flex items-center gap-2 sm:gap-8">
        <!-- Mobile Drawer Hamburger Toggle Button -->
        <Button
          variant="ghost"
          size="icon"
          class="md:hidden size-9 -ml-2"
          :aria-expanded="isOpen"
          aria-label="Toggle navigation menu"
          @click="isOpen = !isOpen"
        >
          <Menu v-if="!isOpen" class="size-5 text-foreground" />
          <X v-else class="size-5 text-foreground" />
        </Button>

        <RouterLink to="/" class="flex items-center gap-2 font-semibold tracking-tight text-foreground">
          <span class="grid size-8 place-items-center rounded-lg bg-primary text-sm font-bold text-primary-foreground shadow-sm shadow-primary/30">D</span>
          <span>DSH</span>
        </RouterLink>
        <div class="hidden items-center gap-6 md:flex">
          <RouterLink to="/plugins" class="nav-link">Plugins</RouterLink>
          <RouterLink to="/trending" class="nav-link">Trending</RouterLink>
          <RouterLink v-if="auth.isAuthenticated" to="/resources" class="nav-link">Resources</RouterLink>
        </div>
      </div>

      <!-- Right side: ThemeToggle + Profile/Auth controls -->
      <div class="flex items-center gap-2 sm:gap-3">
        <ThemeToggle />

        <!-- Authenticated controls -->
        <template v-if="auth.isAuthenticated">
          <!-- Desktop Profile Pill -->
          <RouterLink
            to="/settings"
            class="hidden items-center gap-2 rounded-full border bg-muted/50 p-1 text-sm transition-colors hover:bg-muted sm:flex sm:py-1 sm:pl-1 sm:pr-3"
            aria-label="Profile"
          >
            <img v-if="auth.user?.avatarLink" :src="auth.user.avatarLink" class="size-7 rounded-full object-cover border border-border" alt="Avatar" />
            <span v-else class="grid size-7 place-items-center rounded-full bg-primary/10 text-primary"><UserRound class="size-3.5" /></span>
            <span class="text-muted-foreground">Hello, <span class="font-medium text-foreground">{{ auth.user?.name }}</span></span>
          </RouterLink>

          <!-- Mobile User Avatar Link -->
          <RouterLink
            to="/settings"
            class="flex sm:hidden items-center rounded-full transition-transform active:scale-95"
            aria-label="Mobile Profile"
          >
            <img
              v-if="auth.user?.avatarLink"
              :src="auth.user.avatarLink"
              class="size-8 rounded-full object-cover border border-border"
              alt="Avatar"
            />
            <span
              v-else
              class="grid size-8 place-items-center rounded-full bg-primary/10 text-primary border border-border"
            >
              <UserRound class="size-4" />
            </span>
          </RouterLink>

          <Button variant="outline" size="sm" class="hidden sm:inline-flex" @click="handleLogout">
            <LogOut class="size-4" /> <span>Logout</span>
          </Button>
        </template>

        <!-- Unauthenticated controls -->
        <template v-else>
          <!-- Desktop Log in button -->
          <Button as-child variant="ghost" size="sm" class="hidden sm:inline-flex">
            <RouterLink :to="{ path: '/login', query: redirectQuery }">Log in</RouterLink>
          </Button>

          <!-- Mobile Log in button -->
          <Button as-child variant="outline" size="sm" class="sm:hidden h-8 px-2.5 text-xs font-medium">
            <RouterLink :to="{ path: '/login', query: redirectQuery }">Log in</RouterLink>
          </Button>

          <!-- Desktop Get started button -->
          <Button as-child size="sm" class="hidden sm:inline-flex">
            <RouterLink :to="{ path: '/register', query: redirectQuery }">Get started</RouterLink>
          </Button>
        </template>
      </div>
    </div>

    <!-- Mobile Drawer Overlay & Panel -->
    <Teleport to="body">
      <!-- Backdrop Overlay -->
      <Transition
        enter-active-class="transition-opacity duration-200 ease-out"
        enter-from-class="opacity-0"
        leave-active-class="transition-opacity duration-150 ease-in"
        leave-to-class="opacity-0"
      >
        <div
          v-if="isOpen"
          class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs md:hidden"
          @click="isOpen = false"
        />
      </Transition>

      <!-- Drawer Panel -->
      <Transition
        enter-active-class="transition-transform duration-300 ease-out"
        enter-from-class="-translate-x-full"
        leave-active-class="transition-transform duration-200 ease-in"
        leave-to-class="-translate-x-full"
      >
        <div
          v-if="isOpen"
          class="fixed inset-y-0 left-0 z-50 flex w-full max-w-xs flex-col justify-between border-r border-border bg-background p-6 shadow-2xl md:hidden"
        >
          <!-- Top Section: Header & Links -->
          <div class="space-y-6">
            <!-- Drawer Header -->
            <div class="flex items-center justify-between pb-4 border-b border-border">
              <RouterLink to="/" class="flex items-center gap-2 font-semibold tracking-tight text-foreground" @click="isOpen = false">
                <span class="grid size-8 place-items-center rounded-lg bg-primary text-sm font-bold text-primary-foreground shadow-sm shadow-primary/30">D</span>
                <span>DSH</span>
              </RouterLink>
              <Button variant="ghost" size="icon" class="size-8" @click="isOpen = false" aria-label="Close menu">
                <X class="size-4" />
              </Button>
            </div>

            <!-- User Profile Card (if authenticated) -->
            <div v-if="auth.isAuthenticated">
              <RouterLink
                to="/settings"
                class="flex items-center gap-3 rounded-xl border border-border bg-muted/40 p-3 transition-colors hover:bg-muted"
                @click="isOpen = false"
              >
                <img
                  v-if="auth.user?.avatarLink"
                  :src="auth.user.avatarLink"
                  class="size-10 rounded-full object-cover border border-border shrink-0"
                  alt="Avatar"
                />
                <span
                  v-else
                  class="grid size-10 place-items-center rounded-full bg-primary/10 text-primary shrink-0"
                >
                  <UserRound class="size-5" />
                </span>
                <div class="flex flex-col min-w-0 flex-1">
                  <span class="text-xs text-muted-foreground">My Profile</span>
                  <span class="font-medium text-sm truncate text-foreground">{{ auth.user?.name }}</span>
                </div>
                <ChevronRight class="size-4 text-muted-foreground shrink-0" />
              </RouterLink>
            </div>

            <!-- Navigation Links -->
            <div class="space-y-1">
              <RouterLink
                to="/plugins"
                class="mobile-nav-link"
                @click="isOpen = false"
              >
                <Package class="size-4" />
                <span>Plugins</span>
              </RouterLink>

              <template v-if="auth.isAuthenticated">
                <RouterLink
                  to="/resources"
                  class="mobile-nav-link"
                  @click="isOpen = false"
                >
                  <Layers class="size-4" />
                  <span>Resources</span>
                </RouterLink>

                <RouterLink
                  to="/publish"
                  class="mobile-nav-link"
                  @click="isOpen = false"
                >
                  <UploadCloud class="size-4" />
                  <span>Publish Plugin</span>
                </RouterLink>
              </template>
            </div>
          </div>

          <!-- Bottom Section: Actions -->
          <div class="pt-6 border-t border-border space-y-3">
            <template v-if="auth.isAuthenticated">
              <Button
                variant="outline"
                class="w-full justify-center gap-2 text-destructive border-destructive/30 hover:bg-destructive/10 hover:text-destructive"
                @click="handleLogout"
              >
                <LogOut class="size-4" />
                <span>Log out</span>
              </Button>
            </template>
            <template v-else>
              <Button variant="outline" as-child class="w-full">
                <RouterLink :to="{ path: '/login', query: redirectQuery }" @click="isOpen = false">Log in</RouterLink>
              </Button>
              <Button as-child class="w-full">
                <RouterLink :to="{ path: '/register', query: redirectQuery }" @click="isOpen = false">Get started</RouterLink>
              </Button>
            </template>
          </div>
        </div>
      </Transition>
    </Teleport>
  </nav>
</template>

<style scoped>
/* Vue Router puts router-link-active on the link matching the current route. */
.nav-link {
  font-size: 0.875rem;
  line-height: 1.25rem;
  font-weight: 500;
  color: var(--muted-foreground);
  transition: color 0.15s;
}

.nav-link:hover {
  color: var(--foreground);
}

.nav-link.router-link-active {
  color: var(--foreground);
  font-weight: 600;
}

.mobile-nav-link {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  border-radius: 0.5rem;
  padding: 0.625rem 0.75rem;
  font-size: 0.875rem;
  font-weight: 500;
  color: var(--muted-foreground);
  transition: all 0.15s;
}

.mobile-nav-link:hover {
  background-color: var(--muted);
  color: var(--foreground);
}

.mobile-nav-link.router-link-active {
  background-color: color-mix(in srgb, var(--primary) 10%, transparent);
  color: var(--primary);
  font-weight: 600;
}
</style>
