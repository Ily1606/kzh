import type { AxiosError } from 'axios';

export interface ApiErrorOverrides {
  [status: number]: string | undefined;
  default?: string;
}

export const DEFAULT_ERROR_MESSAGE = 'Something went wrong. Please try again.';
export const RATE_LIMIT_MESSAGE = 'Too many attempts. Please try again later.';
export const VALIDATION_FAILED_MESSAGE = 'Validation failed.';

/**
 * Maps an API error (AxiosError or unknown) to a user-facing error message.
 *
 * Resolution order:
 * 1. Status code override from `overrides[status]`
 * 2. 429 Rate limit message
 * 3. 422 Validation message (server message or "Validation failed.")
 * 4. Server-provided message (`response.data.message` if non-empty string)
 * 5. Fallback message (`overrides.default` or DEFAULT_ERROR_MESSAGE)
 *
 * Passing a string as `overrides` sets the fallback message.
 */
export function getApiErrorMessage(
  error: unknown,
  overrides?: ApiErrorOverrides | string,
): string {
  const opts: ApiErrorOverrides =
    typeof overrides === 'string' ? { default: overrides } : (overrides ?? {});

  const fallback = opts.default ?? DEFAULT_ERROR_MESSAGE;

  const err = error as AxiosError<{ message?: string }> | null | undefined;
  const status = err?.response?.status;
  const rawMessage = err?.response?.data?.message;
  const serverMessage =
    typeof rawMessage === 'string' && rawMessage.trim().length > 0
      ? rawMessage
      : undefined;

  if (status !== undefined) {
    if (opts[status] !== undefined) {
      return opts[status]!;
    }

    if (status === 429) {
      return RATE_LIMIT_MESSAGE;
    }

    if (status === 422) {
      return serverMessage || VALIDATION_FAILED_MESSAGE;
    }
  }

  return serverMessage || fallback;
}

