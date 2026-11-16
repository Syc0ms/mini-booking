<script setup lang="ts">
import { computed } from 'vue'
import SelectButton from 'primevue/selectbutton'
import Select from 'primevue/select'
import { formatDateLong, formatDateWithYear, formatDayOfMonth, formatWeekdayShort } from '../utils/datetime'

const props = defineProps<{
  /** Dates of the current seven-day window, shown as the strip. */
  stripDates: string[]
  /** Any other retained dates, reachable through the select. */
  otherDates: string[]
  today: string
}>()

const selectedDate = defineModel<string | null>({ required: true })

const stripOptions = computed(() =>
  props.stripDates.map((date) => ({
    value: date,
    weekday: date === props.today ? 'сегодня' : formatWeekdayShort(date),
    day: formatDayOfMonth(date),
    fullLabel: formatDateLong(date),
  })),
)

const otherOptions = computed(() =>
  props.otherDates.map((date) => ({ value: date, label: formatDateWithYear(date) })),
)

// The select shows a value only while one of its dates is active.
const otherSelection = computed({
  get: () => (selectedDate.value !== null && props.otherDates.includes(selectedDate.value) ? selectedDate.value : null),
  set: (date: string | null) => {
    if (date !== null) {
      selectedDate.value = date
    }
  },
})
</script>

<template>
  <div class="date-filter">
    <div class="date-strip-scroller">
      <SelectButton
        v-model="selectedDate"
        :options="stripOptions"
        option-value="value"
        option-label="fullLabel"
        :allow-empty="false"
        aria-label="Дата"
        class="segmented date-strip"
      >
        <template #option="{ option }">
          <span class="date-chip" aria-hidden="true">
            <span class="date-chip__weekday">{{ option.weekday }}</span>
            <span class="date-chip__day tabular">{{ option.day }}</span>
          </span>
          <span class="visually-hidden">{{ option.fullLabel }}</span>
        </template>
      </SelectButton>
    </div>

    <Select
      v-if="otherOptions.length > 0"
      v-model="otherSelection"
      :options="otherOptions"
      option-value="value"
      option-label="label"
      placeholder="Другая дата"
      aria-label="Другая дата"
      class="other-dates"
    />
  </div>
</template>

<style scoped>
.date-filter {
  display: flex;
  align-items: center;
  gap: var(--space-3);
  flex-wrap: wrap;
}

/* On narrow screens only the strip scrolls, never the page. The strip's own
   4px padding leaves room for the focus ring of the first and last chip. */
.date-strip-scroller {
  max-width: 100%;
  overflow-x: auto;
  scrollbar-width: none;
}

.date-strip-scroller::-webkit-scrollbar {
  display: none;
}

.date-strip {
  flex-wrap: nowrap;
}

/* Every chip the same width, "сегодня" included. */
.date-strip :deep(.p-togglebutton) {
  flex: 0 0 auto;
  width: 72px;
  padding: var(--space-2) 0;
}

.date-chip {
  display: flex;
  flex-direction: column;
  align-items: center;
  line-height: 1.25;
}

.date-chip__weekday {
  font-size: 12px;
  font-weight: 400;
}

.date-chip__day {
  font-size: 16px;
  font-weight: 500;
  color: var(--text);
}

.date-strip :deep(.p-togglebutton-checked) .date-chip__day {
  color: inherit;
}

.other-dates {
  min-width: 192px;
}
</style>
