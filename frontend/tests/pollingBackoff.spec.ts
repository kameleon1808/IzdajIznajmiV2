import { describe, expect, it } from 'vitest'
import { PollingBackoff } from '../src/utils/pollingBackoff'

describe('PollingBackoff', () => {
  it('increases delay exponentially while idle and caps at max', () => {
    const backoff = new PollingBackoff(3000, 30000)

    expect(backoff.current()).toBe(3000)
    expect(backoff.recordIdle()).toBe(6000)
    expect(backoff.recordIdle()).toBe(12000)
    expect(backoff.recordIdle()).toBe(24000)
    expect(backoff.recordIdle()).toBe(30000)
    expect(backoff.recordIdle()).toBe(30000)
  })

  it('resets to base delay when activity is detected', () => {
    const backoff = new PollingBackoff(3000, 30000)

    backoff.recordIdle()
    backoff.recordIdle()
    expect(backoff.current()).toBe(12000)

    expect(backoff.recordActivity()).toBe(3000)
    expect(backoff.current()).toBe(3000)
  })
})
