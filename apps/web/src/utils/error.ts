import type { AxiosError } from 'axios';

export interface ApiErrorOverrides {
  [status: number]: string | undefined;
  default?: string;
  fallback?: string;
}

export const DEFAULT_ERROR_MESSAGE = 'Something went wrong. Please try again.';
export const RATE_LIMIT_MESSAGE = 'Too many attempts. Please try again later.';
export const VALIDATION_FAILED_MESSAGE = 'Validation failed.';

/**
 * Maps an API error (AxiosError or unknown) to a user-facing error message.
 *
 * Status code handling:
 * - 429: Rate limit message ("Too many attempts. Please try again later.")
 * - 422: Validation message (`err.response?.data?.message || "Validation failed."`)
 * - Specific status overrides: Checked via `overrides[status]`
 * - Fallback/default: `overrides.default ?? overrides.fallback ?? "Something went wrong. Please try again."`
 *
 * Passing a string as `overrides` sets the custom fallback message.
 */
export function getApiErrorMessage(
  error: unknown,
  overrides?: ApiErrorOverrides | string,
): string {
  const opts: ApiErrorOverrides =
    typeof overrides === 'string' ? { default: overrides } : (overrides ?? {});

  const fallback = opts.default ?? opts.fallback ?? DEFAULT_ERROR_MESSAGE;

  const err = error as AxiosError<{ message?: string }> | null | undefined;
  const status = err?.response?.status;

  if (status !== undefined) {
    if (opts[status] !== undefined) {
      return opts[status]!;
    }

    if (status === 429) {
      return RATE_LIMIT_MESSAGE;
    }

    if (status === 422) {
      return err?.response?.data?.message || VALIDATION_FAILED_MESSAGE;
    }
  }

  return fallback;
}

