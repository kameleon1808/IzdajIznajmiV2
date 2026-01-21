import { defineStore } from 'pinia'
import {
  getConversationById,
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
    resolving: false,
    error: '',
    activeConversationId: '' as string | null,
  }),
  actions: {
    upsertConversation(conversation: Conversation) {
      const existing = this.conversations.find((c) => c.id === conversation.id)
      if (existing) {
        Object.assign(existing, conversation)
      } else {
        this.conversations = [conversation, ...this.conversations]
      }
    },

    setActiveConversation(conversationId: string | null) {
      this.activeConversationId = conversationId
    },

    clearError() {
      this.error = ''
    },

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
    async fetchConversationForListing(listingId: string, seekerId?: string) {
      this.error = ''
      const convo = await getConversationForListing(listingId, seekerId)
      this.upsertConversation(convo)
      return convo
    },
    async fetchConversationForApplication(applicationId: string) {
      this.error = ''
      const convo = await getOrCreateConversationForApplication(applicationId)
      this.upsertConversation(convo)
      return convo
    },
    async fetchConversationById(conversationId: string) {
      this.error = ''
      const convo = await getConversationById(conversationId)
      this.upsertConversation(convo)
      return convo
    },
    async openByListingId(listingId: string, seekerId?: string) {
      this.resolving = true
      this.error = ''
      try {
        const conversation = await this.fetchConversationForListing(listingId, seekerId)
        this.setActiveConversation(conversation.id)
        await this.fetchMessages(conversation.id)
        return conversation
      } catch (error) {
        this.error = (error as Error).message || 'Could not open chat.'
        throw error
      } finally {
        this.resolving = false
      }
    },
    async openByApplicationId(applicationId: string) {
      this.resolving = true
      this.error = ''
      try {
        const conversation = await this.fetchConversationForApplication(applicationId)
        this.setActiveConversation(conversation.id)
        await this.fetchMessages(conversation.id)
        return conversation
      } catch (error) {
        this.error = (error as Error).message || 'Could not open chat.'
        throw error
      } finally {
        this.resolving = false
      }
    },
    async openByConversationId(conversationId: string) {
      this.resolving = true
      this.error = ''
      try {
        const conversation = await this.fetchConversationById(conversationId)
        this.setActiveConversation(conversation.id)
        await this.fetchMessages(conversation.id)
        return conversation
      } catch (error) {
        this.error = (error as Error).message || 'Could not open chat.'
        throw error
      } finally {
        this.resolving = false
      }
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
