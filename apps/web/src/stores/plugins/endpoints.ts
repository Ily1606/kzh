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

export function fetchPlugin(id: string) {
  return getPlugin().pluginShow(id);
}

export function savePlugin(id: string, payload: UpdatePluginRequest) {
  return getPlugin().pluginUpdate(id, payload);
}

export function putStar(id: string, starred: boolean) {
  return getStar().starStore(id, { starred });
}

export function pluginTrackView(id: string) {
  return getPlugin().pluginTrackView(id);
}
