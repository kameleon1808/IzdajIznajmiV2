import { defineStore } from 'pinia'
import type { Contract, RentalTransaction } from '../types'
import {
  createTransaction,
  getTransaction,
  generateTransactionContract,
  signTransactionContract,
  createDepositSession,
  confirmMoveIn,
  getAdminTransactions,
  getAdminTransaction,
  markAdminTransactionDisputed,
  cancelAdminTransaction,
  payoutAdminTransaction,
} from '../services'

export const useTransactionsStore = defineStore('transactions', {
  state: () => ({
    current: null as RentalTransaction | null,
    adminList: [] as RentalTransaction[],
    loading: false,
    error: '',
  }),
  actions: {
    clearError() {
      this.error = ''
    },
    async fetchTransaction(id: string) {
      this.loading = true
      this.error = ''
      try {
        this.current = await getTransaction(id)
        return this.current
      } catch (error) {
        this.error = (error as Error).message || 'Failed to load transaction.'
        this.current = null
        throw error
      } finally {
        this.loading = false
      }
    },
    async startTransaction(payload: {
      listingId: string
      seekerId: string
      depositAmount?: number | null
      rentAmount?: number | null
      currency?: string
    }) {
      this.error = ''
      try {
        const tx = await createTransaction(payload)
        this.current = tx
        return tx
      } catch (error) {
        this.error = (error as Error).message || 'Failed to start transaction.'
        throw error
      }
    },
    async generateContract(transactionId: string, payload: { startDate: string; terms?: string }): Promise<Contract> {
      this.error = ''
      try {
        const contract = await generateTransactionContract(transactionId, payload)
        if (this.current && this.current.id === transactionId) {
          this.current.contract = contract
          this.current.status = 'contract_generated'
        }
        return contract
      } catch (error) {
        this.error = (error as Error).message || 'Failed to generate contract.'
        throw error
      }
    },
    async signContract(contractId: string, payload: { typedName: string; consent: boolean }) {
      this.error = ''
      try {
        const contract = await signTransactionContract(contractId, payload)
        if (this.current && this.current.contract?.id === contractId) {
          this.current.contract = contract
        }
        return contract
      } catch (error) {
        this.error = (error as Error).message || 'Failed to sign contract.'
        throw error
      }
    },
    async createDepositSession(transactionId: string) {
      this.error = ''
      try {
        const result = await createDepositSession(transactionId)
        if (this.current && this.current.id === transactionId && result.payment) {
          this.current.payments = [...this.current.payments, result.payment]
        }
        return result
      } catch (error) {
        this.error = (error as Error).message || 'Failed to start deposit payment.'
        throw error
      }
    },
    async confirmMoveIn(transactionId: string) {
      this.error = ''
      try {
        const tx = await confirmMoveIn(transactionId)
        if (this.current && this.current.id === transactionId) {
          this.current = tx
        }
        return tx
      } catch (error) {
        this.error = (error as Error).message || 'Failed to confirm move-in.'
        throw error
      }
    },
    async fetchAdminTransactions(status?: string) {
      this.loading = true
      this.error = ''
      try {
        this.adminList = await getAdminTransactions(status ? { status } : undefined)
        return this.adminList
      } catch (error) {
        this.error = (error as Error).message || 'Failed to load transactions.'
        this.adminList = []
        throw error
      } finally {
        this.loading = false
      }
    },
    async fetchAdminTransaction(id: string) {
      this.loading = true
      this.error = ''
      try {
        this.current = await getAdminTransaction(id)
        return this.current
      } catch (error) {
        this.error = (error as Error).message || 'Failed to load transaction.'
        throw error
      } finally {
        this.loading = false
      }
    },
    async markDisputed(id: string) {
      this.error = ''
      try {
        const tx = await markAdminTransactionDisputed(id)
        this.current = tx
        return tx
      } catch (error) {
        this.error = (error as Error).message || 'Failed to mark disputed.'
        throw error
      }
    },
    async cancelTransaction(id: string) {
      this.error = ''
      try {
        const tx = await cancelAdminTransaction(id)
        this.current = tx
        return tx
      } catch (error) {
        this.error = (error as Error).message || 'Failed to cancel transaction.'
        throw error
      }
    },
    async payoutTransaction(id: string) {
      this.error = ''
      try {
        const tx = await payoutAdminTransaction(id)
        this.current = tx
        return tx
      } catch (error) {
        this.error = (error as Error).message || 'Failed to mark payout.'
        throw error
      }
    },
  },
})
