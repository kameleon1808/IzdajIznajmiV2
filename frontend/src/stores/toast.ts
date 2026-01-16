import { defineStore } from 'pinia'

type ToastType = 'success' | 'error' | 'info'

export interface ToastItem {
  id: string
  title: string
  message?: string
  type?: ToastType
}

const makeId = () =>
  typeof crypto !== 'undefined' && 'randomUUID' in crypto
    ? crypto.randomUUID()
    : Math.random().toString(36).slice(2)

export const useToastStore = defineStore('toast', {
  state: () => ({
    toasts: [] as ToastItem[],
  }),
  actions: {
    push(toast: Omit<ToastItem, 'id'> & { id?: string }) {
      const id = toast.id ?? makeId()
      const item: ToastItem = { ...toast, id, type: toast.type ?? 'info' }
      this.toasts.push(item)
      setTimeout(() => this.remove(id), 3200)
      return id
    },
    remove(id: string) {
      this.toasts = this.toasts.filter((t) => t.id !== id)
    },
  },
})
