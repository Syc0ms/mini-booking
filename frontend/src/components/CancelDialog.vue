<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import Message from 'primevue/message'
import type { Slot } from '../api/bookingApi'
import type { CancelResult } from '../composables/useBookingActions'
import { formatSlotLabel } from '../utils/slot'

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

const slotLabel = computed(() => (props.slot === null ? '' : formatSlotLabel(props.slot, props.timezone)))

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
      <div class="cancel-body__summary">
        <p class="cancel-body__slot tabular">
          <span class="status-mark status-mark--booked" aria-hidden="true"></span>
          <span class="cancel-body__slot-text">{{ slotLabel }}</span>
        </p>
        <p class="cancel-body__hint">Час снова станет свободным для бронирования</p>
      </div>

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
  gap: var(--space-4);
}

.cancel-body__summary {
  display: flex;
  flex-direction: column;
  gap: var(--space-1);
}

.cancel-body__slot {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  font-weight: 500;
}

.cancel-body__hint {
  color: var(--text-secondary);
}

.cancel-body__actions {
  display: flex;
  justify-content: flex-end;
  gap: var(--space-2);
  margin-top: var(--space-2);
}
</style>
