<script setup lang="ts">
import { computed, nextTick, onMounted, ref } from 'vue'
import Button from 'primevue/button'
import Message from 'primevue/message'
import SelectButton from 'primevue/selectbutton'
import Skeleton from 'primevue/skeleton'
import Toast from 'primevue/toast'
import { useToast } from 'primevue/usetoast'
import type { Slot } from './api/bookingApi'
import BookingDialog from './components/BookingDialog.vue'
import CancelDialog from './components/CancelDialog.vue'
import DateStrip from './components/DateStrip.vue'
import SlotList from './components/SlotList.vue'
import WeekGrid from './components/WeekGrid.vue'
import { useBookingActions, type CancelResult } from './composables/useBookingActions'
import { useSchedule } from './composables/useSchedule'
import { describeTimezone, formatDateLong, formatDateRange } from './utils/datetime'
import { formatSlotLabel } from './utils/slot'

const schedule = useSchedule()
const actions = useBookingActions(schedule)
const toast = useToast()

const {
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
} = schedule

const bookingSlot = ref<Slot | null>(null)
const isBookingOpen = ref(false)
const cancelSlot = ref<Slot | null>(null)
const isCancelOpen = ref(false)

type ScheduleView = 'list' | 'calendar'

const VIEW_OPTIONS: { label: string; value: ScheduleView }[] = [
  { label: 'Список', value: 'list' },
  { label: 'Календарь', value: 'calendar' },
]

// The day list is the main view; the week grid is an alternative layout of
// the same data, with the same dialogs.
const view = ref<ScheduleView>('list')

const timezoneLabel = computed(() => describeTimezone(timezone.value))

const headingLabel = computed(() => {
  if (view.value === 'calendar') {
    const days = stripDates.value
    if (days.length === 0) {
      return 'Неделя'
    }
    return `Неделя: ${formatDateRange(days[0], days[days.length - 1])}`
  }
  return selectedDate.value ? formatDateLong(selectedDate.value) : ''
})

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

// Clicking a day in the week grid opens that day as a list.
async function openDayInList(date: string): Promise<void> {
  selectedDate.value = date
  view.value = 'list'
  await nextTick()
  document.getElementById('schedule-heading')?.focus()
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
  toast.add({ severity: 'success', summary: 'Забронировано', detail: formatSlotLabel(slot, timezone.value), life: 4000 })
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

  toast.add({ severity: 'success', summary: 'Бронирование отменено', detail: formatSlotLabel(slot, timezone.value), life: 4000 })
  if (result.outcome === 'cancelled' && !result.scheduleRefreshed) {
    warnScheduleNotRefreshed()
  }
}
</script>

<template>
  <Toast position="top-center" />

  <main class="page">
    <header class="page-header">
      <div>
        <h1>Переговорная</h1>
        <p class="page-header__subtitle">Бронирование на час, с 09:00 до 18:00 каждый день</p>
      </div>
      <SelectButton
        v-model="view"
        :options="VIEW_OPTIONS"
        option-label="label"
        option-value="value"
        :allow-empty="false"
        aria-label="Вид расписания"
        class="segmented view-switch"
      />
    </header>

    <!-- First load: skeleton, then either the schedule or a full error. -->
    <section v-if="isFirstLoad && loadError === null" class="schedule" aria-busy="true">
      <span class="visually-hidden" role="status">Загружаем расписание…</span>
      <Skeleton width="34rem" height="3.75rem" border-radius="12px" class="skeleton-strip" />
      <div class="skeleton-list">
        <Skeleton v-for="n in 9" :key="n" height="3.5rem" border-radius="0" />
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
      <div v-if="view === 'list'" class="schedule-toolbar">
        <DateStrip
          v-model="selectedDate"
          :strip-dates="stripDates"
          :other-dates="otherDates"
          :today="meta?.today ?? ''"
        />
      </div>

      <div class="schedule-heading">
        <h2 id="schedule-heading" tabindex="-1">{{ headingLabel }}</h2>
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

      <template v-if="view === 'list'">
        <SlotList
          v-if="slotsForSelectedDate.length > 0"
          :slots="slotsForSelectedDate"
          :timezone="timezone"
          @book="openBooking"
          @cancel="openCancel"
        />
        <p v-else-if="selectedDate" class="empty-state">На эту дату слотов нет</p>
        <p v-else class="empty-state">Расписание пустое. Заполните базу командой php artisan db:seed.</p>
      </template>

      <template v-else>
        <div v-if="stripDates.length > 0" class="calendar">
          <p class="calendar__hint">Нажмите на свободный час, чтобы забронировать, на занятый — чтобы отменить</p>
          <WeekGrid
            :days="stripDates"
            :slots="slots"
            :timezone="timezone"
            :today="meta?.today ?? ''"
            @book="openBooking"
            @cancel="openCancel"
            @open-day="openDayInList"
          />
        </div>
        <p v-else class="empty-state">На эту неделю слотов нет. Заполните базу командой php artisan db:seed.</p>
      </template>
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
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: var(--space-4);
  margin-bottom: var(--space-6);
}

.page-header h1 {
  font-size: 30px;
  line-height: 36px;
  font-weight: 600;
}

.page-header__subtitle {
  margin-top: var(--space-1);
  color: var(--text-secondary);
}

.view-switch :deep(.p-togglebutton) {
  padding: 6px var(--space-3);
}

/* 24 between the date strip and the heading, 16 from the heading down. */
.schedule {
  display: flex;
  flex-direction: column;
  gap: var(--space-4);
}

.schedule-toolbar {
  margin-bottom: var(--space-2);
}

.schedule-heading {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: var(--space-3);
  flex-wrap: wrap;
}

.schedule-heading h2 {
  font-size: 18px;
  font-weight: 500;
}

.schedule-heading h2::first-letter {
  text-transform: uppercase;
}

.schedule-heading__meta {
  display: flex;
  align-items: center;
  gap: var(--space-2);
}

/* The hint belongs to the grid, so it sits closer to it than to the heading. */
.calendar {
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
}

.calendar__hint {
  font-size: 13px;
  color: var(--text-muted);
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
  border-radius: var(--radius-panel);
}

.skeleton-strip {
  max-width: 100%;
}

.skeleton-list {
  display: flex;
  flex-direction: column;
  gap: 1px;
  border-radius: var(--radius-panel);
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
