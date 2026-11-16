import {
  ApiError,
  cancelBooking,
  createBooking,
  type BookingInput,
  type ConflictReason,
  type FieldErrors,
} from '../api/bookingApi'
import type { Schedule } from './useSchedule'

export type BookResult =
  | { outcome: 'booked'; scheduleRefreshed: boolean }
  | { outcome: 'invalid'; fieldErrors: FieldErrors; message: string }
  | { outcome: 'conflict'; reason: ConflictReason; message: string }
  // No reliable answer from the server. `canRetry` is false when a fresh
  // schedule shows the slot is no longer free (the request may have worked).
  | { outcome: 'unknown'; canRetry: boolean; message: string }

export type CancelResult =
  | { outcome: 'cancelled'; scheduleRefreshed: boolean }
  | { outcome: 'already_cancelled' }
  | { outcome: 'unknown'; canRetry: boolean; message: string }

/**
 * Booking and cancellation, each followed by a schedule reload.
 * The UI only ever shows what the server confirmed: nothing is applied
 * optimistically.
 */
export function useBookingActions(schedule: Schedule) {
  async function book(input: BookingInput): Promise<BookResult> {
    try {
      await createBooking(input)
    } catch (error) {
      return handleBookingError(error, input.slot_id)
    }

    const scheduleRefreshed = await schedule.load()
    return { outcome: 'booked', scheduleRefreshed }
  }

  async function handleBookingError(error: unknown, slotId: number): Promise<BookResult> {
    if (!(error instanceof ApiError)) {
      throw error
    }
    const failure = error.failure

    if (failure.kind === 'validation') {
      return { outcome: 'invalid', fieldErrors: failure.fieldErrors, message: failure.message }
    }

    if (failure.kind === 'conflict') {
      await schedule.load()
      return { outcome: 'conflict', reason: failure.reason, message: failure.message }
    }

    // Network or server failure: the booking may or may not exist. Look at a
    // fresh schedule before offering to send the same request again.
    const refreshed = await schedule.load()
    const slot = schedule.findSlot(slotId)
    if (refreshed && slot?.status === 'booked') {
      return {
        outcome: 'unknown',
        canRetry: false,
        message:
          'Ответ сервера не дошёл, а слот уже занят. Возможно, ваше бронирование создано — проверьте расписание.',
      }
    }
    if (refreshed && slot?.status === 'past') {
      return { outcome: 'unknown', canRetry: false, message: 'Ответ сервера не дошёл, а слот тем временем начался.' }
    }

    return { outcome: 'unknown', canRetry: true, message: `${failure.message} Данные формы сохранены.` }
  }

  async function cancel(bookingId: number, slotId: number): Promise<CancelResult> {
    try {
      await cancelBooking(bookingId)
    } catch (error) {
      return handleCancelError(error, bookingId, slotId)
    }

    const scheduleRefreshed = await schedule.load()
    return { outcome: 'cancelled', scheduleRefreshed }
  }

  async function handleCancelError(error: unknown, bookingId: number, slotId: number): Promise<CancelResult> {
    if (!(error instanceof ApiError)) {
      throw error
    }

    if (error.failure.kind === 'not_found') {
      await schedule.load()
      return { outcome: 'already_cancelled' }
    }

    const refreshed = await schedule.load()
    const slot = schedule.findSlot(slotId)
    if (refreshed && slot !== undefined && slot.booking_id !== bookingId) {
      return {
        outcome: 'unknown',
        canRetry: false,
        message: 'Ответ сервера не дошёл, но бронирования больше нет — похоже, отмена прошла.',
      }
    }

    return { outcome: 'unknown', canRetry: true, message: error.message }
  }

  return { book, cancel }
}
