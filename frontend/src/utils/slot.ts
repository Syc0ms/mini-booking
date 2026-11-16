import type { Slot, SlotStatus } from '../api/bookingApi'
import { formatDateLong, formatTimeRange } from './datetime'

export const STATUS_LABELS: Record<SlotStatus, string> = {
  free: 'Свободно',
  booked: 'Занято',
  past: 'Прошло',
}

/** "Среда, 18 ноября, 10:00–11:00" */
export function formatSlotLabel(slot: Slot, timeZone: string): string {
  const date = formatDateLong(slot.date)
  const capitalizedDate = date.charAt(0).toUpperCase() + date.slice(1)
  return `${capitalizedDate}, ${formatTimeRange(slot.starts_at, slot.ends_at, timeZone)}`
}
