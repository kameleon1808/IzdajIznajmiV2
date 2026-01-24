import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import ErrorState from '../src/components/ui/ErrorState.vue'

describe('ErrorState', () => {
  it('renders message and emits retry', async () => {
    const wrapper = mount(ErrorState, {
      props: { message: 'Failed to load data', retryLabel: 'Retry' },
    })

    expect(wrapper.text()).toContain('Failed to load data')
    const button = wrapper.get('button')
    await button.trigger('click')

    expect(wrapper.emitted('retry')).toBeTruthy()
  })
})
