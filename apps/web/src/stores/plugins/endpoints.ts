import { getPlugin, getStar } from '@/api/generated/endpoints';
import type { PluginStatus, UpdatePluginRequest } from '@/api/generated/model';
import { pluginConfig } from '@/config/plugins';

export function fetchTrending(page = 1, perPage = pluginConfig.numberTrendingPluginPerPage) {
  return getPlugin().pluginTrending({
    page,
    per_page: perPage,
  });
}

export function fetchIndex(page: number, category?: string | null, search?: string | null) {
  return getPlugin().pluginIndex({
    page,
    per_page: pluginConfig.numberPluginPerPage,
    category,
    search: search || null,
  });
}

export function fetchMyPlugins(page: number, status?: PluginStatus) {
  return getPlugin().pluginMyPlugins({ page, per_page: pluginConfig.numberPluginPerPage, status });
}

export function fetchPlugin(username: string, pluginName: string) {
  return getPlugin().pluginShow(username, pluginName);
}

export function savePlugin(username: string, pluginName: string, payload: UpdatePluginRequest) {
  return getPlugin().pluginUpdateBySlug(username, pluginName, payload);
}

export function destroyPlugin(id: string) {
  return getPlugin().pluginDestroy(id);
}

export function putStar(username: string, pluginName: string, starred: boolean) {
  return getStar().starStoreBySlug(username, pluginName, { starred });
}

export function pluginTrackView(id: string) {
  return getPlugin().pluginTrackView(id);
}
