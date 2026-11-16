<script setup lang="ts">
import { computed } from 'vue'
import type { Slot } from '../api/bookingApi'
import { formatDateLong, formatDayOfMonth, formatTime, formatTimeRange, formatWeekdayShort } from '../utils/datetime'
import { STATUS_LABELS } from '../utils/slot'

const props = defineProps<{
  /** Date keys of the columns, in order. */
  days: string[]
  /** Any slots; only those on `days` are shown. */
  slots: Slot[]
  timezone: string
  today: string
}>()

const emit = defineEmits<{
  book: [slot: Slot]
  cancel: [slot: Slot]
  openDay: [date: string]
}>()

interface GridRow {
  /** Local start time in the schedule timezone, e.g. "09:00". */
  time: string
  /** One entry per column; `slot` is null when that day has no such slot. */
  cells: { day: string; slot: Slot | null }[]
}

// Rows come from the data rather than hardcoded hours, so they follow
// config/booking.php on the backend.
const rows = computed<GridRow[]>(() => {
  const visibleDays = new Set(props.days)
  const slotByDayAndTime = new Map<string, Slot>()
  const times = new Set<string>()

  for (const slot of props.slots) {
    if (!visibleDays.has(slot.date)) {
      continue
    }
    const time = formatTime(slot.starts_at, props.timezone)
    slotByDayAndTime.set(`${slot.date} ${time}`, slot)
    times.add(time)
  }

  // "09:00" < "10:00" as strings, because hours are zero-padded.
  const sortedTimes = [...times].sort()

  return sortedTimes.map((time) => ({
    time,
    cells: props.days.map((day) => ({
      day,
      slot: slotByDayAndTime.get(`${day} ${time}`) ?? null,
    })),
  }))
})

function describeCell(slot: Slot): string {
  const time = formatTimeRange(slot.starts_at, slot.ends_at, props.timezone)
  const date = formatDateLong(slot.date)

  if (slot.status === 'free') {
    return `Забронировать: ${date}, ${time}`
  }
  return `Отменить бронирование: ${date}, ${time}`
}

function onCellClick(slot: Slot): void {
  if (slot.status === 'free') {
    emit('book', slot)
  } else if (slot.status === 'booked') {
    emit('cancel', slot)
  }
}
</script>

<template>
  <div class="week-grid-scroller">
    <table class="week-grid">
      <thead>
        <tr>
          <th scope="col" class="week-grid__corner">
            <span class="visually-hidden">Время</span>
          </th>
          <th
            v-for="day in days"
            :key="day"
            scope="col"
            class="week-grid__day"
            :class="{ 'week-grid__day--today': day === today }"
          >
            <button
              type="button"
              class="day-button"
              :aria-label="`${formatDateLong(day)}: открыть списком`"
              @click="emit('openDay', day)"
            >
              <span class="day-button__weekday">{{ day === today ? 'сегодня' : formatWeekdayShort(day) }}</span>
              <span class="day-button__day tabular">{{ formatDayOfMonth(day) }}</span>
            </button>
          </th>
        </tr>
      </thead>

      <tbody>
        <tr v-for="row in rows" :key="row.time">
          <th scope="row" class="week-grid__time tabular">{{ row.time }}</th>

          <td
            v-for="cell in row.cells"
            :key="cell.day"
            class="week-grid__cell"
            :class="{ 'week-grid__cell--past': cell.slot?.status === 'past' }"
          >
            <!-- One <button> for free and booked, so focus stays on the
                 same element after the status changes. -->
            <button
              v-if="cell.slot && cell.slot.status !== 'past'"
              type="button"
              class="cell"
              :class="`cell--${cell.slot.status}`"
              :aria-label="describeCell(cell.slot)"
              @click="onCellClick(cell.slot)"
            >
              <span class="status-mark" :class="`status-mark--${cell.slot.status}`" aria-hidden="true"></span>
              {{ STATUS_LABELS[cell.slot.status] }}
            </button>
            <span v-else-if="cell.slot" class="cell cell--past">
              <span class="status-mark status-mark--past" aria-hidden="true"></span>
              {{ STATUS_LABELS.past }}
            </span>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

<style scoped>
/* On narrow screens the grid scrolls sideways inside its box; the time
   column stays pinned. The page itself never scrolls horizontally. */
.week-grid-scroller {
  overflow-x: auto;
  background: var(--panel-bg);
  border: 1px solid var(--divider);
  border-radius: var(--radius-panel);
}

.week-grid {
  width: 100%;
  min-width: 640px;
  border-collapse: collapse;
  table-layout: fixed;
}

.week-grid__corner,
.week-grid__time {
  position: sticky;
  left: 0;
  z-index: 1;
  width: 64px;
  background: var(--panel-bg);
}

.week-grid__day {
  padding: var(--space-2) var(--space-1);
  border-bottom: 1px solid var(--divider);
  font-weight: 400;
}

.day-button {
  display: flex;
  flex-direction: column;
  align-items: center;
  width: 100%;
  padding: var(--space-1) 0;
  border: 0;
  border-radius: var(--radius-control);
  background: transparent;
  color: var(--text);
  font: inherit;
  line-height: 1.25;
  cursor: pointer;
  transition: background-color var(--duration-fast) var(--ease);
}

.day-button:hover {
  background: var(--surface-raised);
}

.day-button__weekday {
  font-size: 12px;
  color: var(--text-secondary);
}

.day-button__day {
  font-size: 16px;
  font-weight: 500;
}

.week-grid__day--today .day-button__weekday,
.week-grid__day--today .day-button__day {
  color: var(--accent);
}

.week-grid__time {
  padding: 0 var(--space-3);
  font-size: 13px;
  text-align: right;
  color: var(--text-muted);
  vertical-align: middle;
}

.week-grid tbody tr + tr th,
.week-grid tbody tr + tr td {
  border-top: 1px solid var(--divider);
}

.week-grid__cell {
  height: 48px;
  padding: var(--space-1);
  border-left: 1px solid var(--divider);
}

.cell {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  width: 100%;
  height: 100%;
  min-height: 40px;
  border: 0;
  border-radius: var(--radius-control);
  background: transparent;
  font: inherit;
  font-size: 13px;
}

button.cell {
  cursor: pointer;
  transition:
    background-color var(--duration-fast) var(--ease),
    color var(--duration-fast) var(--ease);
}

/* Free hours are empty paper: a quiet label, the blue pen only on hover. */
.cell--free {
  color: var(--text-secondary);
}

.cell--free:hover {
  background: var(--accent-soft);
  color: var(--accent);
}

/* Booked hours are the only filled blocks on the grid. */
.cell--booked {
  background: var(--booked-fill);
  color: var(--booked-ink);
  font-weight: 500;
}

.cell--booked:hover {
  background: var(--booked-fill-hover);
}

/* Past hours fill the whole cell with grey, like time that is used up:
   white = free paper, grey band = gone, ochre block = taken. */
.week-grid__cell--past {
  background: var(--past-fill);
}

.cell--past {
  color: var(--text-muted);
}
</style>
