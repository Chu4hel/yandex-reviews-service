import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import RatingStars from '../RatingStars.vue'

describe('RatingStars.vue', () => {
  it('renders correct number of total stars based on max prop', () => {
    const wrapper = mount(RatingStars, {
      props: {
        rating: 4,
        max: 5,
      },
    })

    const svgElements = wrapper.findAll('svg')
    expect(svgElements).toHaveLength(5)
  })

  it('highlights correct number of active stars according to rating', () => {
    const wrapper = mount(RatingStars, {
      props: {
        rating: 4.2,
        max: 5,
      },
    })

    const svgElements = wrapper.findAll('svg')
    const filledStars = svgElements.filter((svg) => svg.classes().includes('fill-amber-400'))
    // Math.round(4.2) is 4
    expect(filledStars).toHaveLength(4)
  })

  it('displays formatted rating number when showNumber is true', () => {
    const wrapper = mount(RatingStars, {
      props: {
        rating: 4.86,
        showNumber: true,
      },
    })

    const textSpan = wrapper.find('span')
    expect(textSpan.exists()).toBe(true)
    expect(textSpan.text()).toBe('4.9')
  })

  it('displays dash placeholder when rating is null or zero with showNumber', () => {
    const wrapper = mount(RatingStars, {
      props: {
        rating: null,
        showNumber: true,
      },
    })

    const textSpan = wrapper.find('span')
    expect(textSpan.exists()).toBe(true)
    expect(textSpan.text()).toBe('—')
  })

  it('applies correct sizing classes for lg size', () => {
    const wrapper = mount(RatingStars, {
      props: {
        rating: 5,
        size: 'lg',
        showNumber: true,
      },
    })

    const svg = wrapper.find('svg')
    expect(svg.classes()).toContain('w-6')
    expect(svg.classes()).toContain('h-6')

    const textSpan = wrapper.find('span')
    expect(textSpan.classes()).toContain('text-2xl')
  })
})
