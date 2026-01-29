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
    async fetchMessages(conversationId: string, options?: { silent?: boolean }) {
      const silent = options?.silent ?? false
      if (!silent) {
        this.loading = true
        this.error = ''
      }
      try {
        const data = await getMessages(conversationId)
        this.messages[conversationId] = data
        if (!silent) {
          await markConversationRead(conversationId)
          this.conversations = this.conversations.map((c) =>
            c.id === conversationId ? { ...c, unreadCount: 0 } : c,
          )
        }
      } catch (error) {
        if (!silent) {
          this.error = (error as Error).message || 'Failed to load chat.'
          this.messages[conversationId] = []
        }
      } finally {
        if (!silent) {
          this.loading = false
        }
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
    async sendMessage(
      conversationId: string,
      text: string,
      attachments?: File[],
      onProgress?: (progress: number) => void,
    ) {
      this.error = ''
      try {
        const message = await sendMessageToConversation(conversationId, text, attachments, onProgress)
        const thread = this.messages[conversationId] ?? []
        this.messages[conversationId] = [...thread, message]
        const lastMessage = message.text?.trim()
          ? message.text
          : message.attachments?.length
            ? 'Sent an attachment'
            : ''
        this.conversations = this.conversations.map((c) =>
          c.id === conversationId ? { ...c, lastMessage, time: message.time } : c,
        )
        return message
      } catch (error) {
        this.error = (error as Error).message || 'Failed to send message.'
        throw error
      }
    },
    async sendMessageForListing(
      listingId: string,
      text: string,
      attachments?: File[],
      onProgress?: (progress: number) => void,
    ) {
      this.error = ''
      try {
        const message = await sendMessageToListing(listingId, text, attachments, onProgress)
        const conversation = await this.fetchConversationForListing(listingId)
        const thread = this.messages[conversation.id] ?? []
        this.messages[conversation.id] = [...thread, message]
        return { conversation, message }
      } catch (error) {
        this.error = (error as Error).message || 'Failed to send message.'
        throw error
      }
    },
    receiveMessage(message: Message) {
      const conversationId = message.conversationId
      const thread = this.messages[conversationId] ?? []

      if (thread.some((item) => item.id === message.id)) {
        return
      }

      this.messages[conversationId] = [...thread, message]

      const lastMessage = message.text?.trim()
        ? message.text
        : message.attachments?.length
          ? 'Sent an attachment'
          : ''

      const isActive = this.activeConversationId === conversationId
      if (isActive) {
        markConversationRead(conversationId).catch(() => undefined)
      }

      this.conversations = this.conversations.map((c) =>
        c.id === conversationId
          ? {
              ...c,
              lastMessage,
              time: message.time,
              unreadCount: isActive ? 0 : Math.max(0, (c.unreadCount ?? 0) + 1),
            }
          : c,
      )

      if (!this.conversations.some((c) => c.id === conversationId)) {
        this.fetchConversationById(conversationId).catch(() => undefined)
      }
    },
  },
})
