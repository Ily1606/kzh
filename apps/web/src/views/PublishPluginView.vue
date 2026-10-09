<script setup lang="ts">
import { ref } from 'vue'
import { useRouter, RouterLink } from 'vue-router'
import { useForm } from 'vee-validate'
import { toTypedSchema } from '@vee-validate/zod'
import * as z from 'zod'
import { getPlugin } from '@/api/generated/endpoints'
import { SubmitPluginRequestLicense } from '@/api/generated/model'
import type { SubmitPluginRequest } from '@/api/generated/model'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import {
  FormControl,
  FormField,
  FormItem,
  FormLabel,
  FormMessage,
} from '@/components/ui/form'
import {
  ChevronLeft,
  ChevronRight,
  CheckCircle2,
  ArrowRight,
  PackageCheck,
  ShieldCheck,
  Terminal,
  Sparkles
} from 'lucide-vue-next'

const router = useRouter()
const { pluginStore } = getPlugin()

const error = ref('')
const isSuccess = ref(false)

// Zod validation schema matching backend rules
const licenseValues = Object.values(SubmitPluginRequestLicense) as [string, ...string[]]

const submitPluginSchema = toTypedSchema(
  z.object({
    name: z
      .string({ required_error: 'Plugin name is required' })
      .trim()
      .min(1, 'Plugin name is required')
      .max(255, 'Plugin name must not exceed 255 characters'),
    title: z
      .string({ required_error: 'Display title is required' })
      .trim()
      .min(1, 'Display title is required')
      .max(255, 'Display title must not exceed 255 characters'),
    license: z.enum(licenseValues, {
      errorMap: () => ({ message: 'Please select a valid license' }),
    }),
    source_link: z
      .string({ required_error: 'Source link is required' })
      .trim()
      .min(1, 'Source link is required')
      .max(2048, 'Source link must not exceed 2048 characters')
      .url('Source link must be a valid URL')
      .refine((url) => url.startsWith('https://'), {
        message: 'Source link must start with https://',
      }),
  })
)

const { handleSubmit, setErrors, isSubmitting } = useForm({
  validationSchema: submitPluginSchema,
  initialValues: {
    name: '',
    title: '',
    license: SubmitPluginRequestLicense.MIT,
    source_link: '',
  },
})

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

const onSubmit = handleSubmit(async (values) => {
  error.value = ''
  isSuccess.value = false

  try {
    await pluginStore(values as SubmitPluginRequest)
    isSuccess.value = true
    setTimeout(() => {
      router.push('/plugins')
    }, 2400)
  } catch (err: any) {
    if (err?.response?.status === 422 && err?.response?.data?.errors) {
      const serverErrors: Record<string, string> = {}
      for (const [key, msgs] of Object.entries(err.response.data.errors as Record<string, string[]>)) {
        if (msgs && msgs[0]) {
          serverErrors[key] = msgs[0]
        }
      }
      setErrors(serverErrors)
    }
    error.value = err?.response?.data?.message || 'An error occurred while submitting the plugin.'
  }
})
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
              Your plugin has been submitted to the registry queue and will be reviewed by an administrator shortly. Redirecting to plugins list...
            </p>
            <div class="pt-2">
              <Button as-child variant="outline" class="rounded-full px-6">
                <RouterLink to="/plugins">View all plugins</RouterLink>
              </Button>
            </div>
          </div>

          <!-- Form Fields with VeeValidate & Zod Schema Validation -->
          <form v-else @submit="onSubmit" novalidate class="space-y-6">
            <!-- Plugin Name -->
            <FormField v-slot="{ componentField }: any" name="name">
              <FormItem class="space-y-2">
                <FormLabel class="text-sm font-medium">
                  Plugin name <span class="text-primary">*</span>
                </FormLabel>
                <FormControl>
                  <Input
                    placeholder="e.g. @dsh/guardrails"
                    class="h-11 rounded-xl bg-muted/40 border-border/60 focus-visible:bg-background transition-colors"
                    v-bind="componentField"
                  />
                </FormControl>
                <FormMessage />
                <p class="text-xs text-muted-foreground">The unique npm or scoped package identifier.</p>
              </FormItem>
            </FormField>

            <!-- Display Title -->
            <FormField v-slot="{ componentField }: any" name="title">
              <FormItem class="space-y-2">
                <FormLabel class="text-sm font-medium">
                  Display title <span class="text-primary">*</span>
                </FormLabel>
                <FormControl>
                  <Input
                    placeholder="e.g. Opinionated safety checks and policy hooks"
                    class="h-11 rounded-xl bg-muted/40 border-border/60 focus-visible:bg-background transition-colors"
                    v-bind="componentField"
                  />
                </FormControl>
                <FormMessage />
              </FormItem>
            </FormField>

            <!-- License & Source Link Grid -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
              <!-- License -->
              <FormField v-slot="{ componentField }: any" name="license">
                <FormItem class="space-y-2">
                  <FormLabel class="text-sm font-medium">
                    License <span class="text-primary">*</span>
                  </FormLabel>
                  <FormControl>
                    <select
                      class="flex h-11 w-full rounded-xl border border-border/60 bg-muted/40 px-3 py-2 text-sm text-foreground outline-none transition-colors focus-visible:bg-background focus-visible:border-ring focus-visible:ring-2 focus-visible:ring-ring/40 disabled:cursor-not-allowed disabled:opacity-50"
                      v-bind="componentField"
                    >
                      <option
                        v-for="lic in Object.values(SubmitPluginRequestLicense)"
                        :key="lic"
                        :value="lic"
                      >
                        {{ lic }}
                      </option>
                    </select>
                  </FormControl>
                  <FormMessage />
                </FormItem>
              </FormField>

              <!-- Source Link -->
              <FormField v-slot="{ componentField }: any" name="source_link">
                <FormItem class="space-y-2">
                  <FormLabel class="text-sm font-medium">
                    Source link <span class="text-primary">*</span>
                  </FormLabel>
                  <FormControl>
                    <Input
                      type="url"
                      placeholder="https://github.com/org/repo"
                      class="h-11 rounded-xl bg-muted/40 border-border/60 focus-visible:bg-background transition-colors"
                      v-bind="componentField"
                    />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              </FormField>
            </div>

            <!-- Error Banner -->
            <div v-if="error" class="p-3.5 rounded-xl border border-destructive/30 bg-destructive/10 text-destructive text-sm">
              {{ error }}
            </div>

            <!-- Submit Button (Pill style matching reference image) -->
            <div class="pt-2">
              <Button
                type="submit"
                size="lg"
                class="rounded-full px-8 py-3 h-12 text-sm font-semibold shadow-lg shadow-primary/20 hover:shadow-primary/30 transition-all cursor-pointer inline-flex items-center gap-2"
                :disabled="isSubmitting"
              >
                <span>{{ isSubmitting ? 'Submitting plugin...' : 'Publish plugin' }}</span>
                <ArrowRight class="size-4" />
              </Button>
            </div>
          </form>
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
                  :class="activeSlide === idx ? 'w-5 bg-primary' : 'bg-muted-foreground/30'"
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
