<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import Message from 'primevue/message'
import type { Slot } from '../api/bookingApi'
import type { CancelResult } from '../composables/useBookingActions'
import { formatDateLong, formatTimeRange } from '../utils/datetime'

const props = defineProps<{
  slot: Slot | null
  timezone: string
  cancel: (bookingId: number, slotId: number) => Promise<CancelResult>
}>()

const visible = defineModel<boolean>('visible', { required: true })

const emit = defineEmits<{
  'after-hide': []
  cancelled: [slot: Slot, result: CancelResult]
}>()

const isSubmitting = ref(false)
const errorText = ref<string | null>(null)
const canRetry = ref(true)

const slotLabel = computed(() => {
  if (props.slot === null) {
    return ''
  }
  const time = formatTimeRange(props.slot.starts_at, props.slot.ends_at, props.timezone)
  return `${formatDateLong(props.slot.date)}, ${time}`
})

watch(visible, (isOpen) => {
  if (isOpen) {
    errorText.value = null
    canRetry.value = true
  }
})

async function confirm(): Promise<void> {
  const slot = props.slot
  if (slot === null || slot.booking_id === null || isSubmitting.value) {
    return
  }

  isSubmitting.value = true
  errorText.value = null
  const result = await props.cancel(slot.booking_id, slot.id)
  isSubmitting.value = false

  if (result.outcome === 'unknown') {
    errorText.value = result.message
    canRetry.value = result.canRetry
    return
  }

  visible.value = false
  emit('cancelled', slot, result)
}
</script>

<template>
  <Dialog
    v-model:visible="visible"
    modal
    header="Отменить бронирование?"
    :closable="!isSubmitting"
    :close-on-escape="!isSubmitting"
    :draggable="false"
    :style="{ width: '26rem' }"
    :breakpoints="{ '480px': 'calc(100vw - 32px)' }"
    @after-hide="emit('after-hide')"
  >
    <div class="cancel-body">
      <p class="cancel-body__slot tabular">{{ slotLabel }}</p>
      <p class="cancel-body__hint">Слот снова станет свободным для бронирования.</p>

      <Message v-if="errorText" severity="error" size="small">{{ errorText }}</Message>

      <div class="cancel-body__actions">
        <Button
          :label="canRetry ? 'Не отменять' : 'Закрыть'"
          severity="secondary"
          variant="text"
          autofocus
          :disabled="isSubmitting"
          @click="visible = false"
        />
        <Button
          v-if="canRetry"
          :label="errorText ? 'Повторить' : 'Отменить бронирование'"
          severity="danger"
          :loading="isSubmitting"
          :disabled="isSubmitting"
          @click="confirm"
        />
      </div>
    </div>
  </Dialog>
</template>

<style scoped>
.cancel-body {
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
}

.cancel-body__slot {
  font-weight: 600;
}

.cancel-body__slot::first-letter {
  text-transform: uppercase;
}

.cancel-body__hint {
  color: var(--text-muted);
}

.cancel-body__actions {
  display: flex;
  justify-content: flex-end;
  gap: var(--space-2);
  margin-top: var(--space-2);
}
</style>
