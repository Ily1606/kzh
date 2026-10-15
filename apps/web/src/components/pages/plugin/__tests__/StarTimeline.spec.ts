import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import StarTimeline from '../StarTimeline.vue'
import { getStar } from '@/api/generated/endpoints/star/star'

// Mock the API module
vi.mock('@/api/generated/endpoints/star/star', () => ({
  getStar: vi.fn()
}))

describe('StarTimeline.vue', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('shows loading state initially', () => {
    // Return a promise that doesn't resolve to keep it in loading state
    (getStar as any).mockReturnValue({
      starTimeline: () => new Promise(() => {}) 
    })

    const wrapper = mount(StarTimeline, {
      props: { pluginId: '123' }
    })

    expect(wrapper.find('.animate-pulse').exists()).toBe(true)
  })

  it('renders empty state when there is no data', async () => {
    (getStar as any).mockReturnValue({
      starTimeline: vi.fn().mockResolvedValue({ data: [] })
    })

    const wrapper = mount(StarTimeline, {
      props: { pluginId: '123' }
    })

    await flushPromises()

    expect(wrapper.text()).toContain('No stars yet')
    expect(wrapper.text()).toContain('0') // Total stars should be 0
  })

  it('renders timeline data correctly and calculates cumulative total', async () => {
    const mockData = [
      { date: '2026-07-12', count: 10 },
      { date: '2026-07-13', count: 5 }
    ];

    (getStar as any).mockReturnValue({
      starTimeline: vi.fn().mockResolvedValue({ data: mockData })
    })

    const wrapper = mount(StarTimeline, {
      props: { pluginId: '123' }
    })

    await flushPromises()

    // Total stars should be 15
    expect(wrapper.text()).toContain('15')
    
    // SVG chart should be rendered
    expect(wrapper.find('svg').exists()).toBe(true)
  })
  
  it('handles API error', async () => {
    (getStar as any).mockReturnValue({
      starTimeline: vi.fn().mockRejectedValue(new Error('API Error'))
    })

    const wrapper = mount(StarTimeline, {
      props: { pluginId: '123' }
    })

    await flushPromises()

    expect(wrapper.text()).toContain('Failed to load timeline data')
  })
})

