/** Where the router wants the page to end up, applied by the page transition. */
let pendingScroll: { left: number; top: number } | null = null

export function setPendingScroll(position: { left: number; top: number } | null) {
  pendingScroll = position
}

export function takePendingScroll() {
  const target = pendingScroll
  pendingScroll = null

  return target
}
