/** One query's ids plus its page state. The objects live in `entities`. */
export interface PluginQueryState {
  ids: string[];
  page: number;
  total: number;
  lastPage: number;
}

export const emptyQuery = (): PluginQueryState => ({ ids: [], page: 1, total: 0, lastPage: 1 });

/** Each call takes a ticket; only the newest ticket may write its result. */
export function latestOnly() {
  let last = 0;

  return () => {
    const id = ++last;

    return () => id === last;
  };
}

export { getApiErrorMessage as errorMessage } from '@/utils/error';