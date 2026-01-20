import { describe, expect, it } from 'vitest'
import { resolveChatTarget } from '../src/utils/deepLink'

describe('resolveChatTarget', () => {
  it('prefers conversation id when present', () => {
    const target = resolveChatTarget({ conversationId: 42, listingId: 10 }, 'seeker')
    expect(target).toEqual({ kind: 'conversation', id: '42' })
  })

  it('returns application target when provided', () => {
    const target = resolveChatTarget({ applicationId: 'abc' }, 'landlord')
    expect(target).toEqual({ kind: 'application', id: 'abc' })
  })

  it('routes seekers to listing chat when only listing id exists', () => {
    const target = resolveChatTarget({ listingId: 7 }, 'seeker')
    expect(target).toEqual({ kind: 'listing', id: '7' })
  })

  it('keeps landlord on listing selection when no application is provided', () => {
    const target = resolveChatTarget({ listingId: 9 }, 'landlord')
    expect(target).toEqual({ kind: 'listing', id: '9' })
  })

  it('falls back to none when parameters are missing', () => {
    const target = resolveChatTarget({}, 'seeker')
    expect(target).toEqual({ kind: 'none' })
  })
})
