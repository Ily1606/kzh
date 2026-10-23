import { computed, ref } from 'vue';
import type { PluginResource } from '@/api/generated/model';
import { emptyQuery, latestOnly, type PluginQueryState } from './helpers';

export function usePluginsState() {
  // The one place a plugin object lives: every list reaches it through here.
  const entities = ref<Record<string, PluginResource>>({});

  const trending = ref<PluginQueryState>(emptyQuery());
  const list = ref<PluginQueryState>(emptyQuery());
  const mine = ref<PluginQueryState>(emptyQuery());

  // One ticket per list: paging the catalogue and changing the owner's status
  // tab do not cancel each other out.
  const listTicket = latestOnly();
  const mineTicket = latestOnly();
  const trendingTicket = latestOnly();

  // The ids with a star request in flight, so a button can disable itself.
  const starringIds = ref<string[]>([]);

  // Start true: the first render happens before the request does, so false
  // would flash an empty catalogue. `fetchMine` clears its flag for a guest.
  const isFetchingTrending = ref(true);
  const isFetchingList = ref(true);
  const isFetchingMine = ref(true);
  const isFetchingPlugin = ref(false);
  const fetchError = ref<string | null>(null);

  const resolve = (ids: string[]): PluginResource[] =>
    ids.map((id) => entities.value[id]).filter((plugin): plugin is PluginResource => Boolean(plugin));

  const trendingPlugins = computed(() => resolve(trending.value.ids));
  const listPlugins = computed(() => resolve(list.value.ids));
  const minePlugins = computed(() => resolve(mine.value.ids));

  return {
    entities,
    trending,
    list,
    mine,
    starringIds,
    isFetchingTrending,
    isFetchingList,
    isFetchingMine,
    isFetchingPlugin,
    fetchError,
    listTicket,
    mineTicket,
    trendingTicket,
    trendingPlugins,
    listPlugins,
    minePlugins,
  };
}

export type PluginsState = ReturnType<typeof usePluginsState>;
