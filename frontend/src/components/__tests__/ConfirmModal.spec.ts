import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import ConfirmModal from '../ConfirmModal.vue'

describe('ConfirmModal', () => {
  it('renders title and message when isOpen is true', () => {
    const wrapper = mount(ConfirmModal, {
      props: {
        isOpen: true,
        title: 'Удалить организацию?',
        message: 'Вы уверены, что хотите удалить?',
        confirmText: 'Да, удалить',
        cancelText: 'Отмена',
      },
      attachTo: document.body,
    })

    expect(document.body.textContent).toContain('Удалить организацию?')
    expect(document.body.textContent).toContain('Вы уверены, что хотите удалить?')
    expect(document.body.textContent).toContain('Да, удалить')
    expect(document.body.textContent).toContain('Отмена')

    wrapper.unmount()
  })

  it('emits confirm when confirm button is clicked', async () => {
    const wrapper = mount(ConfirmModal, {
      props: {
        isOpen: true,
        title: 'Подтверждение',
        message: 'Тест',
      },
      attachTo: document.body,
    })

    const buttons = document.body.querySelectorAll('button')
    // Второй button - confirm
    const confirmBtn = buttons[buttons.length - 1]
    confirmBtn.click()

    expect(wrapper.emitted('confirm')).toBeTruthy()

    wrapper.unmount()
  })

  it('emits cancel when cancel button is clicked', async () => {
    const wrapper = mount(ConfirmModal, {
      props: {
        isOpen: true,
        title: 'Подтверждение',
        message: 'Тест',
      },
      attachTo: document.body,
    })

    const buttons = document.body.querySelectorAll('button')
    // Первый button в футере - cancel
    const cancelBtn = buttons[buttons.length - 2]
    cancelBtn.click()

    expect(wrapper.emitted('cancel')).toBeTruthy()

    wrapper.unmount()
  })
})
