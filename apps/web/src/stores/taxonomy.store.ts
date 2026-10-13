import { defineStore } from 'pinia'
import { ref } from 'vue'
import { getCategory, getTag } from '@/api/generated/endpoints'

/**
 * The categories and tags a plugin may carry.
 *
 * These come from the API rather than from the generated types: the API owns
 * the taxonomy, so a category added there shows up here on the next load
 * without a frontend rebuild. The lists are fetched once and shared, because
 * every form and filter asks for the same two.
 */
export const useTaxonomyStore = defineStore('taxonomy', () => {
  const categories = ref<string[]>([])
  const tags = ref<string[]>([])
  const isLoaded = ref(false)

  /**
   * A failure leaves the lists empty rather than propagating: the page still
   * works, and the API answers an unusable category or tag with a proper 422.
   * Nothing here should reject into an `onMounted` hook.
   */
  async function ensureLoaded(): Promise<void> {
    if (isLoaded.value) return

    try {
      const [categoryRes, tagRes] = await Promise.all([getCategory().categoryIndex(), getTag().tagIndex()])

      categories.value = (categoryRes.data ?? []).map((row) => row.name)
      tags.value = (tagRes.data ?? []).map((row) => row.name)
      isLoaded.value = true
    } catch {
      isLoaded.value = false
    }
  }

  return { categories, tags, isLoaded, ensureLoaded }
})