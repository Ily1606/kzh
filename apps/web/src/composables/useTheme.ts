import { useDark } from '@vueuse/core'

/**
 * Theme is stored per visitor and starts from the OS preference. The class
 * lands on <html>, which is what the `dark` custom variant in style.css reads.
 */
export function useTheme() {
  const isDark = useDark({
    attribute: 'class',
    valueDark: 'dark',
    valueLight: '',
    storageKey: 'dsh-theme',
  })

  function toggle() {
    isDark.value = !isDark.value
  }

  return { isDark, toggle }
}
