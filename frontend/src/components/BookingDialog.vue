<script setup lang="ts">
import { computed, nextTick, reactive, ref, watch } from 'vue'
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import Message from 'primevue/message'
import type { BookingInput, FieldErrors, Slot } from '../api/bookingApi'
import type { BookResult } from '../composables/useBookingActions'
import { formatSlotLabel } from '../utils/slot'

const props = defineProps<{
  slot: Slot | null
  timezone: string
  book: (input: BookingInput) => Promise<BookResult>
}>()

const visible = defineModel<boolean>('visible', { required: true })

const emit = defineEmits<{
  'after-hide': []
  booked: [slot: Slot, scheduleRefreshed: boolean]
}>()

// The draft survives errors, retries and closing the dialog without booking.
// It is cleared after a successful booking, so on a shared office computer the
// next person does not find someone else's name and email in the form.
const form = reactive({ name: '', email: '' })
const fieldErrors = ref<FieldErrors>({})
const notice = ref<{ severity: 'error' | 'warn'; text: string } | null>(null)
/** Set when this slot can no longer be booked (409 or a lost response). */
const isBlocked = ref(false)
const isSubmitting = ref(false)
const hasFailedOnce = ref(false)

const slotLabel = computed(() => (props.slot === null ? '' : formatSlotLabel(props.slot, props.timezone)))

watch(visible, (isOpen) => {
  if (isOpen) {
    fieldErrors.value = {}
    notice.value = null
    isBlocked.value = false
    hasFailedOnce.value = false
  }
})

// Only the basic shape; the server's RFC check is the real one (and allows
// addresses without a dot in the domain, such as ivan@localhost).
const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+$/

function validateInBrowser(): FieldErrors {
  const errors: FieldErrors = {}
  if (form.name.trim() === '') {
    errors.customer_name = 'Укажите имя'
  }
  if (form.email.trim() === '') {
    errors.customer_email = 'Укажите email'
  } else if (!EMAIL_PATTERN.test(form.email.trim())) {
    errors.customer_email = 'Проверьте формат email'
  }
  return errors
}

async function focusFirstInvalidField(): Promise<void> {
  await nextTick()
  const fieldId = fieldErrors.value.customer_name ? 'booking-name' : 'booking-email'
  document.getElementById(fieldId)?.focus()
}

async function submit(): Promise<void> {
  if (props.slot === null || isSubmitting.value || isBlocked.value) {
    return
  }

  notice.value = null
  fieldErrors.value = validateInBrowser()
  if (Object.keys(fieldErrors.value).length > 0) {
    await focusFirstInvalidField()
    return
  }

  isSubmitting.value = true
  const slot = props.slot
  const result = await props.book({
    slot_id: slot.id,
    customer_name: form.name.trim(),
    customer_email: form.email.trim(),
  })
  isSubmitting.value = false

  handleResult(result, slot)
}

function handleResult(result: BookResult, slot: Slot): void {
  if (result.outcome === 'booked') {
    clearDraft()
    visible.value = false
    emit('booked', slot, result.scheduleRefreshed)
    return
  }

  if (result.outcome === 'invalid') {
    fieldErrors.value = result.fieldErrors
    // slot_id errors have no input of their own.
    if (result.fieldErrors.slot_id) {
      notice.value = { severity: 'error', text: result.fieldErrors.slot_id }
      isBlocked.value = true
    } else {
      void focusFirstInvalidField()
    }
    return
  }

  if (result.outcome === 'conflict') {
    const explanation =
      result.reason === 'slot_past'
        ? 'Этот слот уже начался. Расписание обновлено — выберите другое время.'
        : 'Этот слот только что занял кто-то другой. Расписание обновлено — выберите другое время.'
    notice.value = { severity: 'warn', text: explanation }
    isBlocked.value = true
    return
  }

  hasFailedOnce.value = true
  isBlocked.value = !result.canRetry
  if (result.canRetry) {
    notice.value = { severity: 'error', text: result.message }
  } else {
    // The lost request may have created the booking, so treat the draft as
    // after a success; the message says to check the schedule.
    notice.value = { severity: 'warn', text: result.message }
    clearDraft()
  }
}

function clearDraft(): void {
  form.name = ''
  form.email = ''
}
</script>

<template>
  <Dialog
    v-model:visible="visible"
    modal
    header="Бронирование"
    :closable="!isSubmitting"
    :close-on-escape="!isSubmitting"
    :draggable="false"
    :style="{ width: '26rem' }"
    :breakpoints="{ '480px': 'calc(100vw - 32px)' }"
    @after-hide="emit('after-hide')"
  >
    <form novalidate class="booking-form" @submit.prevent="submit">
      <p class="booking-form__slot tabular">
        <span class="status-mark status-mark--free" aria-hidden="true"></span>
        <span class="booking-form__slot-text">{{ slotLabel }}</span>
      </p>

      <div class="field">
        <label for="booking-name">Имя</label>
        <InputText
          id="booking-name"
          v-model="form.name"
          autocomplete="name"
          autofocus
          :disabled="isSubmitting"
          :invalid="Boolean(fieldErrors.customer_name)"
          :aria-invalid="Boolean(fieldErrors.customer_name)"
          aria-describedby="booking-name-error"
          maxlength="100"
          fluid
        />
        <small id="booking-name-error" class="field__error">{{ fieldErrors.customer_name }}</small>
      </div>

      <div class="field">
        <label for="booking-email">Email</label>
        <InputText
          id="booking-email"
          v-model="form.email"
          type="email"
          inputmode="email"
          autocomplete="email"
          :disabled="isSubmitting"
          :invalid="Boolean(fieldErrors.customer_email)"
          :aria-invalid="Boolean(fieldErrors.customer_email)"
          aria-describedby="booking-email-error"
          maxlength="255"
          fluid
        />
        <small id="booking-email-error" class="field__error">{{ fieldErrors.customer_email }}</small>
      </div>

      <Message v-if="notice" :severity="notice.severity" size="small" class="booking-form__notice">
        {{ notice.text }}
      </Message>

      <div class="booking-form__actions">
        <Button
          type="button"
          :label="isBlocked ? 'Закрыть' : 'Отмена'"
          severity="secondary"
          variant="text"
          :disabled="isSubmitting"
          @click="visible = false"
        />
        <Button
          v-if="!isBlocked"
          type="submit"
          :label="hasFailedOnce ? 'Повторить' : 'Забронировать'"
          :loading="isSubmitting"
          :disabled="isSubmitting"
        />
      </div>
    </form>
  </Dialog>
</template>

<style scoped>
.booking-form {
  display: flex;
  flex-direction: column;
  gap: var(--space-4);
}

.booking-form__slot {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  color: var(--text-secondary);
}

.field {
  display: flex;
  flex-direction: column;
  gap: var(--space-1);
}

.field label {
  font-size: 14px;
  font-weight: 500;
}

/* Kept in the DOM for aria-describedby, takes no space until it has text. */
.field__error {
  font-size: 13px;
  color: var(--danger);
}

.field__error:empty {
  display: none;
}

.booking-form__actions {
  display: flex;
  justify-content: flex-end;
  gap: var(--space-2);
  margin-top: var(--space-2);
}
</style>
