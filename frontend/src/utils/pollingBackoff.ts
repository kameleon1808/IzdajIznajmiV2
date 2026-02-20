export class PollingBackoff {
  private readonly minDelayMs: number
  private readonly maxDelayMs: number
  private readonly factor: number
  private currentDelayMs: number

  constructor(minDelayMs: number, maxDelayMs: number, factor: number = 2) {
    this.minDelayMs = minDelayMs
    this.maxDelayMs = maxDelayMs
    this.factor = factor
    this.currentDelayMs = minDelayMs
  }

  current(): number {
    return this.currentDelayMs
  }

  recordIdle(): number {
    this.currentDelayMs = Math.min(this.maxDelayMs, Math.round(this.currentDelayMs * this.factor))
    return this.currentDelayMs
  }

  recordActivity(): number {
    this.currentDelayMs = this.minDelayMs
    return this.currentDelayMs
  }
}
