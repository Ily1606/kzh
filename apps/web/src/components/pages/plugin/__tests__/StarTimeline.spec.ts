import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
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

  it('updates total stars and re-fetches timeline when starCount or isStarred changes', async () => {
    const starTimelineMock = vi.fn()
      .mockResolvedValueOnce({ data: [{ date: '2026-07-12', count: 10 }] })
      .mockResolvedValueOnce({ data: [{ date: '2026-07-12', count: 10 }, { date: '2026-07-13', count: 1 }] })

    ;(getStar as any).mockReturnValue({
      starTimeline: starTimelineMock,
    })

    const wrapper = mount(StarTimeline, {
      props: {
        pluginId: '123',
        starCount: 10,
        isStarred: false,
      },
    })

    await flushPromises()
    expect(wrapper.text()).toContain('10')
    expect(starTimelineMock).toHaveBeenCalledTimes(1)

    // Simulate starring
    await wrapper.setProps({
      starCount: 11,
      isStarred: true,
    })

    await flushPromises()
    expect(wrapper.text()).toContain('11')
    expect(starTimelineMock).toHaveBeenCalledTimes(2)
  })

  it('optimistically adds today star point and switches away from empty state when starring from 0 stars', async () => {
    const starTimelineMock = vi.fn()
      .mockResolvedValueOnce({ data: [] })
      .mockResolvedValueOnce({ data: [{ date: '2026-10-15', count: 1 }] })

    ;(getStar as any).mockReturnValue({
      starTimeline: starTimelineMock,
    })

    const wrapper = mount(StarTimeline, {
      props: {
        pluginId: '123',
        starCount: 0,
        isStarred: false,
      },
    })

    await flushPromises()
    expect(wrapper.text()).toContain('No stars yet')
    expect(wrapper.text()).toContain('0')

    // User stars the plugin
    await wrapper.setProps({
      starCount: 1,
      isStarred: true,
    })

    await flushPromises()
    expect(wrapper.text()).toContain('1')
    expect(wrapper.text()).not.toContain('No stars yet')
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  it('positions points proportionally according to timestamp rather than equal index intervals', async () => {
    vi.useFakeTimers()
    vi.setSystemTime(new Date('2026-07-30T00:00:00Z'))

    // 01/07 and 02/07 are 1 day apart, 02/07 and 30/07 are 28 days apart (total 29 days)
    const mockData = [
      { date: '2026-07-01', count: 5 },
      { date: '2026-07-02', count: 3 },
      { date: '2026-07-30', count: 10 },
    ]

    ;(getStar as any).mockReturnValue({
      starTimeline: vi.fn().mockResolvedValue({ data: mockData }),
    })

    const wrapper = mount(StarTimeline, {
      props: { pluginId: '123' },
    })

    await flushPromises()

    const trendLine = wrapper.find('path[stroke="rgb(245,158,11)"]')
    expect(trendLine.exists()).toBe(true)

    const d = trendLine.attributes('d') || ''
    // d has shape: M 40,{y0} L {x1},{y1} L 985,{y2}
    const match = d.match(/^M\s*(\d+(\.\d+)?),(\d+(\.\d+)?)\s*L\s*(\d+(\.\d+)?),(\d+(\.\d+)?)\s*L\s*(\d+(\.\d+)?),(\d+(\.\d+)?)/)
    expect(match).not.toBeNull()

    const x0 = parseFloat(match![1])
    const x1 = parseFloat(match![5])

    expect(x0).toBe(40)
    // x1 for Day 2 is close to Day 1 (~72.5px), far below the midpoint (512.5px)
    expect(x1).toBeLessThan(100)
    expect(x1).toBeGreaterThan(60)

    // The last point in the path should reach the right edge (985)
    expect(d).toContain(' 985,')
  })

  it('automatically fills missing dates with count 0 between timeline start and end', async () => {
    vi.useFakeTimers()
    vi.setSystemTime(new Date('2026-07-04T00:00:00Z'))

    // 01/07 (count 5) and 04/07 (count 3) -> missing 02/07 and 03/07
    const mockData = [
      { date: '2026-07-01', count: 5 },
      { date: '2026-07-04', count: 3 },
    ]

    ;(getStar as any).mockReturnValue({
      starTimeline: vi.fn().mockResolvedValue({ data: mockData }),
    })

    const wrapper = mount(StarTimeline, {
      props: { pluginId: '123' },
    })

    await flushPromises()

    // 4 days total: 01, 02 (count 0), 03 (count 0), 04
    const rects = wrapper.findAll('rect.cursor-crosshair')
    expect(rects).toHaveLength(4)

    // Total stars should be 8
    expect(wrapper.text()).toContain('8')
  })

  it('automatically fills missing months with count 0 when data is in monthly format', async () => {
    vi.useFakeTimers()
    vi.setSystemTime(new Date('2026-03-20T00:00:00Z'))

    // 2026-01 (count 10) and 2026-03 (count 5) -> missing 2026-02
    const mockData = [
      { date: '2026-01', count: 10 },
      { date: '2026-03', count: 5 },
    ]

    ;(getStar as any).mockReturnValue({
      starTimeline: vi.fn().mockResolvedValue({ data: mockData }),
    })

    const wrapper = mount(StarTimeline, {
      props: { pluginId: '123' },
    })

    await flushPromises()

    // 3 months total: 2026-01, 2026-02 (count 0), 2026-03
    const rects = wrapper.findAll('rect.cursor-crosshair')
    expect(rects).toHaveLength(3)

    expect(wrapper.text()).toContain('15')
  })

  it('extends missing dates with count 0 up to current date (today) when data ends in the past', async () => {
    vi.useFakeTimers()
    vi.setSystemTime(new Date('2026-10-15T00:00:00Z'))

    // Data stops at 2026-10-09, today is 2026-10-15
    const mockData = [
      { date: '2026-10-08', count: 2 },
      { date: '2026-10-09', count: 1 },
    ]

    ;(getStar as any).mockReturnValue({
      starTimeline: vi.fn().mockResolvedValue({ data: mockData }),
    })

    const wrapper = mount(StarTimeline, {
      props: { pluginId: '123' },
    })

    await flushPromises()

    // Range from 2026-10-08 to 2026-10-15 = 8 days: 08, 09, 10, 11, 12, 13, 14, 15
    const rects = wrapper.findAll('rect.cursor-crosshair')
    expect(rects).toHaveLength(8)

    // Total stars should still be 3
    expect(wrapper.text()).toContain('3')

    // Trendline d attribute should end at PAD_RIGHT boundary (985) on today
    const trendLine = wrapper.find('path[stroke="rgb(245,158,11)"]')
    expect(trendLine.attributes('d')).toContain(' 985,')
  })

  it('keeps highest y-axis tick label within visible bounds when ticks exceed total stars', async () => {
    (getStar as any).mockReturnValue({
      starTimeline: vi.fn().mockResolvedValue({
        data: [{ date: '2026-10-15', count: 3777 }]
      })
    })

    const wrapper = mount(StarTimeline, {
      props: { pluginId: '123', starCount: 3777 }
    })

    await flushPromises()

    const textElements = wrapper.findAll('text.font-mono')
    expect(textElements.length).toBeGreaterThan(0)

    // The top tick (4.0k) should have y >= PAD_TOP (20), never near 0 or negative
    const topTick = textElements.find(el => el.text() === '4.0k')
    expect(topTick).toBeDefined()
    const yVal = parseFloat(topTick!.attributes('y') || '0')
    expect(yVal).toBeGreaterThanOrEqual(20)
  })
})

