/**
 * Date helpers — native Intl only, no extra dependency.
 * API timestamps arrive as ISO 8601 UTC (Laravel `toISOString()`), e.g.
 * "2026-08-14T10:23:45.000000Z".
 */

type DateInput = string | number | Date | null | undefined;

const PLACEHOLDER = "—";

function toDate(value: DateInput): Date | null {
  if (value === null || value === undefined || value === "") return null;
  const date = value instanceof Date ? value : new Date(value);
  return Number.isNaN(date.getTime()) ? null : date;
}

const dayFormatter = new Intl.DateTimeFormat("en-GB", {
  day: "numeric",
  month: "short",
  year: "numeric",
});

/** "14 Aug 2026" */
export function formatDate(value: DateInput): string {
  const date = toDate(value);
  return date ? dayFormatter.format(date) : PLACEHOLDER;
}

const RELATIVE_UNITS: Array<[Intl.RelativeTimeFormatUnit, number]> = [
  ["year", 365 * 24 * 60 * 60 * 1000],
  ["month", 30 * 24 * 60 * 60 * 1000],
  ["week", 7 * 24 * 60 * 60 * 1000],
  ["day", 24 * 60 * 60 * 1000],
  ["hour", 60 * 60 * 1000],
  ["minute", 60 * 1000],
];

const relativeFormatter = new Intl.RelativeTimeFormat("en", { numeric: "auto" });

/**
 * "3 days ago" for recent values, "14 Aug 2026" once it is far enough in the
 * past that a relative phrase stops being useful.
 */
export function formatRelativeDate(value: DateInput): string {
  const date = toDate(value);
  if (!date) return PLACEHOLDER;

  const diff = date.getTime() - Date.now();
  const absDiff = Math.abs(diff);

  if (absDiff < 60 * 1000) return "just now";
  if (absDiff > 365 * 24 * 60 * 60 * 1000) return formatDate(date);

  for (const [unit, ms] of RELATIVE_UNITS) {
    if (absDiff >= ms) {
      return relativeFormatter.format(Math.round(diff / ms), unit);
    }
  }

  return "just now";
}
