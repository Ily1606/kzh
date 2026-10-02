<script setup lang="ts">
import { onMounted, ref } from "vue";
import type { HealthResponse } from "@dsh/shared";
import { ArrowRight, Check, CheckCircle2, CircleAlert, Copy, LoaderCircle, PackageOpen, Terminal, UploadCloud } from "lucide-vue-next";
import { getHealth, getPlugin } from "@/api/generated/endpoints";
import type { PluginResource } from "@/api/generated/model";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import PluginCard from "@/components/PluginCard.vue";

const health = ref<HealthResponse | null>(null);
const error = ref<string | null>(null);
const copied = ref(false);
const installCommand = "npx dsh add @dsh/hello-world";
const { v1Health } = getHealth();
const { pluginTrending, pluginIndex } = getPlugin();

// Trending plugins state
const trendingPlugins = ref<PluginResource[]>([]);

// All plugins (paginated) state
const allPlugins = ref<PluginResource[]>([]);
const allPluginsMeta = ref<any>(null);
const currentPage = ref(1);
const isFetchingPlugins = ref(false);

const mockPlugins = [
  { id: '1', name: '@dsh/starter-kit', title: 'A reference plugin for bootstrapping common harness workflows.', icon: 'boxes', version: 'v0.1.0', author: 'DSH team', tag: 'soon' },
  { id: '2', name: '@dsh/guardrails', title: 'Opinionated safety checks and policy hooks for your harness.', icon: 'shield', version: 'v0.1.0', author: 'community preview', tag: 'soon' },
  { id: '3', name: '@dsh/context-search', title: 'Search, retrieve and organize relevant context for an agent run.', icon: 'search', version: 'v0.1.0', author: 'community preview', tag: 'soon' },
];

async function fetchAllPlugins(page = 1) {
  isFetchingPlugins.value = true;
  try {
    const res = await pluginIndex({ params: { page, per_page: 6 } });
    allPlugins.value = res.data;
    allPluginsMeta.value = res.meta;
    currentPage.value = page;
  } catch (e) {
    console.error("Failed to fetch paginated plugins", e);
  } finally {
    isFetchingPlugins.value = false;
  }
}

onMounted(async () => {
  try {
    const [healthRes, pluginsRes] = await Promise.all([
      v1Health(),
      pluginTrending(),
      fetchAllPlugins(1)
    ]);
    health.value = healthRes.data as unknown as HealthResponse;
    if (pluginsRes.data?.plugins) {
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
    <div class="pointer-events-none absolute inset-x-0 top-0 -z-10 h-[34rem] bg-[radial-gradient(ellipse_at_top,_var(--color-primary-100),_transparent_64%)] dark:bg-[radial-gradient(ellipse_at_top,_#3b0764,_transparent_56%)]" />
    <section class="mx-auto max-w-4xl px-1 text-center">
      <div class="inline-flex items-center gap-2 rounded-full border bg-background/80 px-3 py-1 text-sm font-medium text-muted-foreground shadow-sm backdrop-blur"><PackageOpen class="size-4 text-primary" /> DeepSeek Harness plugin registry</div>
      <h1 class="mt-6 text-balance text-4xl font-bold tracking-tight sm:text-6xl">Publish plugins. <span class="text-primary">Ship better harnesses.</span></h1>
      <p class="mx-auto mt-6 max-w-2xl text-pretty text-base leading-7 text-muted-foreground sm:text-lg">DSH is the home for plugins that extend DeepSeek Harness — discover community tools, publish your own package, and install it with one command.</p>
      <div class="mt-8 flex flex-wrap justify-center gap-3"><Button as-child size="lg"><a href="#plugins">Explore plugins <ArrowRight /></a></Button><Button as-child size="lg" variant="outline"><RouterLink to="/publish"><UploadCloud /> Publish a plugin</RouterLink></Button></div>
    </section>

    <section class="mx-auto mt-12 max-w-3xl px-1">
      <Card class="overflow-hidden border-primary/20 bg-card/90 py-0 shadow-2xl shadow-primary/10">
        <div class="flex items-center gap-2 border-b bg-muted/40 px-4 py-3 text-sm text-muted-foreground"><Terminal class="size-4 text-primary" /><span>Install directly from the registry</span></div>
        <div class="flex items-center gap-3 p-3 sm:p-4"><code class="min-w-0 flex-1 truncate rounded-md bg-foreground px-3 py-2.5 font-mono text-sm text-background">{{ installCommand }}</code><Button size="icon" variant="outline" aria-label="Copy install command" @click="copyInstallCommand"><Check v-if="copied" class="text-primary" /><Copy v-else /></Button></div>
      </Card>
    </section>

    <section id="plugins" class="mx-auto mt-24 max-w-6xl scroll-mt-24 px-4 sm:px-6">
      <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end"><div><p class="text-sm font-medium text-primary">Registry preview</p><h2 class="mt-2 text-3xl font-semibold tracking-tight">Build on what the community ships.</h2><p class="mt-2 max-w-xl text-muted-foreground">Example package cards for the registry catalogue. They will be backed by real publisher data when the API is ready.</p></div><Button variant="outline" as-child><a href="#workflow">How publishing works <ArrowRight /></a></Button></div>

      <!-- Horizontal scroll container -->
      <div class="mt-8 -mx-4 px-4 sm:-mx-4 sm:px-4 flex overflow-x-auto gap-4 snap-x snap-mandatory pb-6 pt-4 [&::-webkit-scrollbar]:h-2 [&::-webkit-scrollbar-track]:rounded-full [&::-webkit-scrollbar-track]:bg-primary/5 [&::-webkit-scrollbar-thumb]:rounded-full [&::-webkit-scrollbar-thumb]:bg-primary/25 hover:[&::-webkit-scrollbar-thumb]:bg-primary/40 transition-colors">
        <template v-if="trendingPlugins.length > 0">
          <PluginCard
            v-for="(plugin, index) in trendingPlugins"
            :key="plugin.id"
            :plugin="plugin"
            :rank="index + 1"
          />
        </template>
        <template v-else>
          <PluginCard
            v-for="(mock, index) in mockPlugins"
            :key="mock.id"
            :fallback="mock"
            :rank="index + 1"
          />
        </template>
      </div>
    </section>

    <!-- Paginated All Plugins Section -->
    <section id="all-plugins" class="mx-auto mt-24 max-w-6xl scroll-mt-24 px-4 sm:px-6">
      <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div>
          <h2 class="text-3xl font-semibold tracking-tight">All plugins</h2>
          <p class="mt-2 max-w-xl text-muted-foreground">Browse all packages published by the community.</p>
        </div>
      </div>

      <div v-if="isFetchingPlugins && allPlugins.length === 0" class="mt-12 flex justify-center py-12">
        <LoaderCircle class="size-8 animate-spin text-primary" />
      </div>
      <div v-else-if="allPlugins.length > 0" class="mt-8 grid gap-4 md:grid-cols-2 lg:grid-cols-3">
        <PluginCard
          v-for="plugin in allPlugins"
          :key="plugin.id"
          :plugin="plugin"
          class="!min-w-0 !max-w-none !w-full !shrink"
        />
      </div>
      <div v-else class="mt-8 py-12 text-center text-muted-foreground border rounded-xl bg-card">
        No plugins found.
      </div>

      <!-- Pagination Controls -->
      <div v-if="allPluginsMeta && allPluginsMeta.last_page > 1" class="mt-10 flex items-center justify-center gap-4">
        <Button
          variant="outline"
          :disabled="currentPage === 1 || isFetchingPlugins"
          @click="fetchAllPlugins(currentPage - 1)"
        >
          Previous
        </Button>
        <span class="text-sm font-medium text-muted-foreground">
          Page {{ currentPage }} of {{ allPluginsMeta.last_page }}
        </span>
        <Button
          variant="outline"
          :disabled="currentPage === allPluginsMeta.last_page || isFetchingPlugins"
          @click="fetchAllPlugins(currentPage + 1)"
        >
          Next
        </Button>
      </div>
    </section>

    <section id="workflow" class="mx-auto mt-24 max-w-6xl scroll-mt-24 rounded-2xl border bg-muted/35 p-6 sm:p-10"><div class="max-w-xl"><p class="text-sm font-medium text-primary">A registry, not just a showcase</p><h2 class="mt-2 text-3xl font-semibold tracking-tight">From local idea to an installable plugin.</h2></div><div class="mt-10 grid gap-8 md:grid-cols-3"><div><span class="grid size-9 place-items-center rounded-full bg-primary text-sm font-semibold text-primary-foreground">1</span><h3 class="mt-4 font-semibold">Publish</h3><p class="mt-2 text-sm leading-6 text-muted-foreground">Package an extension for DeepSeek Harness and share its manifest with the registry.</p></div><div><span class="grid size-9 place-items-center rounded-full bg-primary text-sm font-semibold text-primary-foreground">2</span><h3 class="mt-4 font-semibold">Discover</h3><p class="mt-2 text-sm leading-6 text-muted-foreground">Developers search a focused catalogue with versions, authors and useful metadata.</p></div><div><span class="grid size-9 place-items-center rounded-full bg-primary text-sm font-semibold text-primary-foreground">3</span><h3 class="mt-4 font-semibold">Install</h3><p class="mt-2 text-sm leading-6 text-muted-foreground">Bring a plugin into a harness with a familiar npx command and get back to building.</p></div></div></section>

    <section class="mx-auto mt-12 max-w-6xl"><Card class="border-primary/15 bg-card/90 py-0"><div class="flex flex-col gap-4 p-5 sm:flex-row sm:items-center sm:justify-between"><div class="flex items-center gap-3"><span class="grid size-10 place-items-center rounded-full bg-primary/10 text-primary"><CheckCircle2 v-if="health" class="size-5" /><CircleAlert v-else-if="error" class="size-5 text-destructive" /><LoaderCircle v-else class="size-5 animate-spin" /></span><div><p class="font-medium">Registry status</p><p class="text-sm text-muted-foreground"><span v-if="health">API online — {{ health.status }}</span><span v-else-if="error">API unavailable — {{ error }}</span><span v-else>Checking API connection…</span></p></div></div><span class="font-mono text-xs text-muted-foreground">/api/v1/health</span></div></Card></section>
  </div>
</template>
