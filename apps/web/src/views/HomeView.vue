<script setup lang="ts">
import { onMounted, ref } from "vue";
import type { HealthResponse } from "@dsh/shared";
import { ArrowRight, Check, CheckCircle2, CircleAlert, Copy, LoaderCircle, PackageOpen, Terminal, UploadCloud } from "lucide-vue-next";
import { getHealth, getPlugin } from "@/api/generated/endpoints";
import type { PluginResource } from "@/api/generated/model";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import PluginCard from "@/components/pages/plugin/PluginCard.vue";

const health = ref<HealthResponse | null>(null);
const error = ref<string | null>(null);
const copied = ref(false);
const installCommand = "npx dsh add @dsh/hello-world";
const { v1Health } = getHealth();
const { pluginTrending } = getPlugin();

// Trending plugins state
const trendingPlugins = ref<PluginResource[]>([]);

onMounted(async () => {
  try {
    const [healthRes, pluginsRes] = await Promise.all([
      v1Health(),
      pluginTrending()
    ]);
    health.value = healthRes.data as unknown as HealthResponse;
    if (Array.isArray(pluginsRes.data)) {
      trendingPlugins.value = pluginsRes.data as PluginResource[];
    } else if (pluginsRes.data?.plugins) {
      trendingPlugins.value = pluginsRes.data.plugins as PluginResource[];
    }
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
</script>

<template>
  <div class="relative isolate overflow-hidden pb-20 pt-12 sm:pt-20">
    <div class="pointer-events-none absolute inset-x-0 top-0 -z-10 h-136 bg-[radial-gradient(ellipse_at_top,var(--color-primary-100),transparent_64%)] dark:bg-[radial-gradient(ellipse_at_top,#3b0764,transparent_56%)]" />
    <section class="mx-auto max-w-4xl px-1 text-center">
      <div class="inline-flex items-center gap-2 rounded-full border bg-background/80 px-3 py-1 text-sm font-medium text-muted-foreground shadow-sm backdrop-blur"><PackageOpen class="size-4 text-primary" /> DeepSeek Harness plugin registry</div>
      <h1 class="mt-6 text-balance text-4xl font-bold tracking-tight sm:text-6xl">Publish plugins. <span class="text-primary">Ship better harnesses.</span></h1>
      <p class="mx-auto mt-6 max-w-2xl text-pretty text-base leading-7 text-muted-foreground sm:text-lg">DSH is the home for plugins that extend DeepSeek Harness — discover community tools, publish your own package, and install it with one command.</p>
      <div class="mt-8 flex flex-wrap justify-center gap-3">
        <Button as-child size="lg"><RouterLink to="/plugins">Browse all plugins <ArrowRight /></RouterLink></Button>
        <Button as-child size="lg" variant="outline"><RouterLink to="/publish"><UploadCloud /> Publish a plugin</RouterLink></Button>
      </div>
    </section>

    <section class="mx-auto mt-12 max-w-3xl px-1">
      <Card class="overflow-hidden border-primary/20 bg-card/90 py-0 shadow-2xl shadow-primary/10">
        <div class="flex items-center gap-2 border-b bg-muted/40 px-4 py-3 text-sm text-muted-foreground"><Terminal class="size-4 text-primary" /><span>Install directly from the registry</span></div>
        <div class="flex items-center gap-3 p-3 sm:p-4"><code class="min-w-0 flex-1 truncate rounded-md bg-foreground px-3 py-2.5 font-mono text-sm text-background">{{ installCommand }}</code><Button size="icon" variant="outline" aria-label="Copy install command" @click="copyInstallCommand"><Check v-if="copied" class="text-primary" /><Copy v-else /></Button></div>
      </Card>
    </section>

    <section id="plugins" class="mx-auto mt-24 max-w-6xl scroll-mt-24 px-4 sm:px-6">
      <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div>
          <p class="text-sm font-medium text-primary">Trending plugins</p>
          <h2 class="mt-2 text-3xl font-semibold tracking-tight">Build on what the community ships.</h2>
          <p class="mt-2 max-w-xl text-muted-foreground">Discover the most popular plugins for DeepSeek Harness.</p>
        </div>
        <div class="flex items-center gap-3">
          <Button variant="outline" as-child><RouterLink to="/plugins">View all plugins <ArrowRight /></RouterLink></Button>
        </div>
      </div>

      <!-- Trending Plugins Container: 3-column grid on desktop so all 3 cards (Top 1, 2, 3) are completely visible side-by-side without horizontal scroll; scrollable on smaller mobile screens -->
      <div v-if="trendingPlugins.length > 0" class="mt-8 -mx-4 px-4 sm:mx-0 sm:px-0 flex md:grid md:grid-cols-3 overflow-x-auto md:overflow-visible gap-4 sm:gap-5 pb-4 md:pb-0 pt-2.5 [&::-webkit-scrollbar]:h-1.5 [&::-webkit-scrollbar-track]:rounded-full [&::-webkit-scrollbar-track]:bg-primary/5 [&::-webkit-scrollbar-thumb]:rounded-full [&::-webkit-scrollbar-thumb]:bg-primary/25 hover:[&::-webkit-scrollbar-thumb]:bg-primary/40 transition-colors">
        <PluginCard
          v-for="(plugin, index) in trendingPlugins.slice(0, 3)"
          :key="plugin.id"
          :plugin="plugin"
          :rank="index + 1"
          class="min-w-65 sm:min-w-70 md:min-w-0 md:max-w-none md:w-full shrink-0 md:shrink!"
        />
      </div>
      <div v-else class="mt-8 rounded-2xl border border-dashed border-border/80 p-8 text-center text-sm text-muted-foreground">
        No trending plugins available at the moment.
      </div>
    </section>

    <section id="workflow" class="mx-auto mt-24 max-w-6xl scroll-mt-24 rounded-2xl border bg-muted/35 p-6 sm:p-10"><div class="max-w-xl"><p class="text-sm font-medium text-primary">A registry, not just a showcase</p><h2 class="mt-2 text-3xl font-semibold tracking-tight">From local idea to an installable plugin.</h2></div><div class="mt-10 grid gap-8 md:grid-cols-3"><div><span class="grid size-9 place-items-center rounded-full bg-primary text-sm font-semibold text-primary-foreground">1</span><h3 class="mt-4 font-semibold">Publish</h3><p class="mt-2 text-sm leading-6 text-muted-foreground">Package an extension for DeepSeek Harness and share its manifest with the registry.</p></div><div><span class="grid size-9 place-items-center rounded-full bg-primary text-sm font-semibold text-primary-foreground">2</span><h3 class="mt-4 font-semibold">Discover</h3><p class="mt-2 text-sm leading-6 text-muted-foreground">Developers search a focused catalogue with versions, authors and useful metadata.</p></div><div><span class="grid size-9 place-items-center rounded-full bg-primary text-sm font-semibold text-primary-foreground">3</span><h3 class="mt-4 font-semibold">Install</h3><p class="mt-2 text-sm leading-6 text-muted-foreground">Bring a plugin into a harness with a familiar npx command and get back to building.</p></div></div></section>

    <section class="mx-auto mt-12 max-w-6xl"><Card class="border-primary/15 bg-card/90 py-0"><div class="flex flex-col gap-4 p-5 sm:flex-row sm:items-center sm:justify-between"><div class="flex items-center gap-3"><span class="grid size-10 place-items-center rounded-full bg-primary/10 text-primary"><CheckCircle2 v-if="health" class="size-5" /><CircleAlert v-else-if="error" class="size-5 text-destructive" /><LoaderCircle v-else class="size-5 animate-spin" /></span><div><p class="font-medium">Registry status</p><p class="text-sm text-muted-foreground"><span v-if="health">API online — {{ health.status }}</span><span v-else-if="error">API unavailable — {{ error }}</span><span v-else>Checking API connection…</span></p></div></div><span class="font-mono text-xs text-muted-foreground">/api/v1/health</span></div></Card></section>
  </div>
</template>
