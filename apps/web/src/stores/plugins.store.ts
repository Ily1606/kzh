import { defineStore } from 'pinia';
import { computed, ref } from 'vue';
import { getPlugin, getStar } from '@/api/generated/endpoints';
import type { PluginResource, PluginStatus } from '@/api/generated/model';
import { pluginConfig } from '@/config/plugins';
import { notifyError } from '@/utils/toast';
import { useAuthStore } from './auth.store';

/**
 * The ids one query returned, plus the page state that goes with them. The
 * plugin objects themselves live in `entities`, so a plugin reached from
 * trending, from the catalogue and from the owner's own list is one object.
 */
export interface PluginQueryState {
  ids: string[];
  page: number;
  total: number;
  lastPage: number;
}

const emptyQuery = (): PluginQueryState => ({ ids: [], page: 1, total: 0, lastPage: 1 });

function errorMessage(err: unknown, fallback: string): string {
  const message = (err as { response?: { data?: { message?: string } } } | null)?.response?.data?.message;

  return typeof message === 'string' && message.length > 0 ? message : fallback;
}

/**
 * Plugin data, and the only thing in the app that fetches it.
 *
 * Server state lives here; UI state (search box, expanded readme, copied
 * flag) stays in the views that own it.
 *
 * Not persisted: `is_star` belongs to whoever is signed in, so a persisted
 * cache would show the previous user's stars to the next one on this browser.
 */
export const usePluginsStore = defineStore('plugins', () => {
  const { pluginIndex, pluginTrending, pluginMyPlugins, pluginShow } = getPlugin();
  const { starStore } = getStar();

  const entities = ref<Record<string, PluginResource>>({});

  const trending = ref<PluginQueryState>(emptyQuery());
  const list = ref<PluginQueryState>(emptyQuery());
  const mine = ref<PluginQueryState>(emptyQuery());

  // Start true, not false: every view fetches on mount, and the first render
  // happens before that request starts. Defaulting to false would show an empty
  // catalogue for a frame on every page load before the real data arrives.
  // `fetchMine` clears its own flag on the guest no-op path.
  const isFetchingTrending = ref(true);
  const isFetchingList = ref(true);
  const isFetchingMine = ref(true);
  const isFetchingPlugin = ref(false);
  const fetchError = ref<string | null>(null);
  const starringIds = ref<string[]>([]);

  const resolve = (ids: string[]): PluginResource[] =>
    ids.map((id) => entities.value[id]).filter((plugin): plugin is PluginResource => Boolean(plugin));

  const trendingPlugins = computed(() => resolve(trending.value.ids));
  const listPlugins = computed(() => resolve(list.value.ids));
  const minePlugins = computed(() => resolve(mine.value.ids));

  /**
   * Merge a response into the cache.
   *
   * Always writes, and never replaces the whole map: `entities` is keyed by id,
   * so putting the same plugin twice overwrites the same slot instead of
   * leaving a duplicate behind. A field the incoming payload omits is kept as
   * it was — `is_star` is absent from every response for a guest, and dropping
   * the stored value on a guest response would make the star icon lie.
   *
   * Guarding this with `if (!entities[id])` would be the tempting version and
   * is wrong: it freezes `star_count` at whatever the first response said,
   * forever, no matter how many times the list is refetched.
   */
  function put(items: PluginResource[]): void {
    for (const plugin of items) {
      entities.value[plugin.id] = { ...entities.value[plugin.id], ...plugin };
    }
  }

  function findById(id: string): PluginResource | null {
    return entities.value[id] ?? null;
  }

  /**
   * Write through to the single stored object, so every list showing this
   * plugin — trending, catalogue, owner's list — sees the change at once.
   */
  function patchPlugin(id: string, patch: Partial<PluginResource>): void {
    const current = entities.value[id];

    if (current) {
      entities.value[id] = { ...current, ...patch };
    }
  }

  function isStarring(id: string): boolean {
    return starringIds.value.includes(id);
  }

  function reset(): void {
    entities.value = {};
    trending.value = emptyQuery();
    list.value = emptyQuery();
    mine.value = emptyQuery();
    isFetchingTrending.value = false;
    isFetchingList.value = false;
    isFetchingMine.value = false;
    isFetchingPlugin.value = false;
    fetchError.value = null;
    starringIds.value = [];
  }

  /**
   * Trending is cached, so a second visit to the home page reuses what the
   * first fetch brought in rather than paying for the ranking query again.
   * `force` is for the case where fresh numbers actually matter.
   */
  async function fetchTrending(force = false): Promise<void> {
    if (!force && trending.value.ids.length > 0) {
      return;
    }

    isFetchingTrending.value = true;
    fetchError.value = null;

    try {
      const res = await pluginTrending();
      // Scramble cannot infer this endpoint's shape, so the collection comes
      // back untyped. The resource is the same one every other endpoint sends.
      const plugins = (res.data?.plugins ?? []) as PluginResource[];

      put(plugins);
      trending.value = {
        ids: plugins.map((plugin) => plugin.id),
        page: 1,
        total: plugins.length,
        lastPage: 1,
      };
    } catch (err) {
      fetchError.value = errorMessage(err, 'Failed to load trending plugins.');
    } finally {
      isFetchingTrending.value = false;
    }
  }

  /** Always fetches: this list is paginated, and a search or page change must see fresh counts. */
  async function fetchList(page = 1): Promise<void> {
    isFetchingList.value = true;
    fetchError.value = null;

    try {
      const res = await pluginIndex({ params: { page, per_page: pluginConfig.numberPluginPerPage } });
      const plugins = res.data ?? [];

      put(plugins);
      list.value = {
        ids: plugins.map((plugin) => plugin.id),
        page,
        total: res.meta?.total ?? plugins.length,
        lastPage: res.meta?.last_page ?? 1,
      };
    } catch (err) {
      fetchError.value = errorMessage(err, 'Failed to load plugins.');
    } finally {
      isFetchingList.value = false;
    }
  }

  /** No-ops for a guest: the endpoint is behind `auth:sanctum` and would 401. */
  async function fetchMine(page = 1, status?: PluginStatus): Promise<void> {
    if (!useAuthStore().isAuthenticated) {
      // A guest has no Resources page to load, so the flag must not stay set
      // or that view would spin forever.
      isFetchingMine.value = false;

      return;
    }

    isFetchingMine.value = true;
    // Cleared up front, so a successful retry does not land under a stale error.
    fetchError.value = null;

    try {
      const res = await pluginMyPlugins({
        page,
        per_page: pluginConfig.numberPluginPerPage,
        status,
      });
      const plugins = res.data ?? [];

      put(plugins);
      mine.value = {
        ids: plugins.map((plugin) => plugin.id),
        page,
        total: res.meta?.total ?? plugins.length,
        lastPage: res.meta?.last_page ?? 1,
      };
    } catch (err) {
      fetchError.value = errorMessage(err, 'Failed to load your plugins.');
    } finally {
      isFetchingMine.value = false;
    }
  }

  /**
   * The plugin behind a URL, fetching it only if some earlier page has not
   * already cached it. A deep link or a hard refresh lands here with an empty
   * store and costs one request; arriving from the catalogue costs none.
   */
  async function ensurePlugin(id: string): Promise<PluginResource | null> {
    const cached = entities.value[id];

    if (cached) {
      return cached;
    }

    isFetchingPlugin.value = true;
    fetchError.value = null;

    try {
      const res = await pluginShow(id);
      const plugin = res.data?.plugin ?? null;

      if (plugin) {
        put([plugin]);
      }

      return plugin;
    } catch (err) {
      fetchError.value = errorMessage(err, 'Failed to load plugin details.');

      return null;
    } finally {
      isFetchingPlugin.value = false;
    }
  }

  /**
   * Move one user's star to `starred` and report the state that resulted.
   *
   * Set-state rather than toggle, matching the endpoint: the caller says which
   * state it wants, so a retry cannot reverse it. The optimistic update is
   * rolled back if the request fails, and the server's own count wins on
   * success — it counts every star on the plugin, not just this user's.
   */
  async function setStar(id: string, starred: boolean): Promise<void> {
    const before = entities.value[id];

    if (!before || isStarring(id)) {
      return;
    }

    starringIds.value = [...starringIds.value, id];
    patchPlugin(id, {
      is_star: starred,
      star_count: Math.max(0, (before.star_count ?? 0) + (starred ? 1 : -1)),
    });

    try {
      const res = await starStore(id, { starred });

      patchPlugin(id, { is_star: res.data.starred, star_count: res.data.star_count });
    } catch (err) {
      patchPlugin(id, { is_star: before.is_star, star_count: before.star_count });
      notifyError(errorMessage(err, 'Could not update star. Please try again.'));
    } finally {
      starringIds.value = starringIds.value.filter((starringId) => starringId !== id);
    }
  }

  return {
    entities,
    trending,
    list,
    mine,
    isFetchingTrending,
    isFetchingList,
    isFetchingMine,
    isFetchingPlugin,
    fetchError,
    starringIds,

    trendingPlugins,
    listPlugins,
    minePlugins,

    put,
    findById,
    patchPlugin,
    isStarring,
    fetchTrending,
    fetchList,
    fetchMine,
    ensurePlugin,
    setStar,
    reset,
  };
});
