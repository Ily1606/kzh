import { describe, it, expect, vi, beforeEach } from 'vitest';
import { createApp } from 'vue';
import { createPinia, setActivePinia } from 'pinia';
import piniaPluginPersistedstate from 'pinia-plugin-persistedstate';
import { usePluginsStore } from '@/stores/plugins.store';
import { useAuthStore } from '@/stores/auth.store';
import type { PluginResource, UserResource } from '@/api/generated/model';

const mockPluginIndex = vi.fn();
const mockPluginTrending = vi.fn();
const mockPluginMyPlugins = vi.fn();
const mockPluginShow = vi.fn();
const mockPluginUpdate = vi.fn();
const mockStarStore = vi.fn();
const mockNotifyError = vi.fn();

vi.mock('@/api/generated/endpoints', () => ({
  getPlugin: () => ({
    pluginIndex: mockPluginIndex,
    pluginTrending: mockPluginTrending,
    pluginMyPlugins: mockPluginMyPlugins,
    pluginShow: mockPluginShow,
    pluginUpdate: mockPluginUpdate,
  }),
  getStar: () => ({ starStore: mockStarStore }),
}));

vi.mock('@/utils/toast', () => ({
  notifyError: (message: string) => mockNotifyError(message),
}));

function makePlugin(overrides: Partial<PluginResource> = {}): PluginResource {
  return {
    id: 'plugin-1',
    name: '@dsh/my-plugin',
    user_id: 'user-1',
    author: null,
    title: 'My awesome plugin',
    license: 'MIT',
    approved_at: '2026-10-06T00:00:00Z',
    status: 'approved',
    source_link: 'https://github.com/owner/repo',
    star_count: 5,
    comment_count: 2,
    view_count: 100,
    created_at: '2026-10-01T00:00:00Z',
    updated_at: '2026-10-06T00:00:00Z',
    ...overrides,
  };
}

function signIn() {
  const auth = useAuthStore();
  auth.setUser({ id: 'user-1', name: 'Tester', email: 'tester@example.com' } as UserResource);
  auth.setToken('mock-token');
}

function meta(overrides: Record<string, number> = {}) {
  return { current_page: 1, last_page: 1, per_page: 9, from: 1, to: 9, total: 9, ...overrides };
}

describe('Plugins Store', () => {
  beforeEach(() => {
    vi.clearAllMocks();

    const pinia = createPinia();
    pinia.use(piniaPluginPersistedstate);
    createApp({}).use(pinia);
    setActivePinia(pinia);

    localStorage.clear();
    mockNotifyError.mockImplementation(() => {});
  });

  describe('put()', () => {
    it('keeps a stored field the incoming payload omits', () => {
      const store = usePluginsStore();

      store.put([makePlugin({ star_count: 5, is_star: false })]);
      // A guest response carries no is_star at all.
      store.put([makePlugin({ star_count: 6 })]);

      expect(store.findById('plugin-1')).toMatchObject({ star_count: 6, is_star: false });
    });

    it('overwrites a field the incoming payload carries', () => {
      const store = usePluginsStore();

      store.put([makePlugin({ star_count: 5, is_star: false })]);
      store.put([makePlugin({ star_count: 42, is_star: true })]);

      expect(store.findById('plugin-1')).toMatchObject({ star_count: 42, is_star: true });
    });

    it('does not create a duplicate when the same id is put twice', () => {
      const store = usePluginsStore();

      store.put([makePlugin({ star_count: 5 })]);
      store.put([makePlugin({ star_count: 6 })]);
      store.put([makePlugin({ id: 'plugin-2' })]);

      expect(Object.keys(store.entities)).toEqual(['plugin-1', 'plugin-2']);
    });
  });

  describe('patchPlugin()', () => {
    it('is seen by every list holding the plugin', async () => {
      const store = usePluginsStore();
      signIn();

      mockPluginTrending.mockResolvedValue({ data: { plugins: [makePlugin()] } });
      mockPluginIndex.mockResolvedValue({ data: [makePlugin()], meta: meta() });

      await store.fetchTrending();
      await store.fetchList();

      store.patchPlugin('plugin-1', { star_count: 99 });

      expect(store.trendingPlugins[0].star_count).toBe(99);
      expect(store.listPlugins[0].star_count).toBe(99);
    });

    it('ignores an unknown id', () => {
      const store = usePluginsStore();

      expect(() => store.patchPlugin('nope', { star_count: 1 })).not.toThrow();
      expect(store.findById('nope')).toBeNull();
    });
  });

  describe('fetchTrending()', () => {
    it('skips the network when the ids are already there', async () => {
      const store = usePluginsStore();

      mockPluginTrending.mockResolvedValue({ data: { plugins: [makePlugin()] } });

      await store.fetchTrending();
      await store.fetchTrending();

      expect(mockPluginTrending).toHaveBeenCalledTimes(1);
    });

    it('refetches when forced', async () => {
      const store = usePluginsStore();

      mockPluginTrending.mockResolvedValue({ data: { plugins: [makePlugin()] } });

      await store.fetchTrending();
      await store.fetchTrending(true);

      expect(mockPluginTrending).toHaveBeenCalledTimes(2);
    });

    it('records the error and clears the flag', async () => {
      const store = usePluginsStore();

      mockPluginTrending.mockRejectedValue({
        response: { data: { message: 'Trending exploded.' } },
      });

      await store.fetchTrending();

      expect(store.fetchError).toBe('Trending exploded.');
      expect(store.isFetchingTrending).toBe(false);
    });
  });

  describe('fetchList()', () => {
    it('stores the ids and the page meta', async () => {
      const store = usePluginsStore();

      mockPluginIndex.mockResolvedValue({
        data: [makePlugin(), makePlugin({ id: 'plugin-2' })],
        meta: meta({ current_page: 2, last_page: 5, total: 42 }),
      });

      await store.fetchList(2);

      expect(mockPluginIndex).toHaveBeenCalledWith({ params: { page: 2, per_page: 9 } });
      expect(store.list).toMatchObject({ page: 2, lastPage: 5, total: 42 });
      expect(store.list.ids).toEqual(['plugin-1', 'plugin-2']);
      expect(store.listPlugins).toHaveLength(2);
    });

    it('always refetches, because the list is paginated', async () => {
      const store = usePluginsStore();

      mockPluginIndex.mockResolvedValue({ data: [makePlugin()], meta: meta() });

      await store.fetchList();
      await store.fetchList();

      expect(mockPluginIndex).toHaveBeenCalledTimes(2);
    });
  });

  describe('fetchMine()', () => {
    it('no-ops for a guest', async () => {
      const store = usePluginsStore();

      await store.fetchMine();

      expect(mockPluginMyPlugins).not.toHaveBeenCalled();
      // Cleared, not left spinning: a guest has no Resources page.
      expect(store.isFetchingMine).toBe(false);
    });

    it('sends the status filter and stores the result', async () => {
      const store = usePluginsStore();
      signIn();

      mockPluginMyPlugins.mockResolvedValue({ data: [makePlugin()], meta: meta() });

      await store.fetchMine(1, 'pending');

      expect(mockPluginMyPlugins).toHaveBeenCalledWith({
        page: 1,
        per_page: 9,
        status: 'pending',
      });
      expect(store.minePlugins).toHaveLength(1);
    });
  });

  describe('ensurePlugin()', () => {
    it('returns the cached plugin without calling the API', async () => {
      const store = usePluginsStore();

      store.put([makePlugin()]);

      const plugin = await store.ensurePlugin('plugin-1');

      expect(plugin?.id).toBe('plugin-1');
      expect(mockPluginShow).not.toHaveBeenCalled();
    });

    it('fetches on a miss and caches the result', async () => {
      const store = usePluginsStore();

      mockPluginShow.mockResolvedValue({ data: { plugin: makePlugin({ is_star: true }) } });

      const plugin = await store.ensurePlugin('plugin-1');

      expect(mockPluginShow).toHaveBeenCalledWith('plugin-1');
      expect(plugin?.is_star).toBe(true);
      expect(store.findById('plugin-1')?.is_star).toBe(true);
    });

    it('returns null and records the error when the fetch fails', async () => {
      const store = usePluginsStore();

      mockPluginShow.mockRejectedValue({ response: { data: { message: 'Not found.' } } });

      const plugin = await store.ensurePlugin('plugin-1');

      expect(plugin).toBeNull();
      expect(store.fetchError).toBe('Not found.');
      expect(store.isFetchingPlugin).toBe(false);
    });
  });

  describe('setStar()', () => {
    beforeEach(() => {
      mockStarStore.mockResolvedValue({ data: { starred: true, star_count: 6 } });
    });

    it('applies the optimistic count at once, then the server count', async () => {
      const store = usePluginsStore();
      store.put([makePlugin({ star_count: 5, is_star: false })]);

      mockStarStore.mockImplementation(async () => {
        expect(store.findById('plugin-1')).toMatchObject({ is_star: true, star_count: 6 });

        return { data: { starred: true, star_count: 6 } };
      });

      await store.setStar('plugin-1', true);

      expect(mockStarStore).toHaveBeenCalledWith('plugin-1', { starred: true });
      expect(store.findById('plugin-1')).toMatchObject({ is_star: true, star_count: 6 });
      expect(store.isStarring('plugin-1')).toBe(false);
    });

    it('lets the server count win over the optimistic one', async () => {
      const store = usePluginsStore();
      store.put([makePlugin({ star_count: 5, is_star: false })]);

      mockStarStore.mockResolvedValue({ data: { starred: true, star_count: 17 } });

      await store.setStar('plugin-1', true);

      expect(store.findById('plugin-1')?.star_count).toBe(17);
    });

    it('rolls back and notifies when the request fails', async () => {
      const store = usePluginsStore();
      store.put([makePlugin({ star_count: 5, is_star: false })]);

      mockStarStore.mockRejectedValue({ response: { data: { message: 'Too many requests.' } } });

      await store.setStar('plugin-1', true);

      expect(store.findById('plugin-1')).toMatchObject({ is_star: false, star_count: 5 });
      expect(mockNotifyError).toHaveBeenCalledWith('Too many requests.');
      expect(store.isStarring('plugin-1')).toBe(false);
    });

    it('rolls back a star the server rejected as not starring', async () => {
      const store = usePluginsStore();
      store.put([makePlugin({ star_count: 5, is_star: true })]);

      mockStarStore.mockResolvedValue({ data: { starred: false, star_count: 4 } });

      await store.setStar('plugin-1', false);

      expect(store.findById('plugin-1')).toMatchObject({ is_star: false, star_count: 4 });
      expect(mockNotifyError).not.toHaveBeenCalled();
    });

    it('never lets the optimistic count go negative', async () => {
      const store = usePluginsStore();
      store.put([makePlugin({ star_count: 0, is_star: true })]);

      mockStarStore.mockImplementation(async () => {
        expect(store.findById('plugin-1')?.star_count).toBe(0);

        return { data: { starred: false, star_count: 0 } };
      });

      await store.setStar('plugin-1', false);

      expect(store.findById('plugin-1')?.star_count).toBe(0);
    });

    it('ignores a second call while the first is in flight', async () => {
      const store = usePluginsStore();
      store.put([makePlugin({ star_count: 5, is_star: false })]);

      let release!: () => void;
      mockStarStore.mockImplementation(() => new Promise((resolve) => {
        release = () => resolve({ data: { starred: true, star_count: 6 } });
      }));

      const first = store.setStar('plugin-1', true);
      await store.setStar('plugin-1', false);

      expect(mockStarStore).toHaveBeenCalledTimes(1);
      expect(store.isStarring('plugin-1')).toBe(true);

      release();
      await first;

      expect(store.isStarring('plugin-1')).toBe(false);
    });

    it('does nothing for an unknown plugin', async () => {
      const store = usePluginsStore();

      await store.setStar('nope', true);

      expect(mockStarStore).not.toHaveBeenCalled();
    });
  });

  describe('updatePlugin()', () => {
    it('sends only the four editable fields', async () => {
      const store = usePluginsStore();
      store.put([makePlugin()]);

      mockPluginUpdate.mockResolvedValue({ data: { plugin: makePlugin({ title: 'Renamed' }) } });

      await store.updatePlugin('plugin-1', { title: 'Renamed' });

      expect(mockPluginUpdate).toHaveBeenCalledWith('plugin-1', { title: 'Renamed' });
    });

    it('writes the server response through, so every list sees the edit', async () => {
      const store = usePluginsStore();
      store.put([makePlugin({ title: 'My awesome plugin' })]);

      // The server owns the fields the form never touched — `updated_at`, the
      // comment recount — so the stored copy has to come from the response
      // rather than from what the form happened to submit.
      mockPluginUpdate.mockResolvedValue({
        data: {
          plugin: makePlugin({
            title: 'Renamed',
            updated_at: '2026-10-09T00:00:00Z',
            comment_count: 9,
          }),
        },
      });

      const ok = await store.updatePlugin('plugin-1', { title: 'Renamed' });

      expect(ok).toBe(true);
      expect(store.findById('plugin-1')).toMatchObject({
        title: 'Renamed',
        updated_at: '2026-10-09T00:00:00Z',
        comment_count: 9,
      });
    });

    it('reports failure and keeps the stored plugin untouched', async () => {
      const store = usePluginsStore();
      store.put([makePlugin({ title: 'My awesome plugin' })]);

      mockPluginUpdate.mockRejectedValue({ response: { data: { message: 'Too many requests.' } } });

      // A thrown error would leave the caller unable to tell "nothing happened"
      // from "it changed and the cache is now stale".
      const ok = await store.updatePlugin('plugin-1', { title: 'Renamed' });

      expect(ok).toBe(false);
      expect(mockNotifyError).toHaveBeenCalledWith('Too many requests.');
      expect(store.findById('plugin-1')?.title).toBe('My awesome plugin');
    });

    it('falls back to a generic message when the response carries none', async () => {
      const store = usePluginsStore();

      mockPluginUpdate.mockRejectedValue({});

      await store.updatePlugin('plugin-1', { title: 'Renamed' });

      expect(mockNotifyError).toHaveBeenCalledWith('Could not save the plugin. Please try again.');
    });

    it('reports success even when the response has no plugin in it', async () => {
      const store = usePluginsStore();
      store.put([makePlugin()]);

      mockPluginUpdate.mockResolvedValue({ data: {} });

      // Nothing to write, so the cache keeps what it had — and the page is told
      // the request itself went through.
      const ok = await store.updatePlugin('plugin-1', { title: 'Renamed' });

      expect(ok).toBe(false);
      expect(store.findById('plugin-1')?.title).toBe('My awesome plugin');
    });
  });

  describe('reset()', () => {
    it('empties the cache and every query', async () => {
      const store = usePluginsStore();
      signIn();

      mockPluginTrending.mockResolvedValue({ data: { plugins: [makePlugin()] } });
      mockPluginIndex.mockResolvedValue({ data: [makePlugin()], meta: meta() });
      mockPluginMyPlugins.mockResolvedValue({ data: [makePlugin()], meta: meta() });

      await store.fetchTrending();
      await store.fetchList();
      await store.fetchMine();

      store.reset();

      expect(store.entities).toEqual({});
      expect(store.trendingPlugins).toEqual([]);
      expect(store.listPlugins).toEqual([]);
      expect(store.minePlugins).toEqual([]);
      expect(store.list).toMatchObject({ ids: [], total: 0, lastPage: 1 });
      expect(store.fetchError).toBeNull();
      expect(store.starringIds).toEqual([]);
    });

    it('persists nothing about plugins', async () => {
      const store = usePluginsStore();
      signIn();

      mockPluginIndex.mockResolvedValue({ data: [makePlugin()], meta: meta() });
      await store.fetchList();

      // Only the auth store writes to localStorage. A persisted plugin cache
      // would show the previous user's stars to the next one on this browser.
      expect(Object.keys(localStorage).sort()).toEqual(['auth', 'auth_token']);
      expect(localStorage.getItem('plugins')).toBeNull();
      expect(JSON.stringify(localStorage)).not.toContain('plugin-1');
    });
  });
});
