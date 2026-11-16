// Formatting helpers. Every instant is shown in the schedule timezone that
// the API reports, never in the browser's own timezone.

const LOCALE = 'ru-RU'

export function formatTime(iso: string, timeZone: string): string {
  return new Intl.DateTimeFormat(LOCALE, {
    hour: '2-digit',
    minute: '2-digit',
    hourCycle: 'h23',
    timeZone,
  }).format(new Date(iso))
}

export function formatTimeRange(startIso: string, endIso: string, timeZone: string): string {
  return `${formatTime(startIso, timeZone)}–${formatTime(endIso, timeZone)}`
}

// Date keys ("2026-11-18") are calendar dates already resolved by the server
// in the schedule timezone. To print their weekday/month we anchor them at
// noon UTC and format in UTC, which can never shift them to another day.
function dateKeyToUtcNoon(dateKey: string): Date {
  const [year, month, day] = dateKey.split('-').map(Number)
  return new Date(Date.UTC(year, month - 1, day, 12))
}

function formatDateKey(dateKey: string, options: Intl.DateTimeFormatOptions): string {
  return new Intl.DateTimeFormat(LOCALE, { ...options, timeZone: 'UTC' }).format(dateKeyToUtcNoon(dateKey))
}

/** "ср" */
export function formatWeekdayShort(dateKey: string): string {
  return formatDateKey(dateKey, { weekday: 'short' })
}

/** "18" */
export function formatDayOfMonth(dateKey: string): string {
  return formatDateKey(dateKey, { day: 'numeric' })
}

/** "среда, 18 ноября" */
export function formatDateLong(dateKey: string): string {
  return formatDateKey(dateKey, { weekday: 'long', day: 'numeric', month: 'long' })
}

/** "18 ноября 2026" */
export function formatDateWithYear(dateKey: string): string {
  return formatDateKey(dateKey, { day: 'numeric', month: 'long', year: 'numeric' })
}

/** "2026-11-18" plus N days, as a calendar-date calculation. */
export function addDaysToDateKey(dateKey: string, days: number): string {
  const date = dateKeyToUtcNoon(dateKey)
  date.setUTCDate(date.getUTCDate() + days)
  return date.toISOString().slice(0, 10)
}

/** Human name of a timezone offset, e.g. "Europe/Moscow, UTC+3". */
export function describeTimezone(timeZone: string): string {
  const parts = new Intl.DateTimeFormat('en-US', { timeZone, timeZoneName: 'shortOffset' }).formatToParts(new Date())
  const offset = parts.find((part) => part.type === 'timeZoneName')?.value ?? ''
  return `${timeZone}, ${offset.replace('GMT', 'UTC')}`
}
