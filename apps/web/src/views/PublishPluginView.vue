<script setup lang="ts">
import { ref } from 'vue'
import { useRouter, RouterLink } from 'vue-router'
import { getPlugin } from '@/api/generated/endpoints'
import type { SubmitPluginRequest } from '@/api/generated/model'
import type { PluginFields } from '@/schemas/plugin.schema'
import { Button } from '@/components/ui/button'
import PluginFieldsForm from '@/components/pages/plugin/PluginFieldsForm.vue'
import {
  ChevronLeft,
  ChevronRight,
  CheckCircle2,
  PackageCheck,
  ShieldCheck,
  Terminal,
  Sparkles
} from 'lucide-vue-next'
import { emptyPluginFields, submitPluginSchema } from '@/schemas/plugin.schema'

const router = useRouter()
const { pluginStore } = getPlugin()

const error = ref('')
const isSuccess = ref(false)

const form = ref<InstanceType<typeof PluginFieldsForm> | null>(null)

// Carousel slides for the right-hand showcase card
const activeSlide = ref(0)
const slides = [
  {
    quote: "Publishing our harness guardrails on DSH took less than 2 minutes. The instant discovery by the agentic developer community drove 10x adoption.",
    author: "Alex Morgan",
    role: "Lead Agentic Architect",
    badge: "AutoHarness Lab"
  },
  {
    quote: "Automated manifest checks verify your schema, security permissions, and GitHub README within minutes of submission.",
    author: "DSH Verification Engine",
    role: "Automated Quality & Policy",
    badge: "Verified Registry"
  },
  {
    quote: "Once approved, your package is instantly installable in any harness workspace with 'npx dsh add <name>'. Zero manual setup.",
    author: "Global Registry Edge",
    role: "Fast Package Distribution",
    badge: "Instant Install"
  }
]

function nextSlide() {
  activeSlide.value = (activeSlide.value + 1) % slides.length
}

function prevSlide() {
  activeSlide.value = (activeSlide.value - 1 + slides.length) % slides.length
}

async function submitPlugin(values: PluginFields) {
  error.value = ''

  try {
    await pluginStore(values as SubmitPluginRequest)
  } catch (err: unknown) {
    const response = (err as { response?: { status?: number; data?: { message?: string; errors?: Record<string, string[]> } } }).response

    if (response?.status === 422 && response.data?.errors) {
      form.value?.showServerErrors(response.data.errors)
    }

    error.value = response?.data?.message || 'An error occurred while submitting the plugin.'

    throw err
  }
}

function onSubmitted() {
  isSuccess.value = true

  setTimeout(() => {
    router.push({ name: 'my-plugins', query: { status: 'pending' } })
  }, 2400)
}
</script>

<template>
  <div class="relative isolate overflow-hidden min-h-[calc(100vh-4rem)] py-10">

    <!-- Atmospheric Ambient Glows (Inspired by Arctiq dark aesthetic) -->
    <div class="pointer-events-none absolute right-0 bottom-0 -z-10 h-130 w-130 rounded-full bg-emerald-500/15 blur-[120px] dark:bg-emerald-500/20" />
    <div class="pointer-events-none absolute left-0 top-1/4 -z-10 h-95 w-95 rounded-full bg-primary/10 blur-[100px] dark:bg-primary/15" />

    <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
      <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 lg:gap-16 items-start">

        <!-- Left Column: Heading & Submit Form (7 cols) -->
        <div class="lg:col-span-7 space-y-8">
          <div>
            <h1 class="text-3xl sm:text-4xl font-bold tracking-tight">
              Publish your plugin
            </h1>
            <p class="mt-3 text-base sm:text-lg text-muted-foreground max-w-xl leading-relaxed">
              Share your package with the DeepSeek Harness community. Once reviewed, developers worldwide can install it with one command.
            </p>
          </div>

          <!-- Success State -->
          <div v-if="isSuccess" class="py-12 space-y-5 rounded-3xl border border-emerald-500/30 bg-emerald-500/5 p-8 text-center backdrop-blur-sm">
            <div class="inline-flex items-center justify-center size-16 rounded-full bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 border border-emerald-500/30 mx-auto">
              <CheckCircle2 class="size-8" />
            </div>
            <h3 class="text-2xl font-bold">Plugin Submitted Successfully!</h3>
            <p class="text-muted-foreground max-w-md mx-auto text-sm leading-relaxed">
              Your plugin has been submitted to the registry queue and will be reviewed by an administrator shortly. Redirecting to your resources...
            </p>
            <div class="pt-2">
              <Button as-child variant="outline" class="rounded-full px-6">
                <RouterLink to="/resources">View your resources</RouterLink>
              </Button>
            </div>
          </div>

          <!-- Form Fields with VeeValidate & Zod Schema Validation -->
          <PluginFieldsForm
            v-else
            ref="form"
            :schema="submitPluginSchema"
            :initial-values="emptyPluginFields"
            submit-label="Publish plugin"
            :submit="submitPlugin"
            @submitted="onSubmitted"
          />

          <!-- Error Banner -->
          <div v-if="error" class="p-3.5 rounded-xl border border-destructive/30 bg-destructive/10 text-destructive text-sm">
            {{ error }}
          </div>
        </div>

        <!-- Right Column: Stylized Floating Glassmorphism Showcase Card (5 cols) -->
        <div class="lg:col-span-5 relative lg:sticky lg:top-24">
          <div class="rounded-3xl border border-border/60 bg-card/70 backdrop-blur-xl p-8 sm:p-9 shadow-2xl h-95 sm:h-100 flex flex-col justify-between relative overflow-hidden">
            <!-- Subtle card inner glow -->
            <div class="pointer-events-none absolute -top-12 -right-12 size-36 bg-emerald-500/10 rounded-full blur-2xl" />

            <!-- Card Header: Brand & Navigation Arrows -->
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-2.5 font-semibold tracking-tight text-foreground">
                <span class="grid size-8 place-items-center rounded-xl bg-primary text-xs font-bold text-primary-foreground shadow-sm shadow-primary/30">
                  D
                </span>
                <span class="text-sm font-semibold">DSH Registry</span>
              </div>

              <!-- Carousel Controls (Previous / Next) -->
              <div class="flex items-center gap-1.5">
                <button
                  type="button"
                  @click="prevSlide"
                  class="size-8 rounded-full border border-border/60 bg-background/50 hover:bg-muted flex items-center justify-center text-muted-foreground hover:text-foreground transition-colors cursor-pointer"
                  aria-label="Previous quote"
                >
                  <ChevronLeft class="size-4" />
                </button>
                <button
                  type="button"
                  @click="nextSlide"
                  class="size-8 rounded-full border border-border/60 bg-background/50 hover:bg-muted flex items-center justify-center text-muted-foreground hover:text-foreground transition-colors cursor-pointer"
                  aria-label="Next quote"
                >
                  <ChevronRight class="size-4" />
                </button>
              </div>
            </div>

            <!-- Quote Text (Middle) with Fixed Safe Height and Smooth Fade Transition -->
            <div class="flex-1 flex flex-col justify-center my-2 overflow-hidden">
              <Transition name="fade" mode="out-in">
                <div :key="activeSlide" class="space-y-4">
                  <p class="text-base sm:text-lg leading-relaxed text-foreground/90 font-normal">
                    “{{ slides[activeSlide].quote }}”
                  </p>
                  <div>
                    <p class="text-sm font-semibold text-foreground">{{ slides[activeSlide].author }}</p>
                    <p class="text-xs text-muted-foreground">{{ slides[activeSlide].role }}</p>
                  </div>
                </div>
              </Transition>
            </div>

            <!-- Card Footer: Badge & Verification standards -->
            <div class="border-t border-border/50 pt-5 flex items-center justify-between">
              <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 text-xs font-semibold border border-emerald-500/20">
                <Sparkles class="size-3.5" />
                {{ slides[activeSlide].badge }}
              </span>

              <!-- Pagination Dots -->
              <div class="flex items-center gap-1.5">
                <span
                  v-for="(_, idx) in slides"
                  :key="idx"
                  class="size-1.5 rounded-full transition-all duration-300"
                  :class="idx === activeSlide ? 'w-5 bg-primary' : 'bg-muted-foreground/30'"
                />
              </div>
            </div>
          </div>

          <!-- Bottom Micro-checkpoints -->
          <div class="mt-6 px-3 flex flex-wrap items-center justify-between gap-3 text-xs text-muted-foreground">
            <span class="inline-flex items-center gap-1.5">
              <ShieldCheck class="size-3.5 text-emerald-500" /> Automated manifest scan
            </span>
            <span class="inline-flex items-center gap-1.5">
              <Terminal class="size-3.5 text-primary" /> Instant npx install
            </span>
            <span class="inline-flex items-center gap-1.5">
              <PackageCheck class="size-3.5 text-blue-500" /> Fast review queue
            </span>
          </div>
        </div>

      </div>
    </div>
  </div>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.25s ease, transform 0.25s ease;
}
.fade-enter-from {
  opacity: 0;
  transform: translateY(6px);
}
.fade-leave-to {
  opacity: 0;
  transform: translateY(-6px);
}
</style>
