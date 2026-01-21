export type BookingsTabs = {
  primaryTab: 'reservations' | 'viewings'
  reservationTab: 'booked' | 'history' | 'requests'
  viewingRequestId?: string | null
}

const getParam = (params: Record<string, any>, key: string): string | null => {
  const raw = params[key]
  return raw !== undefined && raw !== null && raw !== '' ? String(raw) : null
}

export const resolveBookingsTabs = (params: Record<string, any>): BookingsTabs => {
  const tabParam = getParam(params, 'tab')
  const sectionParam = getParam(params, 'section')
  const viewingRequestId = getParam(params, 'viewingRequestId')

  const sectionValue = sectionParam ?? (tabParam && ['booked', 'history', 'requests'].includes(tabParam) ? tabParam : null)
  const reservationTab = (sectionValue as BookingsTabs['reservationTab']) || 'booked'

  const primaryTab: BookingsTabs['primaryTab'] =
    tabParam === 'viewings' || (!!viewingRequestId && tabParam !== 'reservations') ? 'viewings' : 'reservations'

  return { primaryTab, reservationTab, viewingRequestId }
}
