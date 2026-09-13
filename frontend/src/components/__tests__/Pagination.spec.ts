import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import Pagination from '../Pagination.vue'

describe('Pagination.vue', () => {
  it('renders correct info about shown range of reviews', () => {
    const wrapper = mount(Pagination, {
      props: {
        currentPage: 2,
        lastPage: 4,
        perPage: 50,
        total: 180,
      },
    })

    const text = wrapper.text()
    expect(text).toContain('51–100')
    expect(text).toContain('180')
  })

  it('disables previous button on the first page', () => {
    const wrapper = mount(Pagination, {
      props: {
        currentPage: 1,
        lastPage: 5,
        perPage: 50,
        total: 250,
      },
    })

    const prevButton = wrapper.find('button')
    expect(prevButton.attributes('disabled')).toBeDefined()
  })

  it('disables next button on the last page', () => {
    const wrapper = mount(Pagination, {
      props: {
        currentPage: 5,
        lastPage: 5,
        perPage: 50,
        total: 250,
      },
    })

    const buttons = wrapper.findAll('button')
    const nextButton = buttons[buttons.length - 1]
    expect(nextButton.attributes('disabled')).toBeDefined()
  })

  it('emits page-change event with new page number when page button clicked', async () => {
    const wrapper = mount(Pagination, {
      props: {
        currentPage: 1,
        lastPage: 3,
        perPage: 50,
        total: 150,
      },
    })

    // Find button for page 2
    const buttons = wrapper.findAll('button')
    const page2Button = buttons.find((btn) => btn.text().trim() === '2')
    expect(page2Button).toBeDefined()

    if (page2Button) {
      await page2Button.trigger('click')
      const emitted = wrapper.emitted('page-change')
      expect(emitted).toBeDefined()
      expect(emitted?.[0]).toEqual([2])
    }
  })

  it('does not emit page-change when isLoading is true', async () => {
    const wrapper = mount(Pagination, {
      props: {
        currentPage: 1,
        lastPage: 3,
        perPage: 50,
        total: 150,
        isLoading: true,
      },
    })

    const buttons = wrapper.findAll('button')
    const page2Button = buttons.find((btn) => btn.text().trim() === '2')
    if (page2Button) {
      await page2Button.trigger('click')
      expect(wrapper.emitted('page-change')).toBeUndefined()
    }
  })
})
