import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import ThemeToggle from '../ThemeToggle.vue'

describe('ThemeToggle', () => {
  it('renders three theme buttons', () => {
    const wrapper = mount(ThemeToggle)
    const buttons = wrapper.findAll('button')
    expect(buttons.length).toBe(3)
  })

  it('switches theme to dark when dark button is clicked', async () => {
    const wrapper = mount(ThemeToggle)
    const buttons = wrapper.findAll('button')
    // buttons[1] is dark
    await buttons[1].trigger('click')
    expect(document.documentElement.classList.contains('dark')).toBe(true)
  })

  it('switches theme to light when light button is clicked', async () => {
    const wrapper = mount(ThemeToggle)
    const buttons = wrapper.findAll('button')
    // buttons[0] is light
    await buttons[0].trigger('click')
    expect(document.documentElement.classList.contains('dark')).toBe(false)
  })
})
