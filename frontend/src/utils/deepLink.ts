export type ChatDeepLinkTarget =
  | { kind: 'conversation'; id: string }
  | { kind: 'application'; id: string }
  | { kind: 'listing'; id: string }
  | { kind: 'none' }

const getParam = (params: Record<string, any>, key: string): string | null => {
  const raw =
    params[key] ??
    params[`${key}Id`] ??
    params[`${key}ID`] ??
    params[`${key}_id`] ??
    params[`${key}_Id`] ??
    params[`${key}_ID`]
  return raw !== undefined && raw !== null && raw !== '' ? String(raw) : null
}

export const resolveChatTarget = (
  params: Record<string, any>,
  role: 'seeker' | 'landlord' | 'admin' | string,
): ChatDeepLinkTarget => {
  const conversationId = getParam(params, 'conversation')
  if (conversationId) return { kind: 'conversation', id: conversationId }

  const applicationId = getParam(params, 'application')
  if (applicationId) return { kind: 'application', id: applicationId }

  const listingId = getParam(params, 'listing')
  if (listingId && role === 'seeker') return { kind: 'listing', id: listingId }

  if (listingId && role === 'landlord') {
    if (applicationId) return { kind: 'application', id: applicationId }
    return { kind: 'listing', id: listingId }
  }

  return { kind: 'none' }
}
