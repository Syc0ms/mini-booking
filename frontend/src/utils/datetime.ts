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

/** "16–22 ноября", or "28 ноября – 4 декабря" when the range spans two months. */
export function formatDateRange(firstDateKey: string, lastDateKey: string): string {
  const sameMonth = firstDateKey.slice(0, 7) === lastDateKey.slice(0, 7)
  const last = formatDateKey(lastDateKey, { day: 'numeric', month: 'long' })

  if (sameMonth) {
    return `${formatDayOfMonth(firstDateKey)}–${last}`
  }
  return `${formatDateKey(firstDateKey, { day: 'numeric', month: 'long' })} – ${last}`
}

/** "2026-11-18" plus N days, as a calendar-date calculation. */
export function addDaysToDateKey(dateKey: string, days: number): string {
  const date = dateKeyToUtcNoon(dateKey)
  date.setUTCDate(date.getUTCDate() + days)
  return date.toISOString().slice(0, 10)
}

function timeZoneNamePart(locale: string, timeZone: string, style: 'longGeneric' | 'shortOffset'): string {
  const parts = new Intl.DateTimeFormat(locale, { timeZone, timeZoneName: style }).formatToParts(new Date())
  return parts.find((part) => part.type === 'timeZoneName')?.value ?? ''
}

/** "Москва, UTC+3" for people; falls back to the IANA ID when Intl has no Russian name. */
export function describeTimezone(timeZone: string): string {
  const offset = timeZoneNamePart('en-US', timeZone, 'shortOffset').replace('GMT', 'UTC')

  // 'longGeneric' gives "Москва, стандартное время" in Chrome and Node alike
  // ('shortGeneric' falls back to English in Chrome). Keep the place only.
  const longName = timeZoneNamePart(LOCALE, timeZone, 'longGeneric')
  const placeName = longName.split(',')[0].trim()

  // Without a name Intl returns an offset like "GMT+03:00".
  const hasPlaceName = placeName !== '' && !placeName.startsWith('GMT')
  const place = hasPlaceName ? placeName : timeZone

  return `${place}, ${offset}`
}
