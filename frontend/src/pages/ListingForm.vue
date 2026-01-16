<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ImagePlus, MapPin } from 'lucide-vue-next'
import Badge from '../components/ui/Badge.vue'
import Button from '../components/ui/Button.vue'
import EmptyState from '../components/ui/EmptyState.vue'
import ErrorBanner from '../components/ui/ErrorBanner.vue'
import ListSkeleton from '../components/ui/ListSkeleton.vue'
import { useAuthStore } from '../stores/auth'
import { useListingsStore } from '../stores/listings'
import { getListingById, isMockApi } from '../services'
import type { Listing } from '../types'

const router = useRouter()
const route = useRoute()
const auth = useAuthStore()
const listingsStore = useListingsStore()
const loading = ref(false)
const submitting = ref(false)
const error = ref('')

const isEdit = computed(() => route.name === 'landlord-listing-edit')

const form = reactive({
  title: '',
  pricePerNight: 0,
  category: 'villa' as Listing['category'],
  address: '',
  city: '',
  country: '',
  description: '',
  beds: 1,
  baths: 1,
  images: [] as string[],
  facilities: [] as string[],
  lat: '',
  lng: '',
})
const existingImages = ref<string[]>([])
const newUploads = ref<{ file: File; preview: string }[]>([])

const loadListing = async () => {
  if (!isEdit.value) return
  loading.value = true
  error.value = ''
  try {
    const data = await getListingById(route.params.id as string)
    if (data) {
      newUploads.value = []
      form.title = data.title
      form.pricePerNight = data.pricePerNight
      form.category = data.category
      form.address = data.address || ''
      form.city = data.city
      form.country = data.country
      form.description = data.description || ''
      form.beds = data.beds
      form.baths = data.baths
      existingImages.value = data.images || (data.coverImage ? [data.coverImage] : [])
      form.facilities = data.facilities || []
      form.lat = data.lat?.toString() || ''
      form.lng = data.lng?.toString() || ''
    }
  } catch (err) {
    error.value = (err as Error).message || 'Failed to load listing.'
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  loadListing()
})

const isValid = computed(() =>
  !!form.title &&
  !!form.address &&
  !!form.city &&
  !!form.country &&
  form.pricePerNight > 0 &&
  form.description.trim().length >= 30,
)

const onFilesChange = (event: Event) => {
  const files = (event.target as HTMLInputElement).files
  if (!files) return
  Array.from(files).forEach((file) => {
    const preview = URL.createObjectURL(file)
    newUploads.value.push({ file, preview })
  })
}

const removeExisting = (url: string) => {
  existingImages.value = existingImages.value.filter((img) => img !== url)
}

const removeUpload = (preview: string) => {
  newUploads.value = newUploads.value.filter((item) => item.preview !== preview)
}

const totalImages = computed(() => existingImages.value.length + newUploads.value.length)

const save = async () => {
  if (!isValid.value) return
  submitting.value = true
  error.value = ''
  try {
    const payload: any = {
      ...form,
      lat: form.lat ? Number(form.lat) : undefined,
      lng: form.lng ? Number(form.lng) : undefined,
      ownerId: auth.user.id,
      keepImageUrls: existingImages.value,
      imagesFiles: newUploads.value.map((u) => u.file),
    }
    if (isMockApi) {
      payload.images = existingImages.value.concat(newUploads.value.map((u) => u.preview))
    }
    if (isEdit.value) {
      await listingsStore.updateListingAction(route.params.id as string, payload)
    } else {
      await listingsStore.createListing(payload)
    }
    router.push('/landlord/listings')
  } catch (err) {
    error.value = (err as Error).message || 'Save failed.'
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <div class="space-y-4">
    <div class="flex items-center justify-between">
      <h2 class="section-title">{{ isEdit ? 'Edit Listing' : 'New Listing' }}</h2>
      <Badge variant="pending">{{ isEdit ? 'Editing' : 'Draft' }}</Badge>
    </div>

    <ErrorBanner v-if="error" :message="error" />
    <ListSkeleton v-if="loading" :count="2" />

    <div v-else class="space-y-4 rounded-2xl bg-white p-4 shadow-soft border border-white/60">
      <div class="grid grid-cols-2 gap-3">
        <label class="text-sm font-semibold text-slate-900">
          Title
          <input
            v-model="form.title"
            type="text"
            class="mt-1 w-full rounded-xl border border-line px-3 py-3 text-sm focus:border-primary focus:outline-none"
            placeholder="Stay name"
          />
        </label>
        <label class="text-sm font-semibold text-slate-900">
          Price per night
          <input
            v-model.number="form.pricePerNight"
            type="number"
            min="0"
            class="mt-1 w-full rounded-xl border border-line px-3 py-3 text-sm focus:border-primary focus:outline-none"
            placeholder="180"
          />
        </label>
        <label class="text-sm font-semibold text-slate-900">
          Category
          <select
            v-model="form.category"
            class="mt-1 w-full rounded-xl border border-line px-3 py-3 text-sm capitalize focus:border-primary focus:outline-none"
          >
            <option value="villa">Villa</option>
            <option value="hotel">Hotel</option>
            <option value="apartment">Apartment</option>
          </select>
        </label>
        <label class="text-sm font-semibold text-slate-900">
          Address
          <input
            v-model="form.address"
            type="text"
            class="mt-1 w-full rounded-xl border border-line px-3 py-3 text-sm focus:border-primary focus:outline-none"
            placeholder="Street and number"
          />
        </label>
      </div>

      <div class="grid grid-cols-2 gap-3">
        <label class="text-sm font-semibold text-slate-900">
          City
          <input
            v-model="form.city"
            type="text"
            class="mt-1 w-full rounded-xl border border-line px-3 py-3 text-sm focus:border-primary focus:outline-none"
            placeholder="City"
          />
        </label>
        <label class="text-sm font-semibold text-slate-900">
          Country
          <input
            v-model="form.country"
            type="text"
            class="mt-1 w-full rounded-xl border border-line px-3 py-3 text-sm focus:border-primary focus:outline-none"
            placeholder="Country"
          />
        </label>
      </div>

      <label class="text-sm font-semibold text-slate-900">
        Description
        <textarea
          v-model="form.description"
          rows="3"
          class="mt-1 w-full rounded-xl border border-line px-3 py-3 text-sm text-slate-900 placeholder:text-muted focus:border-primary focus:outline-none"
          placeholder="Describe the experience"
        ></textarea>
        <span class="text-xs text-muted">{{ form.description.length }}/30</span>
      </label>

      <div class="grid grid-cols-2 gap-3">
        <label class="text-sm font-semibold text-slate-900">
          Beds
          <input
            v-model.number="form.beds"
            type="number"
            min="0"
            class="mt-1 w-full rounded-xl border border-line px-3 py-3 text-sm focus:border-primary focus:outline-none"
          />
        </label>
        <label class="text-sm font-semibold text-slate-900">
          Baths
          <input
            v-model.number="form.baths"
            type="number"
            min="0"
            class="mt-1 w-full rounded-xl border border-line px-3 py-3 text-sm focus:border-primary focus:outline-none"
          />
        </label>
      </div>

      <div class="grid grid-cols-2 gap-3">
        <label class="text-sm font-semibold text-slate-900">
          Latitude
          <input
            v-model="form.lat"
            type="text"
            class="mt-1 w-full rounded-xl border border-line px-3 py-3 text-sm focus:border-primary focus:outline-none"
            placeholder="Optional"
          />
        </label>
        <label class="text-sm font-semibold text-slate-900">
          Longitude
          <input
            v-model="form.lng"
            type="text"
            class="mt-1 w-full rounded-xl border border-line px-3 py-3 text-sm focus:border-primary focus:outline-none"
            placeholder="Optional"
          />
        </label>
      </div>

      <div class="space-y-2">
        <p class="text-sm font-semibold text-slate-900">Images</p>
        <div class="flex flex-wrap gap-3">
          <label
            class="flex h-28 w-28 cursor-pointer flex-col items-center justify-center rounded-2xl border border-dashed border-line bg-surface text-center text-xs text-muted"
          >
            <ImagePlus class="h-6 w-6 text-primary" />
            Upload
            <input type="file" multiple class="hidden" accept="image/*" @change="onFilesChange" />
          </label>
          <div
            v-for="img in existingImages"
            :key="img"
            class="relative h-28 w-28 overflow-hidden rounded-2xl border border-white/70"
          >
            <img :src="img" class="h-full w-full object-cover" />
            <button
              class="absolute right-1 top-1 rounded-full bg-white/90 px-2 py-1 text-xs font-semibold text-red-500 shadow-soft"
              @click="removeExisting(img)"
              type="button"
            >
              Remove
            </button>
          </div>
          <div
            v-for="item in newUploads"
            :key="item.preview"
            class="relative h-28 w-28 overflow-hidden rounded-2xl border border-white/70"
          >
            <img :src="item.preview" class="h-full w-full object-cover" />
            <button
              class="absolute right-1 top-1 rounded-full bg-white/90 px-2 py-1 text-xs font-semibold text-red-500 shadow-soft"
              @click="removeUpload(item.preview)"
              type="button"
            >
              Remove
            </button>
          </div>
        </div>
      </div>

      <div class="flex justify-end gap-2">
        <Button variant="secondary" @click="router.push('/landlord/listings')">Cancel</Button>
        <Button :disabled="!isValid || submitting" @click="save">
          {{ submitting ? 'Saving...' : 'Save listing' }}
        </Button>
      </div>
    </div>

    <EmptyState
      v-if="!loading && !isEdit && !totalImages"
      title="Tip: add photos"
      subtitle="Listings with photos get more requests"
      :icon="MapPin"
    />
  </div>
</template>
