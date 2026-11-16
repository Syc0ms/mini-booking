<script setup lang="ts">
import Button from 'primevue/button'
import type { Slot, SlotStatus } from '../api/bookingApi'
import { formatTimeRange } from '../utils/datetime'

defineProps<{
  slots: Slot[]
  timezone: string
}>()

const emit = defineEmits<{
  book: [slot: Slot]
  cancel: [slot: Slot]
}>()

const STATUS_LABELS: Record<SlotStatus, string> = {
  free: 'Свободно',
  booked: 'Занято',
  past: 'Прошло',
}
</script>

<template>
  <ul class="slot-list">
    <li v-for="slot in slots" :key="slot.id" class="slot-row" :class="`slot-row--${slot.status}`">
      <span class="slot-row__time tabular">{{ formatTimeRange(slot.starts_at, slot.ends_at, timezone) }}</span>

      <span class="slot-row__status">
        <span class="status-mark" aria-hidden="true"></span>
        {{ STATUS_LABELS[slot.status] }}
      </span>

      <span class="slot-row__action">
        <Button
          v-if="slot.status === 'free'"
          label="Забронировать"
          :aria-label="`Забронировать ${formatTimeRange(slot.starts_at, slot.ends_at, timezone)}`"
          size="small"
          @click="emit('book', slot)"
        />
        <Button
          v-else-if="slot.status === 'booked' && slot.booking_id !== null"
          label="Отменить"
          :aria-label="`Отменить бронирование ${formatTimeRange(slot.starts_at, slot.ends_at, timezone)}`"
          size="small"
          severity="secondary"
          variant="outlined"
          @click="emit('cancel', slot)"
        />
      </span>
    </li>
  </ul>
</template>

<style scoped>
.slot-list {
  list-style: none;
  margin: 0;
  padding: 0;
  background: var(--panel-bg);
  border: 1px solid var(--divider);
  border-radius: var(--radius);
}

.slot-row {
  display: grid;
  grid-template-columns: 9rem 1fr auto;
  align-items: center;
  gap: var(--space-4);
  min-height: 60px;
  padding: var(--space-2) var(--space-5);
}

.slot-row + .slot-row {
  border-top: 1px solid var(--divider);
}

.slot-row__time {
  font-size: 16px;
  font-weight: 500;
}

.slot-row__status {
  display: inline-flex;
  align-items: center;
  gap: var(--space-2);
  font-size: 14px;
  font-weight: 500;
}

/* Shape differs per status as well as color: ring, filled dot, dash. */
.status-mark {
  width: 10px;
  height: 10px;
  border-radius: 50%;
  flex: none;
}

.slot-row--free .slot-row__status {
  color: var(--status-free);
}

.slot-row--free .status-mark {
  border: 2px solid currentColor;
}

.slot-row--booked {
  background: #fbf7ef;
}

.slot-row--booked .slot-row__status {
  color: var(--status-booked);
}

.slot-row--booked .status-mark {
  background: currentColor;
}

.slot-row--past {
  color: var(--status-past);
}

.slot-row--past .status-mark {
  height: 2px;
  border-radius: 1px;
  background: currentColor;
}

.slot-row__action {
  display: flex;
  justify-content: flex-end;
  min-width: 140px;
}

.slot-row__action :deep(.p-button) {
  min-width: 140px;
}

@media (max-width: 560px) {
  .slot-row {
    grid-template-columns: 1fr auto;
    grid-template-areas:
      'time action'
      'status action';
    row-gap: 2px;
    column-gap: var(--space-3);
    padding: var(--space-3) var(--space-4);
  }

  .slot-row__time {
    grid-area: time;
  }

  .slot-row__status {
    grid-area: status;
    font-size: 13px;
  }

  .slot-row__action {
    grid-area: action;
    min-width: 0;
  }

  .slot-row__action :deep(.p-button) {
    min-width: 0;
  }
}
</style>
