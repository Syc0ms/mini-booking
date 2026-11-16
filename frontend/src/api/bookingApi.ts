// All HTTP calls of the app live here. Components never call fetch directly;
// they get typed data or an ApiError with a small, explicit set of kinds.

export type SlotStatus = 'free' | 'booked' | 'past'

export interface Slot {
  id: number
  /** Calendar date in the schedule timezone, e.g. "2026-11-18". */
  date: string
  /** ISO 8601 with offset, e.g. "2026-11-18T10:00:00+03:00". */
  starts_at: string
  ends_at: string
  status: SlotStatus
  booking_id: number | null
}

export interface ScheduleMeta {
  timezone: string
  /** Today's date in the schedule timezone, according to the server. */
  today: string
  server_time: string
}

export interface SlotsResponse {
  data: Slot[]
  meta: ScheduleMeta
}

export interface BookingInput {
  slot_id: number
  customer_name: string
  customer_email: string
}

export type FieldErrors = Partial<Record<keyof BookingInput, string>>

export type ConflictReason = 'slot_booked' | 'slot_past'

export type ApiFailure =
  | { kind: 'validation'; message: string; fieldErrors: FieldErrors }
  | { kind: 'conflict'; message: string; reason: ConflictReason }
  | { kind: 'not_found'; message: string }
  // 'server' and 'network' mean we do not know whether a mutation was applied.
  | { kind: 'server'; message: string; status: number }
  | { kind: 'network'; message: string }

export class ApiError extends Error {
  readonly failure: ApiFailure

  constructor(failure: ApiFailure) {
    super(failure.message)
    this.name = 'ApiError'
    this.failure = failure
  }
}

const NETWORK_MESSAGE = 'Нет связи с сервером. Проверьте подключение.'
const SERVER_MESSAGE = 'Сервер недоступен или не смог обработать запрос.'

export async function fetchSlots(): Promise<SlotsResponse> {
  const response = await send('GET', '/api/slots')
  return (await response.json()) as SlotsResponse
}

export async function createBooking(input: BookingInput): Promise<{ id: number }> {
  const response = await send('POST', '/api/bookings', input)
  return (await response.json()) as { id: number }
}

export async function cancelBooking(bookingId: number): Promise<void> {
  await send('DELETE', `/api/bookings/${bookingId}`)
}

async function send(method: string, url: string, body?: unknown): Promise<Response> {
  const headers: Record<string, string> = { Accept: 'application/json' }
  if (body !== undefined) {
    headers['Content-Type'] = 'application/json'
  }

  let response: Response
  try {
    response = await fetch(url, {
      method,
      headers,
      body: body === undefined ? undefined : JSON.stringify(body),
    })
  } catch {
    // fetch only rejects when no HTTP response arrived at all.
    throw new ApiError({ kind: 'network', message: NETWORK_MESSAGE })
  }

  if (response.ok) {
    return response
  }

  throw await toApiError(response)
}

async function toApiError(response: Response): Promise<ApiError> {
  const payload = await readJson(response)
  const message = typeof payload?.message === 'string' ? payload.message : SERVER_MESSAGE

  if (response.status === 422) {
    return new ApiError({
      kind: 'validation',
      message,
      fieldErrors: firstErrorPerField(payload?.errors),
    })
  }

  if (response.status === 409) {
    const reason: ConflictReason = payload?.reason === 'slot_past' ? 'slot_past' : 'slot_booked'
    return new ApiError({ kind: 'conflict', message, reason })
  }

  if (response.status === 404) {
    return new ApiError({ kind: 'not_found', message })
  }

  // A proxy in front of a stopped backend answers 502/504: that is a
  // connectivity problem from the user's point of view.
  if (response.status === 502 || response.status === 503 || response.status === 504) {
    return new ApiError({ kind: 'network', message: NETWORK_MESSAGE })
  }

  return new ApiError({ kind: 'server', message: SERVER_MESSAGE, status: response.status })
}

async function readJson(response: Response): Promise<Record<string, unknown> | null> {
  try {
    return (await response.json()) as Record<string, unknown>
  } catch {
    return null
  }
}

function firstErrorPerField(errors: unknown): FieldErrors {
  const result: FieldErrors = {}
  if (errors === null || typeof errors !== 'object') {
    return result
  }

  for (const [field, messages] of Object.entries(errors as Record<string, unknown>)) {
    if (Array.isArray(messages) && typeof messages[0] === 'string') {
      result[field as keyof BookingInput] = messages[0]
    }
  }
  return result
}
