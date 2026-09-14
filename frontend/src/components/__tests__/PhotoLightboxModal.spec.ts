import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import PhotoLightboxModal from '../PhotoLightboxModal.vue'
import type { ReviewPhoto } from '@/types/review'

describe('PhotoLightboxModal.vue', () => {
  const mockPhotos: ReviewPhoto[] = [
    {
      id: 'img-1',
      preview_url: 'https://avatars.mds.yandex.net/get-altay/1/L',
      full_url: 'https://avatars.mds.yandex.net/get-altay/1/orig',
    },
    {
      id: 'img-2',
      preview_url: 'https://avatars.mds.yandex.net/get-altay/2/L',
      full_url: 'https://avatars.mds.yandex.net/get-altay/2/orig',
    },
  ]

  it('renders nothing when isOpen is false', () => {
    mount(PhotoLightboxModal, {
      props: {
        isOpen: false,
        photos: mockPhotos,
      },
      attachTo: document.body,
    })

    expect(document.body.querySelector('[role="dialog"]')).toBeNull()
  })

  it('renders photo and counter when isOpen is true', () => {
    mount(PhotoLightboxModal, {
      props: {
        isOpen: true,
        photos: mockPhotos,
        initialIndex: 0,
      },
      attachTo: document.body,
    })

    const dialog = document.body.querySelector('[role="dialog"]')
    expect(dialog).not.toBeNull()
    expect(document.body.textContent).toContain('1 из 2')

    const img = dialog?.querySelector('img')
    expect(img?.getAttribute('src')).toBe('https://avatars.mds.yandex.net/get-altay/1/orig')
  })

  it('switches photo on next and previous button clicks', async () => {
    const wrapper = mount(PhotoLightboxModal, {
      props: {
        isOpen: true,
        photos: mockPhotos,
        initialIndex: 0,
      },
      attachTo: document.body,
    })

    const nextBtn = document.body.querySelector('button[aria-label="Следующее фото"]') as HTMLButtonElement
    expect(nextBtn).not.toBeNull()
    nextBtn.click()
    await wrapper.vm.$nextTick()

    expect(document.body.textContent).toContain('2 из 2')
    const img = document.body.querySelector('img')
    expect(img?.getAttribute('src')).toBe('https://avatars.mds.yandex.net/get-altay/2/orig')
  })
})
