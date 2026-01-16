import { defineStore } from 'pinia'
import { getConversations, getMessages } from '../services/mockApi'
import type { Conversation, Message } from '../types'

export const useChatStore = defineStore('chat', {
  state: () => ({
    conversations: [] as Conversation[],
    messages: {} as Record<string, Message[]>,
    loading: false,
  }),
  actions: {
    async fetchConversations() {
      this.loading = true
      this.conversations = await getConversations()
      this.loading = false
    },
    async fetchMessages(conversationId: string) {
      this.loading = true
      const data = await getMessages(conversationId)
      this.messages[conversationId] = data
      this.loading = false
    },
    sendMessage(conversationId: string, text: string) {
      const newMessage: Message = {
        id: Math.random().toString(36).slice(2),
        conversationId,
        from: 'me',
        text,
        time: 'Now',
      }
      const thread = this.messages[conversationId] ?? []
      this.messages[conversationId] = [...thread, newMessage]
    },
  },
})
