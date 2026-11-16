import { computed, ref } from 'vue'
import { ApiError, fetchSlots, type ScheduleMeta, type Slot } from '../api/bookingApi'
import { addDaysToDateKey } from '../utils/datetime'

/** How many days the primary date strip shows, starting today. */
const STRIP_DAYS = 7

/**
 * The schedule as last returned by the server, plus the selected date.
 * The server list is the only source of truth for availability: after every
 * mutation we reload it instead of patching slots locally.
 */
export function useSchedule() {
  const slots = ref<Slot[]>([])
  const meta = ref<ScheduleMeta | null>(null)
  const selectedDate = ref<string | null>(null)

  /** True until the first successful load. */
  const isFirstLoad = ref(true)
  const isLoading = ref(false)
  /** Error of the most recent load attempt, or null if it succeeded. */
  const loadError = ref<string | null>(null)

  const timezone = computed(() => meta.value?.timezone ?? 'UTC')

  const allDates = computed(() => {
    const unique = new Set(slots.value.map((slot) => slot.date))
    return [...unique].sort()
  })

  // Today and the next six days: the current booking window.
  const stripDates = computed(() => {
    if (meta.value === null) {
      return []
    }
    const windowStart = meta.value.today
    const windowEnd = addDaysToDateKey(windowStart, STRIP_DAYS - 1)
    return allDates.value.filter((date) => date >= windowStart && date <= windowEnd)
  })

  // Retained dates outside the window (for example earlier seeded days).
  // They stay reachable through the "Другая дата" select.
  const otherDates = computed(() => {
    const inStrip = new Set(stripDates.value)
    return allDates.value.filter((date) => !inStrip.has(date))
  })

  const slotsForSelectedDate = computed(() => {
    return slots.value.filter((slot) => slot.date === selectedDate.value)
  })

  function findSlot(slotId: number): Slot | undefined {
    return slots.value.find((slot) => slot.id === slotId)
  }

  /** Loads the full schedule. Resolves to false instead of throwing. */
  async function load(): Promise<boolean> {
    isLoading.value = true
    try {
      const response = await fetchSlots()
      slots.value = response.data
      meta.value = response.meta
      loadError.value = null
      isFirstLoad.value = false
      keepSelectedDateValid()
      return true
    } catch (error) {
      loadError.value = error instanceof ApiError ? error.message : 'Не удалось загрузить расписание.'
      return false
    } finally {
      isLoading.value = false
    }
  }

  // Keep the user's date across reloads; fall back to today on first load.
  function keepSelectedDateValid(): void {
    const dates = allDates.value
    if (selectedDate.value !== null && dates.includes(selectedDate.value)) {
      return
    }

    const today = meta.value?.today ?? null
    if (today !== null && dates.includes(today)) {
      selectedDate.value = today
    } else {
      selectedDate.value = stripDates.value[0] ?? dates[0] ?? today
    }
  }

  return {
    slots,
    meta,
    timezone,
    selectedDate,
    stripDates,
    otherDates,
    slotsForSelectedDate,
    isFirstLoad,
    isLoading,
    loadError,
    load,
    findSlot,
  }
}

export type Schedule = ReturnType<typeof useSchedule>
