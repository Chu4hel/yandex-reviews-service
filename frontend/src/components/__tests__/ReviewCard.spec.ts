import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import ReviewCard from '../ReviewCard.vue'
import type { Review } from '@/types/review'

describe('ReviewCard.vue', () => {
  const baseReview: Review = {
    id: 1,
    organization_id: 10,
    yandex_review_id: 'rev-100',
    author_name: 'Иван Иванов',
    author_avatar_url: null,
    author_level: 'Знаток города 4 уровня',
    rating: 5,
    text: 'Очень вкусная пицца и быстрая доставка!',
    published_at: '2026-03-01T12:00:00.000Z',
    business_response_text: 'Спасибо за ваш теплый отзыв!',
    business_response_at: '2026-03-01T14:00:00.000Z',
    created_at: '2026-03-01T12:00:00.000Z',
    updated_at: '2026-03-01T12:00:00.000Z',
  }

  it('renders author name, level and text', () => {
    const wrapper = mount(ReviewCard, {
      props: {
        review: baseReview,
      },
    })

    expect(wrapper.text()).toContain('Иван Иванов')
    expect(wrapper.text()).toContain('Знаток города 4 уровня')
    expect(wrapper.text()).toContain('Очень вкусная пицца и быстрая доставка!')
  })

  it('computes author initials correctly when avatar is null', () => {
    const wrapper = mount(ReviewCard, {
      props: {
        review: baseReview,
      },
    })

    expect(wrapper.text()).toContain('ИИ')
  })

  it('renders official business response when present', () => {
    const wrapper = mount(ReviewCard, {
      props: {
        review: baseReview,
      },
    })

    expect(wrapper.text()).toContain('Ответ организации')
    expect(wrapper.text()).toContain('Спасибо за ваш теплый отзыв!')
  })

  it('does not render business response block when business_response_text is null', () => {
    const reviewWithoutReply: Review = {
      ...baseReview,
      business_response_text: null,
      business_response_at: null,
    }

    const wrapper = mount(ReviewCard, {
      props: {
        review: reviewWithoutReply,
      },
    })

    expect(wrapper.text()).not.toContain('Ответ организации')
  })

  it('highlights matching search terms with mark tags safely', () => {
    const wrapper = mount(ReviewCard, {
      props: {
        review: baseReview,
        searchTerm: 'пицца теплый',
      },
    })

    const html = wrapper.html()
    expect(html).toContain('<mark class="bg-amber-200/90 dark:bg-amber-800/80 text-amber-950 dark:text-amber-100 px-0.5 rounded-xs font-semibold">пицца</mark>')
    expect(html).toContain('<mark class="bg-amber-200/90 dark:bg-amber-800/80 text-amber-950 dark:text-amber-100 px-0.5 rounded-xs font-semibold">теплый</mark>')
  })
})
