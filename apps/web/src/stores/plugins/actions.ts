import type { PluginResource, PluginStatus, UpdatePluginRequest } from '@/api/generated/model';
import { notifyError } from '@/utils/toast';
import { useAuthStore } from '@/stores/auth.store';
import * as endpoints from './endpoints';
import { errorMessage } from './helpers';
import type { PluginsState } from './state';

export function usePluginsActions(state: PluginsState) {
  /** Merge a response into the cache. */
  function put(items: PluginResource[]): void {
    for (const plugin of items) {
      state.entities.value[plugin.id] = { ...state.entities.value[plugin.id], ...plugin };
    }
  }

  function findById(id: string): PluginResource | null {
    return state.entities.value[id] ?? null;
  }

  /** Write through, so every list showing this plugin sees the change. */
  function patchPlugin(id: string, patch: Partial<PluginResource>): void {
    const current = state.entities.value[id];

    if (current) {
      state.entities.value[id] = { ...current, ...patch };
    }
  }

  function isStarring(id: string): boolean {
    return state.starringIds.value.includes(id);
  }

  /**
   * Paged trending list. Re-requesting the current page without `force` is a no-op,
   * so navigating between pages does not trigger duplicate requests.
   */
  async function fetchTrending(pageOrForce: number | boolean = 1, force = false): Promise<void> {
    const page = typeof pageOrForce === 'number' ? pageOrForce : 1;
    const isForce = typeof pageOrForce === 'boolean' ? pageOrForce : force;
    const isLatest = state.trendingTicket();

    if (!isForce && state.trending.value.page === page && state.trending.value.ids.length > 0) {
      return;
    }

    state.isFetchingTrending.value = true;
    state.fetchError.value = null;

    try {
      const res = await endpoints.fetchTrending(page);
      if (!isLatest()) return;

      // Untyped: Scramble cannot infer this endpoint's shape.
      const plugins = (res.data?.plugins ?? []) as PluginResource[];
      const meta = res.meta as unknown as {
        current_page?: number;
        last_page?: number;
        per_page?: number;
        total?: number;
      } | undefined;

      put(plugins);
      state.trending.value = {
        ids: plugins.map((plugin) => plugin.id),
        page: meta?.current_page ?? page,
        total: meta?.total ?? plugins.length,
        lastPage: meta?.last_page ?? 1,
      };
    } catch (err) {
      if (!isLatest()) return;

      state.fetchError.value = errorMessage(err, 'Failed to load trending plugins.');
    } finally {
      if (isLatest()) {
        state.isFetchingTrending.value = false;
      }
    }
  }

  /** Never cached: a search or page change must see fresh counts. */
  async function fetchList(page = 1, category?: string | null, search?: string | null): Promise<void> {
    const isLatest = state.listTicket();
    state.isFetchingList.value = true;
    state.fetchError.value = null;

    try {
      const res = await endpoints.fetchIndex(page, category, search);
      // A slow earlier search must not land last and overwrite the newer results.
      if (!isLatest()) return;

      const plugins = res.data ?? [];

      put(plugins);
      state.list.value = {
        ids: plugins.map((plugin) => plugin.id),
        page,
        total: res.meta?.total ?? plugins.length,
        lastPage: res.meta?.last_page ?? 1,
      };
    } catch (err) {
      if (!isLatest()) return;

      state.fetchError.value = errorMessage(err, 'Failed to load plugins.');
    } finally {
      if (isLatest()) state.isFetchingList.value = false;
    }
  }

  /** No-ops for a guest: the endpoint is behind `auth:sanctum`. */
  async function fetchMine(page = 1, status?: PluginStatus): Promise<void> {
    if (!useAuthStore().isAuthenticated) {
      state.isFetchingMine.value = false;

      return;
    }

    const isLatest = state.mineTicket();

    state.isFetchingMine.value = true;
    state.fetchError.value = null;

    try {
      const res = await endpoints.fetchMyPlugins(page, status);
      // Switching tabs fast: the earlier status must not land last.
      if (!isLatest()) return;

      const plugins = res.data ?? [];

      put(plugins);
      state.mine.value = {
        ids: plugins.map((plugin) => plugin.id),
        page,
        total: res.meta?.total ?? plugins.length,
        lastPage: res.meta?.last_page ?? 1,
      };
    } catch (err) {
      if (!isLatest()) return;

      state.fetchError.value = errorMessage(err, 'Failed to load your plugins.');
    } finally {
      if (isLatest()) state.isFetchingMine.value = false;
    }
  }

  /** Find a cached plugin by its author username and plugin name. */
  function findBySlug(username: string, pluginName: string): PluginResource | null {
    return Object.values(state.entities.value).find(
      (p) => p.author?.username === username && p.name === pluginName
    ) ?? null;
  }

  /**
   * Fetches plugin details by slug (username and pluginName).
   */
  async function ensurePluginBySlug(username: string, pluginName: string): Promise<PluginResource | null> {
    const cached = findBySlug(username, pluginName);
    if (cached) {
      return cached;
    }

    state.isFetchingPlugin.value = true;
    state.fetchError.value = null;

    try {
      const res = await endpoints.fetchPlugin(username, pluginName);
      const plugin = res.data?.plugin ?? null;

      if (plugin) {
        put([plugin]);
      }

      return plugin;
    } catch (err) {
      state.fetchError.value = errorMessage(err, 'Failed to load plugin details.');

      return null;
    } finally {
      state.isFetchingPlugin.value = false;
    }
  }

  /**
   * Set-state rather than toggle, matching the endpoint: a retry cannot reverse
   * it. Rolled back on failure; the server's own count wins on success.
   */
  async function setStar(username: string, pluginName: string, starred: boolean): Promise<void> {
    const before = findBySlug(username, pluginName);

    if (!before || isStarring(before.id)) {
      return;
    }

    const id = before.id;

    state.starringIds.value = [...state.starringIds.value, id];
    patchPlugin(id, {
      is_star: starred,
      star_count: Math.max(0, (before.star_count ?? 0) + (starred ? 1 : -1)),
    });

    try {
      const res = await endpoints.putStar(username, pluginName, starred);

      patchPlugin(id, { is_star: res.data.starred, star_count: res.data.star_count });
    } catch (err) {
      patchPlugin(id, { is_star: before.is_star, star_count: before.star_count });
      notifyError(errorMessage(err, 'Could not update star. Please try again.'));
    } finally {
      state.starringIds.value = state.starringIds.value.filter((starringId) => starringId !== id);
    }
  }

  /**
   * Applies an edit and writes the response through to the cache.
   *
   * Returns the saved plugin because an edit can rename it: the caller needs
   * the new slug to navigate to.
   */
  async function updatePlugin(
    username: string,
    pluginName: string,
    payload: UpdatePluginRequest
  ): Promise<PluginResource | null> {
    try {
      const res = await endpoints.savePlugin(username, pluginName, payload);
      const plugin = res.data?.plugin ?? null;

      if (plugin) {
        put([plugin]);
      }

      return plugin;
    } catch (err) {
      notifyError(errorMessage(err, 'Could not save the plugin. Please try again.'));

      return null;
    }
  }

    /**
   * Record a view for the plugin.
   *
   * Fires asynchronously without throwing so viewing the plugin is never
   * interrupted. Updates the stored view_count if the server returns one.
   */
  async function trackView(id: string): Promise<void> {
    if (!id) {
      return;
    }

    try {
      const res = await endpoints.pluginTrackView(id);
      const data = res?.data as unknown as { view_count?: number } | undefined;

      if (data?.view_count !== undefined) {
        patchPlugin(id, { view_count: data.view_count });
      }
    } catch {
      // Non-blocking: view tracking failure is silent to the viewer
    }
  }


  function withoutId<T extends { ids: string[]; total: number }>(list: T, id: string): T {
    if (!list.ids.includes(id)) {
      return list;
    }

    return {
      ...list,
      ids: list.ids.filter((pluginId) => pluginId !== id),
      total: Math.max(0, list.total - 1),
    };
  }

  /** Removes a plugin from the store cache and all list queries. */
  function removePluginFromCache(id: string): void {
    delete state.entities.value[id];

    state.trending.value = withoutId(state.trending.value, id);
    state.list.value = withoutId(state.list.value, id);
    state.mine.value = withoutId(state.mine.value, id);
    state.starringIds.value = state.starringIds.value.filter((starringId) => starringId !== id);
  }

  /** Permanently deletes a plugin owned by the user. */
  async function deletePlugin(id: string): Promise<boolean> {
    try {
      await endpoints.destroyPlugin(id);
      removePluginFromCache(id);

      return true;
    } catch (err) {
      notifyError(errorMessage(err, 'Could not delete the plugin. Please try again.'));

      return false;
    }
  }

  return {
    put,
    findById,
    findBySlug,
    patchPlugin,
    isStarring,
    fetchTrending,
    fetchList,
    fetchMine,
    ensurePluginBySlug,
    setStar,
    updatePlugin,
    deletePlugin,
    trackView
  };
}
