import { defineStore } from 'pinia'
import {
  getConversationForListing,
  getConversations,
  getMessages,
  getOrCreateConversationForApplication,
  markConversationRead,
  sendMessageToConversation,
  sendMessageToListing,
} from '../services'
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
        await markConversationRead(conversationId)
        this.conversations = this.conversations.map((c) =>
          c.id === conversationId ? { ...c, unreadCount: 0 } : c,
        )
      } catch (error) {
        this.error = (error as Error).message || 'Failed to load chat.'
        this.messages[conversationId] = []
      } finally {
        this.loading = false
      }
    },
    async fetchConversationForListing(listingId: string) {
      this.error = ''
      const convo = await getConversationForListing(listingId)
      const existing = this.conversations.find((c) => c.id === convo.id)
      if (existing) {
        Object.assign(existing, convo)
      } else {
        this.conversations = [convo, ...this.conversations]
      }
      return convo
    },
    async fetchConversationForApplication(applicationId: string) {
      this.error = ''
      const convo = await getOrCreateConversationForApplication(applicationId)
      const existing = this.conversations.find((c) => c.id === convo.id)
      if (existing) {
        Object.assign(existing, convo)
      } else {
        this.conversations = [convo, ...this.conversations]
      }
      return convo
    },
    async sendMessage(conversationId: string, text: string) {
      this.error = ''
      try {
        const message = await sendMessageToConversation(conversationId, text)
        const thread = this.messages[conversationId] ?? []
        this.messages[conversationId] = [...thread, message]
        this.conversations = this.conversations.map((c) =>
          c.id === conversationId ? { ...c, lastMessage: message.text, time: message.time } : c,
        )
        return message
      } catch (error) {
        this.error = (error as Error).message || 'Failed to send message.'
        throw error
      }
    },
    async sendMessageForListing(listingId: string, text: string) {
      this.error = ''
      try {
        const message = await sendMessageToListing(listingId, text)
        const conversation = await this.fetchConversationForListing(listingId)
        const thread = this.messages[conversation.id] ?? []
        this.messages[conversation.id] = [...thread, message]
        return { conversation, message }
      } catch (error) {
        this.error = (error as Error).message || 'Failed to send message.'
        throw error
      }
    },
  },
})
