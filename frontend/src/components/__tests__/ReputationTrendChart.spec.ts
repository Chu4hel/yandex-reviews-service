import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import ReputationTrendChart from '../ReputationTrendChart.vue'
import type { OrganizationSnapshot } from '@/types/snapshot'

describe('ReputationTrendChart.vue', () => {
  const mockSnapshots: OrganizationSnapshot[] = [
    {
      id: 1,
      organization_id: 1,
      rating_before: 4.5,
      rating_after: 4.6,
      ratings_count_before: 100,
      ratings_count_after: 105,
      reviews_count_before: 80,
      reviews_count_after: 84,
      new_reviews_added: 4,
      updated_reviews_count: 0,
      snapshot_at: '2026-09-10T10:00:00Z',
      created_at: '2026-09-10T10:00:00Z',
    },
    {
      id: 2,
      organization_id: 1,
      rating_before: 4.6,
      rating_after: 4.8,
      ratings_count_before: 105,
      ratings_count_after: 115,
      reviews_count_before: 84,
      reviews_count_after: 92,
      new_reviews_added: 8,
      updated_reviews_count: 1,
      snapshot_at: '2026-09-13T10:00:00Z',
      created_at: '2026-09-13T10:00:00Z',
    },
  ]

  it('renders rating trend and delta correctly', () => {
    const wrapper = mount(ReputationTrendChart, {
      props: {
        snapshots: mockSnapshots,
      },
    })

    expect(wrapper.text()).toContain('4.8★')
    expect(wrapper.text()).toContain('+0.3')
    expect(wrapper.text()).toContain('4.5')
  })

  it('calculates total new reviews correctly', () => {
    const wrapper = mount(ReputationTrendChart, {
      props: {
        snapshots: mockSnapshots,
      },
    })

    expect(wrapper.text()).toContain('+12')
  })

  it('renders SVG chart elements including data points for all snapshots', () => {
    const wrapper = mount(ReputationTrendChart, {
      props: {
        snapshots: mockSnapshots,
      },
    })

    const svg = wrapper.find('svg')
    expect(svg.exists()).toBe(true)

    const circles = wrapper.findAll('circle')
    expect(circles).toHaveLength(2)

    const paths = wrapper.findAll('path')
    expect(paths.length).toBeGreaterThanOrEqual(2)
  })
})
