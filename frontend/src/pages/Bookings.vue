<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { CalendarClock, MapPin, Star } from 'lucide-vue-next'
import Button from '../components/ui/Button.vue'
import { useBookingsStore } from '../stores/bookings'

const bookingsStore = useBookingsStore()
const tab = ref<'booked' | 'history'>('booked')

onMounted(() => {
  bookingsStore.fetchBookings()
})

const items = computed(() => (tab.value === 'booked' ? bookingsStore.booked : bookingsStore.history))
</script>

<template>
  <div class="space-y-4">
    <div class="grid grid-cols-2 gap-2 rounded-2xl bg-surface p-1">
      <button
        class="rounded-xl py-3 text-sm font-semibold"
        :class="tab === 'booked' ? 'bg-white shadow-soft text-slate-900' : 'text-muted'"
        @click="tab = 'booked'"
      >
        Booked
      </button>
      <button
        class="rounded-xl py-3 text-sm font-semibold"
        :class="tab === 'history' ? 'bg-white shadow-soft text-slate-900' : 'text-muted'"
        @click="tab = 'history'"
      >
        History
      </button>
    </div>

    <div class="space-y-3">
      <div
        v-for="booking in items"
        :key="booking.id"
        class="flex gap-3 rounded-2xl bg-white p-3 shadow-soft border border-white/60"
      >
        <div class="h-28 w-28 overflow-hidden rounded-2xl">
          <img :src="booking.coverImage" :alt="booking.listingTitle" class="h-full w-full object-cover" />
        </div>
        <div class="flex flex-1 flex-col gap-2">
          <div class="flex items-start justify-between">
            <div class="space-y-1">
              <p class="text-base font-semibold text-slate-900">{{ booking.listingTitle }}</p>
              <div class="flex items-center gap-1 text-xs text-muted">
                <MapPin class="h-4 w-4 text-primary" />
                <span>{{ booking.datesRange }}</span>
              </div>
            </div>
            <div class="flex items-center gap-1 text-sm font-semibold text-slate-900">
              <Star class="h-4 w-4 fill-primary text-primary" />
              <span>{{ booking.rating }}</span>
            </div>
          </div>

          <div class="flex items-center justify-between text-xs text-muted">
            <span>{{ booking.guestsText }}</span>
            <span>${{ booking.pricePerNight }}/night</span>
          </div>

          <div class="flex gap-2">
            <Button v-if="tab === 'booked'" variant="primary" class="flex-1">View Ticket</Button>
            <Button v-else variant="secondary" class="flex-1">Book Again</Button>
            <button class="rounded-xl bg-surface px-3 py-2 text-primary">
              <CalendarClock class="h-4 w-4" />
            </button>
          </div>
        </div>
      </div>
      <p v-if="!items.length" class="text-center text-muted">No bookings yet.</p>
    </div>
  </div>
</template>
