import { describe, it, expect } from 'vitest';
import {
  getApiErrorMessage,
  RATE_LIMIT_MESSAGE,
  VALIDATION_FAILED_MESSAGE,
  DEFAULT_ERROR_MESSAGE,
} from '@/utils/error';

describe('getApiErrorMessage', () => {
  it('returns default rate limit message on 429 status', () => {
    const error = { response: { status: 429 } };
    expect(getApiErrorMessage(error)).toBe(RATE_LIMIT_MESSAGE);
  });

  it('allows overriding 429 rate limit message', () => {
    const error = { response: { status: 429 } };
    expect(
      getApiErrorMessage(error, { 429: 'Custom rate limit message' }),
    ).toBe('Custom rate limit message');
  });

  it('returns response data message on 422 status', () => {
    const error = {
      response: {
        status: 422,
        data: { message: 'The email has already been taken.' },
      },
    };
    expect(getApiErrorMessage(error)).toBe('The email has already been taken.');
  });

  it('falls back to "Validation failed." on 422 if no message in response data', () => {
    const error = {
      response: {
        status: 422,
        data: {},
      },
    };
    expect(getApiErrorMessage(error)).toBe(VALIDATION_FAILED_MESSAGE);
  });

  it('allows overriding 422 message completely', () => {
    const error = {
      response: {
        status: 422,
        data: { message: 'Backend validation error' },
      },
    };
    expect(
      getApiErrorMessage(error, { 422: 'Incorrect email or password.' }),
    ).toBe('Incorrect email or password.');
  });

  it('allows overriding 401 status message', () => {
    const error = { response: { status: 401 } };
    expect(
      getApiErrorMessage(error, { 401: 'Incorrect email or password.' }),
    ).toBe('Incorrect email or password.');
  });

  it('returns default generic error message when error has no status or is unknown', () => {
    expect(getApiErrorMessage(new Error('Network error'))).toBe(
      DEFAULT_ERROR_MESSAGE,
    );
    expect(getApiErrorMessage(null)).toBe(DEFAULT_ERROR_MESSAGE);
    expect(getApiErrorMessage(undefined)).toBe(DEFAULT_ERROR_MESSAGE);
    expect(getApiErrorMessage({ response: { status: 500 } })).toBe(
      DEFAULT_ERROR_MESSAGE,
    );
  });

  it('allows overriding default message via object', () => {
    expect(
      getApiErrorMessage(new Error('Network error'), {
        default: "Couldn't create your account. Please try again.",
      }),
    ).toBe("Couldn't create your account. Please try again.");

    expect(
      getApiErrorMessage({ response: { status: 500 } }, {
        default: 'Failed to send reset link. Please try again.',
      }),
    ).toBe('Failed to send reset link. Please try again.');
  });

  it('allows overriding default message via string shortcut', () => {
    expect(
      getApiErrorMessage(
        new Error('Network error'),
        'Failed to reset password.',
      ),
    ).toBe('Failed to reset password.');
  });

  it('returns server response message for other status codes or errors', () => {
    expect(
      getApiErrorMessage(
        { response: { status: 500, data: { message: 'Database connection failed' } } },
        'Default fallback',
      ),
    ).toBe('Database connection failed');

    expect(
      getApiErrorMessage(
        { response: { data: { message: 'Something custom' } } },
        'Default fallback',
      ),
    ).toBe('Something custom');
  });

  it('ignores whitespace-only server message and falls back', () => {
    expect(
      getApiErrorMessage(
        { response: { data: { message: '   ' } } },
        'Default fallback',
      ),
    ).toBe('Default fallback');
  });
});

