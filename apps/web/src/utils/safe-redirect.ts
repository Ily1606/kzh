const AUTH_PAGES = new Set([
  '/login',
  '/register',
  '/forgot-password',
  '/reset-password',
]);

export function safeRedirect(redirectPath: unknown, fallback = '/'): string {

  if (typeof redirectPath !== 'string') {
    return fallback
  }

  const trimmed = redirectPath.trim();

  if (!trimmed.startsWith('/') || trimmed.startsWith('//') || trimmed.startsWith('/\\')) {
    return fallback;
  }

  const pathname = trimmed.split('?')[0].split('#')[0];
  if (AUTH_PAGES.has(pathname)) {
    return fallback;
  }

  return trimmed;
}
