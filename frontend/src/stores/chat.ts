import { defineStore } from 'pinia'
import { getConversations, getMessages } from '../services'
import type { Conversation, Message } from '../types'

export const useChatStore = defineStore('chat', {
  state: () => ({
    conversations: [] as Conversation[],
    messages: {} as Record<string, Message[]>,
    loading: false,
    error: '',
  }),
  actions: {
    async fetchConversations() {
      this.loading = true
      this.error = ''
      try {
        this.conversations = await getConversations()
      } catch (error) {
        this.error = (error as Error).message || 'Failed to load messages.'
        this.conversations = []
      } finally {
        this.loading = false
      }
    },
    async fetchMessages(conversationId: string) {
      this.loading = true
      this.error = ''
      try {
        const data = await getMessages(conversationId)
        this.messages[conversationId] = data
      } catch (error) {
        this.error = (error as Error).message || 'Failed to load chat.'
        this.messages[conversationId] = []
      } finally {
        this.loading = false
      }
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
