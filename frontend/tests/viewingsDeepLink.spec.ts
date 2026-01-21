import { describe, expect, it } from 'vitest'
import { resolveBookingsTabs } from '../src/utils/viewings'

describe('resolveBookingsTabs', () => {
  it('defaults to reservations/booked', () => {
    const res = resolveBookingsTabs({})
    expect(res).toEqual({ primaryTab: 'reservations', reservationTab: 'booked', viewingRequestId: null })
  })

  it('picks reservation section when provided', () => {
    const res = resolveBookingsTabs({ tab: 'reservations', section: 'history' })
    expect(res).toMatchObject({ primaryTab: 'reservations', reservationTab: 'history' })
  })

  it('treats legacy tab values as reservation section', () => {
    const res = resolveBookingsTabs({ tab: 'requests' })
    expect(res).toMatchObject({ primaryTab: 'reservations', reservationTab: 'requests' })
  })

  it('switches to viewings when tab set', () => {
    const res = resolveBookingsTabs({ tab: 'viewings' })
    expect(res.primaryTab).toBe('viewings')
  })

  it('forces viewings when deep link id provided', () => {
    const res = resolveBookingsTabs({ viewingRequestId: 42 })
    expect(res.primaryTab).toBe('viewings')
    expect(res.viewingRequestId).toBe('42')
  })
})
