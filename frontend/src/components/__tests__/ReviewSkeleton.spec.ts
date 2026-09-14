import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import ReviewSkeleton from '../ReviewSkeleton.vue'

describe('ReviewSkeleton.vue', () => {
  it('рендерит скелетон карточки с классом animate-pulse', () => {
    const wrapper = mount(ReviewSkeleton)
    const article = wrapper.find('[data-testid="review-skeleton"]')
    expect(article.exists()).toBe(true)
    expect(article.classes()).toContain('animate-pulse')
  })
})
