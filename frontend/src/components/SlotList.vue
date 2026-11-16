<script setup lang="ts">
import Button from 'primevue/button'
import type { Slot } from '../api/bookingApi'
import { formatTimeRange } from '../utils/datetime'
import { STATUS_LABELS } from '../utils/slot'

defineProps<{
  slots: Slot[]
  timezone: string
}>()

const emit = defineEmits<{
  book: [slot: Slot]
  cancel: [slot: Slot]
}>()
</script>

<template>
  <ul class="slot-list">
    <li v-for="slot in slots" :key="slot.id" class="slot-row" :class="`slot-row--${slot.status}`">
      <span class="slot-row__time tabular">{{ formatTimeRange(slot.starts_at, slot.ends_at, timezone) }}</span>

      <span class="slot-row__status">
        <span class="status-mark" :class="`status-mark--${slot.status}`" aria-hidden="true"></span>
        {{ STATUS_LABELS[slot.status] }}
      </span>

      <span class="slot-row__action">
        <!-- Outlined, not filled: nine filled buttons per day would drown
             the one filled action on the screen, the dialog's submit. -->
        <Button
          v-if="slot.status === 'free'"
          label="Забронировать"
          :aria-label="`Забронировать ${formatTimeRange(slot.starts_at, slot.ends_at, timezone)}`"
          size="small"
          variant="outlined"
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
  overflow: hidden;
  background: var(--panel-bg);
  border: 1px solid var(--divider);
  border-radius: var(--radius-panel);
}

.slot-row {
  display: grid;
  grid-template-columns: 144px 1fr auto;
  align-items: center;
  gap: var(--space-4);
  min-height: 56px;
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
  color: var(--text-secondary);
}

.slot-row--booked {
  background: var(--booked-row);
}

.slot-row--booked .slot-row__status {
  color: var(--booked-ink);
  font-weight: 500;
}

.slot-row--past {
  background: var(--past-fill);
}

.slot-row--past .slot-row__time,
.slot-row--past .slot-row__status {
  color: var(--text-muted);
}

.slot-row--past .slot-row__time {
  font-weight: 400;
}

.slot-row__action {
  display: flex;
  justify-content: flex-end;
  min-width: 136px;
}

.slot-row__action :deep(.p-button) {
  min-width: 136px;
}

@media (max-width: 560px) {
  .slot-row {
    grid-template-columns: 1fr auto;
    grid-template-areas:
      'time action'
      'status action';
    row-gap: var(--space-1);
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
