import { defineStore } from 'pinia';
import { emptyQuery } from './helpers';
import { usePluginsActions } from './actions';
import { usePluginsState } from './state';

export const usePluginsStore = defineStore('plugins', () => {
  const state = usePluginsState();
  const actions = usePluginsActions(state);

  function reset(): void {
    state.entities.value = {};
    state.trending.value = emptyQuery();
    state.list.value = emptyQuery();
    state.mine.value = emptyQuery();
    state.starringIds.value = [];
    state.isFetchingTrending.value = false;
    state.isFetchingList.value = false;
    state.isFetchingMine.value = false;
    state.isFetchingPlugin.value = false;
    state.fetchError.value = null;
  }

  return {
    entities: state.entities,
    trending: state.trending,
    list: state.list,
    mine: state.mine,
    starringIds: state.starringIds,
    isFetchingTrending: state.isFetchingTrending,
    isFetchingList: state.isFetchingList,
    isFetchingMine: state.isFetchingMine,
    isFetchingPlugin: state.isFetchingPlugin,
    fetchError: state.fetchError,

    trendingPlugins: state.trendingPlugins,
    listPlugins: state.listPlugins,
    minePlugins: state.minePlugins,

    ...actions,
    reset,
  };
});