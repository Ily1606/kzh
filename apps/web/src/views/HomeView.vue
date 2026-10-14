<script setup lang="ts">
import { computed, onMounted, ref } from "vue";
import type { HealthResponse } from "@dsh/shared";
import {
  ArrowRight,
  Check,
  CheckCircle2,
  CircleAlert,
  Copy,
  GitBranch,
  LoaderCircle,
  PackageOpen,
  ShieldCheck,
  Sparkles,
  Star,
  UploadCloud,
  Zap,
} from "@lucide/vue";
import { Motion } from "motion-v";
import { getHealth } from "@/api/generated/endpoints";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import PluginCard from "@/components/pages/plugin/PluginCard.vue";
import Reveal from "@/components/common/Reveal.vue";
import SectionHeading from "@/components/common/SectionHeading.vue";
import SiteFooter from "@/components/common/SiteFooter.vue";
import { usePluginsStore } from "@/stores";

const health = ref<HealthResponse | null>(null);
const error = ref<string | null>(null);
const copied = ref(false);
const installCommand = "npx dsh add @dsh/hello-world";
const { v1Health } = getHealth();
const store = usePluginsStore();

onMounted(async () => {
  // Independent of the health probe: a down API is already reported in the
  // registry status card, and must not stop the trending list from loading.
  store.fetchTrending();

  try {
    const healthRes = await v1Health();
    health.value = healthRes.data as unknown as HealthResponse;
  } catch (e) {
    error.value = e instanceof Error ? e.message : "Unknown error";
  }
});

async function copyInstallCommand() {
  try {
    await navigator.clipboard.writeText(installCommand);
    copied.value = true;
    window.setTimeout(() => (copied.value = false), 1800);
  } catch {
    copied.value = false;
  }
}

const headlineTop = ["Publish", "plugins."];

/**
 * Counts come off the trending list already on screen, so they never cost an
 * extra request. They are optional on the wire — the trending payload is
 * untyped — hence the `?? 0` on every field.
 */
const stats = computed(() => {
  const plugins = store.trendingPlugins;
  const sum = (key: "star_count" | "view_count") =>
    plugins.reduce((total, plugin) => total + (plugin[key] ?? 0), 0);

  return [
    { value: store.trending.total, label: "Trending packages" },
    { value: sum("star_count"), label: "Stars on the top list" },
    { value: sum("view_count"), label: "Views on the top list" },
  ];
});

const features = [
  {
    icon: ShieldCheck,
    title: "Reviewed before it ships",
    description:
      "Every submission is scanned for a valid manifest before it reaches the catalogue.",
  },
  {
    icon: Zap,
    title: "One command to install",
    description:
      "Copy the npx line and the plugin lands in your harness. No config to hand-write.",
  },
  {
    icon: GitBranch,
    title: "Versioned releases",
    description:
      "Publish an update without breaking the installs that already depend on you.",
  },
  {
    icon: Star,
    title: "Rated by developers",
    description:
      "Star the plugins that saved you an afternoon, so the next person finds them first.",
  },
];

const steps = [
  {
    title: "Publish",
    description:
      "Package an extension for DeepSeek Harness and share its manifest with the registry.",
  },
  {
    title: "Discover",
    description:
      "Developers search a focused catalogue with versions, authors and useful metadata.",
  },
  {
    title: "Install",
    description:
      "Bring a plugin into a harness with a familiar npx command and get back to building.",
  },
];
</script>

<template>
  <div class="relative isolate overflow-hidden pt-12 sm:pt-20">
    <!-- Hero backdrop: the aurora pans slowly, the dot grid anchors it so the
         gradient has something to sit on rather than floating in flat space. -->
    <div class="pointer-events-none absolute inset-x-0 top-0 -z-10 h-150">
      <div class="aurora absolute inset-0 animate-aurora-pan" />
      <div
        class="grid-dots absolute inset-0 opacity-40 [mask-image:radial-gradient(ellipse_70%_55%_at_50%_0%,black,transparent)]"
      />
      <div
        class="absolute -left-20 top-40 size-72 animate-float rounded-full bg-primary/25 blur-3xl"
      />
      <div
        class="absolute -right-16 top-24 size-64 animate-float-slow rounded-full bg-fuchsia-500/20 blur-3xl"
      />
    </div>

    <section class="mx-auto max-w-4xl px-1 text-center">
      <div
        class="glass inline-flex items-center gap-2 rounded-full px-3 py-1 text-sm font-medium text-muted-foreground"
      >
        <span class="relative grid size-4 place-items-center">
          <span class="absolute inset-0 animate-pulse-ring rounded-full bg-primary/40" />
          <PackageOpen class="size-3.5 text-primary" />
        </span>
        DeepSeek Harness plugin registry
      </div>

      <h1
        class="mt-6 text-4xl font-bold leading-[1.05] tracking-tight sm:text-6xl"
      >
        <span class="block">
          <Motion
            v-for="(word, index) in headlineTop"
            :key="word"
            class="mr-[0.24em] inline-block"
            :initial="{ opacity: 0, y: 26 }"
            :animate="{ opacity: 1, y: 0 }"
            :transition="{ delay: 0.1 + index * 0.09, duration: 0.6, ease: [0.22, 1, 0.36, 1] }"
            >{{ word }}</Motion
          >
        </span>
        <Motion
          class="gradient-text block"
          :initial="{ opacity: 0, y: 26 }"
          :animate="{ opacity: 1, y: 0 }"
          :transition="{ delay: 0.28, duration: 0.6, ease: [0.22, 1, 0.36, 1] }"
          >Ship better harnesses.</Motion
        >
      </h1>

      <p
        class="mx-auto mt-6 max-w-2xl text-pretty text-base leading-7 text-muted-foreground sm:text-lg"
      >
        DSH is the home for plugins that extend DeepSeek Harness — discover
        community tools, publish your own package, and install it with one
        command.
      </p>
      <div class="mt-8 flex flex-wrap justify-center gap-3">
        <Button as-child size="lg"
          ><RouterLink to="/plugins"
            >Browse all plugins <ArrowRight /></RouterLink
        ></Button>
        <Button as-child size="lg" variant="outline"
          ><RouterLink to="/publish"
            ><UploadCloud /> Publish a plugin</RouterLink
          ></Button
        >
      </div>
    </section>

    <section class="mx-auto mt-12 max-w-3xl px-1">
      <Motion
        :initial="{ opacity: 0, y: 28 }"
        :animate="{ opacity: 1, y: 0 }"
        :transition="{ delay: 0.45, duration: 0.7, ease: [0.22, 1, 0.36, 1] }"
      >
        <Card variant="glass" class="overflow-hidden py-0 shadow-2xl shadow-primary/10">
          <div class="flex items-center gap-2 border-b bg-muted/40 px-4 py-3">
            <span class="flex gap-1.5">
              <span class="size-2.5 rounded-full bg-red-400/70" />
              <span class="size-2.5 rounded-full bg-amber-400/70" />
              <span class="size-2.5 rounded-full bg-emerald-400/70" />
            </span>
            <span class="ml-1 text-sm text-muted-foreground"
              >Install directly from the registry</span
            >
          </div>
          <div class="flex items-center gap-3 p-3 sm:p-4">
            <code
              class="min-w-0 flex-1 truncate rounded-md bg-foreground px-3 py-2.5 font-mono text-sm text-background"
              ><span class="text-primary">$ </span
              ><span class="animate-typing">{{ installCommand }}</span
              ><span class="animate-blink text-primary">▍</span>
            </code>
            <Button
              size="icon"
              variant="outline"
              aria-label="Copy install command"
              @click="copyInstallCommand"
              ><Check v-if="copied" class="text-primary" /><Copy v-else
            /></Button>
          </div>
        </Card>
      </Motion>
    </section>

    <section
      v-if="stats.some((stat) => stat.value > 0)"
      class="mx-auto mt-14 max-w-3xl px-1"
    >
      <dl class="glass grid grid-cols-3 divide-x rounded-2xl px-2 py-5">
        <div
          v-for="stat in stats"
          :key="stat.label"
          class="px-3 text-center first:pl-0 last:pr-0"
        >
          <dd class="text-2xl font-semibold tracking-tight sm:text-3xl">
            {{ stat.value.toLocaleString() }}
          </dd>
          <dt class="mt-1 text-xs text-muted-foreground">{{ stat.label }}</dt>
        </div>
      </dl>
    </section>

    <section
      id="plugins"
      class="mx-auto mt-24 max-w-6xl scroll-mt-24 px-4 sm:px-6"
    >
      <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <SectionHeading
          eyebrow="Trending plugins"
          title="Build on what the community ships."
          description="Discover the most popular plugins for DeepSeek Harness."
        />
        <div class="flex items-center gap-3">
          <Button variant="outline" as-child
            ><RouterLink to="/plugins"
              >View all plugins <ArrowRight /></RouterLink
          ></Button>
        </div>
      </div>

      <!-- Trending Plugins Container: 3-column grid on desktop so all 3 cards (Top 1, 2, 3) are completely visible side-by-side without horizontal scroll; scrollable on smaller mobile screens -->
      <div
        v-if="store.trendingPlugins.length > 0"
        class="mt-8 -mx-4 px-4 sm:mx-0 sm:px-0 flex md:grid md:grid-cols-3 overflow-x-auto md:overflow-visible gap-4 sm:gap-5 pb-4 md:pb-0 pt-2.5 [&::-webkit-scrollbar]:h-1.5 [&::-webkit-scrollbar-track]:rounded-full [&::-webkit-scrollbar-track]:bg-primary/5 [&::-webkit-scrollbar-thumb]:rounded-full [&::-webkit-scrollbar-thumb]:bg-primary/25 hover:[&::-webkit-scrollbar-thumb]:bg-primary/40 transition-colors"
      >
        <PluginCard
          v-for="(plugin, index) in store.trendingPlugins.slice(0, 3)"
          :key="plugin.id"
          :plugin="plugin"
          :rank="index + 1"
          class="min-w-65 sm:min-w-70 md:min-w-0 md:max-w-none md:w-full shrink-0 md:shrink!"
        />
      </div>
      <div
        v-else
        class="mt-8 rounded-2xl border border-dashed border-border/80 p-8 text-center text-sm text-muted-foreground"
      >
        No trending plugins available at the moment.
      </div>
    </section>

    <section class="mx-auto mt-24 max-w-6xl px-4 sm:px-6">
      <SectionHeading
        align="center"
        eyebrow="Why DSH"
        title="Everything a registry should do."
        description="A place to publish is only useful if people can find, trust and install what you publish."
      />
      <div class="mt-10 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <Reveal
          v-for="(feature, index) in features"
          :key="feature.title"
          :delay="index * 0.08"
          :y="20"
        >
          <div
            class="glass h-full rounded-2xl p-5 transition-transform duration-300 hover:-translate-y-1"
          >
            <span
              class="grid size-10 place-items-center rounded-xl bg-primary/10 text-primary"
            >
              <component :is="feature.icon" class="size-5" />
            </span>
            <h3 class="mt-4 font-semibold">{{ feature.title }}</h3>
            <p class="mt-2 text-sm leading-6 text-muted-foreground">
              {{ feature.description }}
            </p>
          </div>
        </Reveal>
      </div>
    </section>

    <section
      id="workflow"
      class="mx-auto mt-24 max-w-6xl scroll-mt-24 px-4 sm:px-6"
    >
      <SectionHeading
        align="center"
        eyebrow="A registry, not just a showcase"
        title="From local idea to an installable plugin."
      />
      <div class="relative mt-10 grid gap-8 md:grid-cols-3">
        <!-- The connector runs behind the numbered steps so the three read as
             one path rather than three unrelated boxes. -->
        <div
          class="pointer-events-none absolute left-[12.5%] right-[12.5%] top-5 hidden h-px bg-gradient-to-r from-transparent via-primary/35 to-transparent md:block"
        />
        <Reveal
          v-for="(step, index) in steps"
          :key="step.title"
          :delay="index * 0.12"
          :y="20"
        >
          <div>
            <span
              class="relative grid size-10 place-items-center rounded-full bg-primary text-sm font-semibold text-primary-foreground shadow-lg shadow-primary/25"
              >{{ index + 1 }}</span
            >
            <h3 class="mt-4 text-lg font-semibold">{{ step.title }}</h3>
            <p class="mt-2 max-w-xs text-sm leading-6 text-muted-foreground">
              {{ step.description }}
            </p>
          </div>
        </Reveal>
      </div>
    </section>

    <section class="mx-auto mt-24 max-w-6xl px-4 sm:px-6">
      <Reveal :y="20">
        <div
          class="glass-strong relative overflow-hidden rounded-3xl px-6 py-12 text-center"
        >
          <div
            class="pointer-events-none absolute inset-0 -z-10 bg-gradient-to-br from-primary/15 via-transparent to-fuchsia-500/10"
          />
          <Sparkles class="mx-auto size-8 text-primary" />
          <h2 class="mt-4 text-balance text-3xl font-semibold tracking-tight">
            Ship your plugin to the community.
          </h2>
          <p class="mx-auto mt-3 max-w-xl text-muted-foreground">
            Publish a manifest, get a versioned package, and let every DeepSeek
            Harness user install it in one command.
          </p>
          <div class="mt-7 flex flex-wrap justify-center gap-3">
            <Button as-child size="lg"
              ><RouterLink to="/publish"
                ><UploadCloud /> Publish a plugin</RouterLink
              ></Button
            >
            <Button as-child size="lg" variant="outline"
              ><RouterLink to="/resources"
                >Your resources <ArrowRight /></RouterLink
              ></Button
            >
          </div>
        </div>
      </Reveal>
    </section>

    <section class="mx-auto mt-12 max-w-6xl px-4 sm:px-6">
      <Card class="border-primary/15 bg-card/90 py-0"
        ><div
          class="flex flex-col gap-4 p-5 sm:flex-row sm:items-center sm:justify-between"
        >
          <div class="flex items-center gap-3">
            <span
              class="grid size-10 place-items-center rounded-full bg-primary/10 text-primary"
              ><CheckCircle2 v-if="health" class="size-5" /><CircleAlert
                v-else-if="error"
                class="size-5 text-destructive" /><LoaderCircle
                v-else
                class="size-5 animate-spin"
            /></span>
            <div>
              <p class="font-medium">Registry status</p>
              <p class="text-sm text-muted-foreground">
                <span v-if="health">API online — {{ health.status }}</span
                ><span v-else-if="error">API unavailable — {{ error }}</span
                ><span v-else>Checking API connection…</span>
              </p>
            </div>
          </div>
          <span class="font-mono text-xs text-muted-foreground"
            >/api/v1/health</span
          >
        </div></Card
      >
    </section>

    <!-- Only the landing page carries the footer: on the inner pages it reads
         as padding the user has to scroll past to reach the next action. -->
    <SiteFooter />
  </div>
</template>
