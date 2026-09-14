import { describe, it, expect, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import ToastContainer from '../ToastContainer.vue'
import { useNotificationStore } from '@/stores/notification'

describe('ToastContainer.vue', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  it('renders nothing when there are no toasts', () => {
    const wrapper = mount(ToastContainer)
    expect(wrapper.findAll('[role="alert"]').length).toBe(0)
  })

  it('renders success toast with title and message', async () => {
    const store = useNotificationStore()
    const wrapper = mount(ToastContainer)

    store.success('Успешная операция', 'Все данные сохранены')
    await wrapper.vm.$nextTick()

    const alerts = wrapper.findAll('[role="alert"]')
    expect(alerts.length).toBe(1)
    expect(alerts[0].text()).toContain('Успешная операция')
    expect(alerts[0].text()).toContain('Все данные сохранены')
  })

  it('renders error toast with request id', async () => {
    const store = useNotificationStore()
    const wrapper = mount(ToastContainer)

    store.error('Ошибка сервера', 'Внутренний сбой', 'req-trace-uuid-123')
    await wrapper.vm.$nextTick()

    const alert = wrapper.find('[role="alert"]')
    expect(alert.exists()).toBe(true)
    expect(alert.text()).toContain('Ошибка сервера')
    expect(alert.text()).toContain('ID: req-trace-uuid-123')
  })

  it('dismisses toast on close button click', async () => {
    const store = useNotificationStore()
    const wrapper = mount(ToastContainer)

    store.info('Информация', 'Тестовое уведомление')
    await wrapper.vm.$nextTick()

    expect(wrapper.findAll('[role="alert"]').length).toBe(1)

    const closeBtn = wrapper.find('button[aria-label="Закрыть уведомление"]')
    await closeBtn.trigger('click')
    await wrapper.vm.$nextTick()

    expect(store.toasts.length).toBe(0)
    expect(wrapper.findAll('[role="alert"]').length).toBe(0)
  })
})
