<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import Button from 'primevue/button'
import Message from 'primevue/message'
import Skeleton from 'primevue/skeleton'
import Toast from 'primevue/toast'
import { useToast } from 'primevue/usetoast'
import type { Slot } from './api/bookingApi'
import BookingDialog from './components/BookingDialog.vue'
import CancelDialog from './components/CancelDialog.vue'
import DateStrip from './components/DateStrip.vue'
import SlotList from './components/SlotList.vue'
import { useBookingActions, type CancelResult } from './composables/useBookingActions'
import { useSchedule } from './composables/useSchedule'
import { describeTimezone, formatDateLong, formatTimeRange } from './utils/datetime'

const schedule = useSchedule()
const actions = useBookingActions(schedule)
const toast = useToast()

const {
  meta,
  timezone,
  selectedDate,
  stripDates,
  otherDates,
  slotsForSelectedDate,
  isFirstLoad,
  isLoading,
  loadError,
} = schedule

const bookingSlot = ref<Slot | null>(null)
const isBookingOpen = ref(false)
const cancelSlot = ref<Slot | null>(null)
const isCancelOpen = ref(false)

const timezoneLabel = computed(() => describeTimezone(timezone.value))
const selectedDateLabel = computed(() => (selectedDate.value ? formatDateLong(selectedDate.value) : ''))

onMounted(() => {
  void schedule.load()
})

// The button that opened a dialog. After a mutation that row re-renders with
// a different button, so we fall back to the schedule heading.
let dialogTrigger: HTMLElement | null = null

function rememberTrigger(): void {
  dialogTrigger = document.activeElement instanceof HTMLElement ? document.activeElement : null
}

function restoreFocus(): void {
  if (dialogTrigger !== null && dialogTrigger.isConnected) {
    dialogTrigger.focus()
  } else {
    document.getElementById('schedule-heading')?.focus()
  }
  dialogTrigger = null
}

function openBooking(slot: Slot): void {
  rememberTrigger()
  bookingSlot.value = slot
  isBookingOpen.value = true
}

function openCancel(slot: Slot): void {
  rememberTrigger()
  cancelSlot.value = slot
  isCancelOpen.value = true
}

function describeSlot(slot: Slot): string {
  const date = formatDateLong(slot.date)
  const capitalizedDate = date.charAt(0).toUpperCase() + date.slice(1)
  return `${capitalizedDate}, ${formatTimeRange(slot.starts_at, slot.ends_at, timezone.value)}`
}

function warnScheduleNotRefreshed(): void {
  toast.add({
    severity: 'warn',
    summary: 'Расписание не обновилось',
    detail: 'Действие выполнено, но свежие данные получить не удалось. Нажмите «Обновить».',
    life: 8000,
  })
}

function onBooked(slot: Slot, scheduleRefreshed: boolean): void {
  toast.add({ severity: 'success', summary: 'Забронировано', detail: describeSlot(slot), life: 4000 })
  if (!scheduleRefreshed) {
    warnScheduleNotRefreshed()
  }
}

function onCancelled(slot: Slot, result: CancelResult): void {
  if (result.outcome === 'already_cancelled') {
    toast.add({
      severity: 'info',
      summary: 'Бронирование уже отменено',
      detail: 'Расписание обновлено.',
      life: 5000,
    })
    return
  }

  toast.add({ severity: 'success', summary: 'Бронирование отменено', detail: describeSlot(slot), life: 4000 })
  if (result.outcome === 'cancelled' && !result.scheduleRefreshed) {
    warnScheduleNotRefreshed()
  }
}
</script>

<template>
  <Toast position="top-center" />

  <main class="page">
    <header class="page-header">
      <h1>Переговорная</h1>
      <p class="page-header__subtitle">Бронирование на час, с 09:00 до 18:00 каждый день</p>
    </header>

    <!-- First load: skeleton, then either the schedule or a full error. -->
    <section v-if="isFirstLoad && loadError === null" class="schedule" aria-busy="true">
      <span class="visually-hidden" role="status">Загружаем расписание…</span>
      <Skeleton width="28rem" height="3.75rem" class="skeleton-strip" />
      <div class="skeleton-list">
        <Skeleton v-for="n in 9" :key="n" height="3.75rem" border-radius="0" />
      </div>
    </section>

    <section v-else-if="isFirstLoad" class="schedule">
      <Message severity="error">
        <div class="load-error">
          <span>Не удалось загрузить расписание. {{ loadError }}</span>
          <Button label="Повторить" size="small" :loading="isLoading" :disabled="isLoading" @click="schedule.load()" />
        </div>
      </Message>
    </section>

    <section v-else class="schedule" aria-labelledby="schedule-heading">
      <div class="schedule-toolbar">
        <DateStrip
          v-model="selectedDate"
          :strip-dates="stripDates"
          :other-dates="otherDates"
          :today="meta?.today ?? ''"
        />
      </div>

      <div class="schedule-heading">
        <h2 id="schedule-heading" tabindex="-1">{{ selectedDateLabel }}</h2>
        <div class="schedule-heading__meta">
          <span class="timezone-note">Время: {{ timezoneLabel }}</span>
          <Button
            label="Обновить"
            size="small"
            severity="secondary"
            variant="text"
            :loading="isLoading"
            :disabled="isLoading"
            @click="schedule.load()"
          />
        </div>
      </div>

      <Message v-if="loadError" severity="warn" class="refresh-error">
        <div class="load-error">
          <span>Не удалось обновить расписание, показаны прежние данные. {{ loadError }}</span>
          <Button label="Повторить" size="small" :loading="isLoading" :disabled="isLoading" @click="schedule.load()" />
        </div>
      </Message>

      <SlotList
        v-if="slotsForSelectedDate.length > 0"
        :slots="slotsForSelectedDate"
        :timezone="timezone"
        @book="openBooking"
        @cancel="openCancel"
      />
      <p v-else class="empty-state">
        {{ selectedDate ? 'На эту дату слотов нет.' : 'Расписание пустое. Заполните базу командой php artisan db:seed.' }}
      </p>
    </section>
  </main>

  <BookingDialog
    v-model:visible="isBookingOpen"
    :slot="bookingSlot"
    :timezone="timezone"
    :book="actions.book"
    @booked="onBooked"
    @after-hide="restoreFocus"
  />
  <CancelDialog
    v-model:visible="isCancelOpen"
    :slot="cancelSlot"
    :timezone="timezone"
    :cancel="actions.cancel"
    @cancelled="onCancelled"
    @after-hide="restoreFocus"
  />
</template>

<style scoped>
.page {
  max-width: 760px;
  margin: 0 auto;
  padding: var(--space-7) var(--space-5) var(--space-7);
}

.page-header {
  margin-bottom: var(--space-6);
}

.page-header h1 {
  font-size: 30px;
  line-height: 1.2;
  font-weight: 700;
  letter-spacing: -0.01em;
}

.page-header__subtitle {
  margin-top: var(--space-1);
  color: var(--text-muted);
}

.schedule {
  display: flex;
  flex-direction: column;
  gap: var(--space-4);
}

.schedule-heading {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: var(--space-3);
  flex-wrap: wrap;
  margin-top: var(--space-2);
}

.schedule-heading h2 {
  font-size: 18px;
  font-weight: 600;
}

.schedule-heading h2::first-letter {
  text-transform: uppercase;
}

.schedule-heading__meta {
  display: flex;
  align-items: center;
  gap: var(--space-2);
}

.timezone-note {
  font-size: 13px;
  color: var(--text-muted);
}

.load-error {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-3);
  flex-wrap: wrap;
  width: 100%;
}

.empty-state {
  padding: var(--space-6) var(--space-5);
  text-align: center;
  color: var(--text-muted);
  background: var(--panel-bg);
  border: 1px solid var(--divider);
  border-radius: var(--radius);
}

.skeleton-strip {
  max-width: 100%;
}

.skeleton-list {
  display: flex;
  flex-direction: column;
  gap: 1px;
  border-radius: var(--radius);
  overflow: hidden;
}

@media (max-width: 560px) {
  .page {
    padding: var(--space-5) var(--space-4) var(--space-6);
  }

  .page-header {
    margin-bottom: var(--space-5);
  }

  .page-header h1 {
    font-size: 26px;
  }
}
</style>
