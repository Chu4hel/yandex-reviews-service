import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import ReviewCard from '../ReviewCard.vue'
import { computeAdaptiveTruncation } from '@/utils/textTruncation'
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

  it('renders photo thumbnails and opens lightbox when clicked', async () => {
    const reviewWithPhotos: Review = {
      ...baseReview,
      photos: [
        {
          id: 'p1',
          preview_url: 'https://avatars.mds.yandex.net/get-altay/1/L',
          full_url: 'https://avatars.mds.yandex.net/get-altay/1/orig',
        },
        {
          id: 'p2',
          preview_url: 'https://avatars.mds.yandex.net/get-altay/2/L',
          full_url: 'https://avatars.mds.yandex.net/get-altay/2/orig',
        },
      ],
    }

    const wrapper = mount(ReviewCard, {
      props: {
        review: reviewWithPhotos,
      },
    })

    const photoButtons = wrapper.findAll('button[title^="Открыть фото"]')
    expect(photoButtons).toHaveLength(2)

    const firstImg = photoButtons[0].find('img')
    expect(firstImg.exists()).toBe(true)
    expect(firstImg.attributes('src')).toBe('https://avatars.mds.yandex.net/get-altay/1/L')
    expect(firstImg.attributes('referrerpolicy')).toBe('no-referrer')

    await photoButtons[0].trigger('click')
  })

  it('truncates very long review and toggles expansion on button click', async () => {
    const longText = 'Это очень длинный отзыв о заведении. '.repeat(15) // ~555 символов
    const longReview: Review = {
      ...baseReview,
      text: longText,
    }

    const wrapper = mount(ReviewCard, {
      props: {
        review: longReview,
      },
    })

    // В свернутом состоянии текст укорочен и есть кнопка "Показать полностью"
    expect(wrapper.text()).toContain('Показать полностью')
    expect(wrapper.text()).toContain('...')
    expect(wrapper.text()).not.toContain(longText)

    // Кликаем "Показать полностью"
    const toggleButton = wrapper.find('button.text-red-600')
    expect(toggleButton.exists()).toBe(true)
    await toggleButton.trigger('click')

    // Теперь текст развернут полностью и кнопка сменилась на "Свернуть"
    expect(wrapper.text()).toContain('Свернуть')
    expect(wrapper.text()).toContain(longText)

    // Кликаем "Свернуть"
    await toggleButton.trigger('click')
    expect(wrapper.text()).toContain('Показать полностью')
  })

  it('does not truncate reviews shorter than trigger threshold (e.g. 420 chars)', () => {
    const mediumText = 'Уютное заведение с прекрасным обслуживанием и вкусной кухней. '.repeat(6) // ~378 символов
    const mediumReview: Review = {
      ...baseReview,
      text: mediumText,
    }

    const wrapper = mount(ReviewCard, {
      props: {
        review: mediumReview,
      },
    })

    expect(wrapper.text()).not.toContain('Показать полностью')
    expect(wrapper.text()).toContain(mediumText)
  })

  it('adaptively cuts at sentence boundary and keeps short remaining endings', () => {
    // 1. Предложение заканчивается на ~380 символе, а хвост длинный (200 символов) -> срез ровно по предложению
    const sent1 = 'Первая часть отзыва с подробным описанием всех впечатлений от визита в кафе. '.repeat(5) // ~385 символов
    const sent2 = 'Вторая отдельная мысль с дополнительными замечаниями о десертах и парковке. '.repeat(3) // ~230 символов
    const fullText = sent1 + sent2

    const result = computeAdaptiveTruncation(fullText)
    expect(result.isLong).toBe(true)
    expect(result.preview).toContain('впечатлений от визита в кафе.')
    expect(result.preview.endsWith('...')).toBe(true)

    // 2. Если до конца отзыва остается короткий хвостик (< 80 символов), он дописывается целиком
    const almostComplete = sent1 + 'Рекомендую всем заглянуть!' // ~412 символов всего, остаток 27 символов
    const completeResult = computeAdaptiveTruncation(almostComplete)
    expect(completeResult.isLong).toBe(false)
    expect(completeResult.preview).toBe(almostComplete)
  })
})

