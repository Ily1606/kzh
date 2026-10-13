import { describe, it, expect, vi, beforeEach } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import { useTaxonomyStore } from '@/stores/taxonomy.store';
import { TAXONOMY_CATEGORIES, TAXONOMY_TAGS } from '../../helpers/taxonomy';

const mockCategoryIndex = vi.fn();
const mockTagIndex = vi.fn();

vi.mock('@/api/generated/endpoints', () => ({
  getCategory: () => ({ categoryIndex: mockCategoryIndex }),
  getTag: () => ({ tagIndex: mockTagIndex }),
}));

const emptyLists = () => {
  mockCategoryIndex.mockResolvedValue({
    data: TAXONOMY_CATEGORIES.map((name, index) => ({ id: index + 1, name })),
  });
  mockTagIndex.mockResolvedValue({
    data: TAXONOMY_TAGS.map((name, index) => ({ id: index + 1, name })),
  });
};

describe('taxonomy.store', () => {
  beforeEach(() => {
    setActivePinia(createPinia());
    vi.clearAllMocks();
    emptyLists();
  });

  it('flattens the two endpoints into plain name lists', async () => {
    const store = useTaxonomyStore();

    await store.ensureLoaded();

    expect(store.categories).toEqual(TAXONOMY_CATEGORIES);
    expect(store.tags).toEqual(TAXONOMY_TAGS);
  });

  it('asks the API once, however many callers want the lists', async () => {
    const store = useTaxonomyStore();

    await store.ensureLoaded();
    await store.ensureLoaded();

    expect(mockCategoryIndex).toHaveBeenCalledTimes(1);
    expect(mockTagIndex).toHaveBeenCalledTimes(1);
  });

  it('leaves the lists empty and stays retryable when an endpoint fails', async () => {
    mockCategoryIndex.mockRejectedValue(new Error('offline'));
    const store = useTaxonomyStore();

    await store.ensureLoaded();

    expect(store.categories).toEqual([]);

    emptyLists();
    await store.ensureLoaded();

    expect(store.categories).toEqual(TAXONOMY_CATEGORIES);
  });
});