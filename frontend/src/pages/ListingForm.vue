<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ImagePlus, MapPin, MoveLeft, MoveRight, Star } from 'lucide-vue-next'
import Badge from '../components/ui/Badge.vue'
import Button from '../components/ui/Button.vue'
import EmptyState from '../components/ui/EmptyState.vue'
import ErrorBanner from '../components/ui/ErrorBanner.vue'
import ListSkeleton from '../components/ui/ListSkeleton.vue'
import { useAuthStore } from '../stores/auth'
import { useListingsStore } from '../stores/listings'
import { getListingById, isMockApi } from '../services'
import type { Listing } from '../types'

type GalleryItem = {
  key: string
  url?: string
  preview?: string
  file?: File
  isNew: boolean
  isCover: boolean
  sortOrder: number
  keep: boolean
  processingStatus?: string
}

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
  facilities: [] as string[],
  lat: '',
  lng: '',
})

const gallery = ref<GalleryItem[]>([])

const loadListing = async () => {
  if (!isEdit.value) return
  loading.value = true
  error.value = ''
  try {
    const data = await getListingById(route.params.id as string)
    if (data) {
      gallery.value = []
      form.title = data.title
      form.pricePerNight = data.pricePerNight
      form.category = data.category
      form.address = data.address || ''
      form.city = data.city
      form.country = data.country
      form.description = data.description || ''
      form.beds = data.beds
      form.baths = data.baths
      const detailed: { url: string; sortOrder: number; isCover?: boolean; processingStatus?: string }[] = data.imagesDetailed?.length
        ? data.imagesDetailed
        : (data.images || []).map((url: string, idx: number) => ({ url, sortOrder: idx, isCover: idx === 0, processingStatus: 'done' }))
      const sorted = [...detailed].sort((a, b) => a.sortOrder - b.sortOrder)
      gallery.value = sorted.map((img, idx) => ({
        key: `existing-${idx}-${img.url}`,
        url: img.url,
        isNew: false,
        isCover: img.isCover ?? idx === 0,
        sortOrder: idx,
        keep: true,
        processingStatus: img.processingStatus ?? 'done',
      }))
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
    gallery.value.push({
      key: `new-${preview}`,
      preview,
      file,
      isNew: true,
      isCover: gallery.value.every((g) => !g.isCover),
      sortOrder: gallery.value.length,
      keep: true,
      processingStatus: 'pending',
    })
  })
}

const activeGallery = computed<GalleryItem[]>(() => gallery.value.filter((g) => g.keep))

const setCover = (key: string) => {
  gallery.value = gallery.value.map((item) => ({ ...item, isCover: item.key === key ? true : false }))
}

const removeItem = (key: string) => {
  gallery.value = gallery.value.map((item) =>
    item.key === key ? { ...item, keep: false, isCover: false } : item,
  )
  if (!gallery.value.some((g) => g.isCover && g.keep) && gallery.value.some((g) => g.keep)) {
    const first = gallery.value.find((g) => g.keep)
    if (first) first.isCover = true
  }
}

const move = (key: string, direction: -1 | 1) => {
  const items: GalleryItem[] = [...activeGallery.value].sort((a, b) => a.sortOrder - b.sortOrder)
  const index = items.findIndex((i) => i.key === key)
  if (index === -1) return
  const swapIndex = index + direction
  if (swapIndex < 0 || swapIndex >= items.length) return
  const temp = items[index] as GalleryItem
  items[index] = items[swapIndex] as GalleryItem
  items[swapIndex] = temp
  items.forEach((item, idx) => (item.sortOrder = idx))
  gallery.value = gallery.value.map((g) => {
    const updated = items.find((i) => i.key === g.key)
    return updated ? updated : g
  })
}

const totalImages = computed(() => activeGallery.value.length)
const hasPendingImages = computed(() => activeGallery.value.some((g) => g.processingStatus && g.processingStatus !== 'done'))
const canPublish = computed(() => isValid.value && totalImages.value > 0 && !hasPendingImages.value)

const save = async () => {
  if (!isValid.value) return
  submitting.value = true
  error.value = ''
  const ordered = [...activeGallery.value].sort((a, b) => a.sortOrder - b.sortOrder)
  ordered.forEach((item, idx) => (item.sortOrder = idx))
  try {
    const payload: any = {
      ...form,
      lat: form.lat ? Number(form.lat) : undefined,
      lng: form.lng ? Number(form.lng) : undefined,
      ownerId: auth.user.id,
      keepImages: ordered
        .filter((g) => !g.isNew)
        .map((g) => ({
          url: g.url,
          sortOrder: g.sortOrder,
          isCover: g.isCover,
          processingStatus: g.processingStatus,
        })),
      removeImageUrls: gallery.value.filter((g) => !g.keep && !g.isNew && g.url).map((g) => g.url),
      imagesFiles: ordered.filter((g) => g.isNew && g.file).map((g) => g.file),
    }
    if (isMockApi) {
      payload.images = ordered.map((g) => g.url ?? g.preview ?? '')
    }
    if (isEdit.value) {
      await listingsStore.updateListingAction(route.params.id as string, payload)
    } else {
      payload.coverIndex = ordered.findIndex((g) => g.isCover)
      await listingsStore.createListing(payload)
    }
    router.push('/landlord/listings')
  } catch (err) {
    error.value = (err as Error).message || 'Save failed.'
  } finally {
    submitting.value = false
  }
}

const publishNow = async () => {
  if (!canPublish.value) {
    error.value = 'Need at least one processed image and valid fields to publish.'
    return
  }
  submitting.value = true
  error.value = ''
  try {
    let listingId = route.params.id as string | undefined
    if (!isEdit.value) {
      const created = await listingsStore.createListing({
        ...form,
        lat: form.lat ? Number(form.lat) : undefined,
        lng: form.lng ? Number(form.lng) : undefined,
        ownerId: auth.user.id,
        keepImages: [],
        imagesFiles: activeGallery.value.filter((g) => g.isNew && g.file).map((g) => g.file),
      } as any)
      listingId = created.id
    } else {
      await save()
      listingId = route.params.id as string
    }
    if (listingId) {
      await listingsStore.publishListingAction(listingId)
      router.push('/landlord/listings')
    }
  } catch (err: any) {
    error.value = err.message ?? 'Publish failed.'
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
            v-for="(item, idx) in activeGallery"
            :key="item.key"
            class="relative h-32 w-32 overflow-hidden rounded-2xl border border-white/70 bg-white shadow-soft"
          >
            <img :src="item.url || item.preview" class="h-full w-full object-cover" loading="lazy" />
            <div class="absolute inset-x-1 top-1 flex items-center gap-1 text-[11px] font-semibold">
              <span
                class="rounded-full px-2 py-1"
                :class="item.isCover ? 'bg-primary text-white' : 'bg-black/50 text-white'"
              >
                {{ item.isCover ? 'Cover' : 'Image' }}
              </span>
              <span class="ml-auto rounded-full bg-white/80 px-2 py-1 text-slate-800 shadow-soft">#{{ idx + 1 }}</span>
              <span
                v-if="item.processingStatus && item.processingStatus !== 'done'"
                class="rounded-full bg-amber-500/90 px-2 py-1 text-white"
              >
                Processing
              </span>
            </div>
            <div class="absolute bottom-1 left-1 right-1 flex items-center gap-1">
              <button
                class="rounded-full bg-white/90 p-2 text-primary shadow-soft"
                type="button"
                @click="move(item.key, -1)"
              >
                <MoveLeft class="h-4 w-4" />
              </button>
              <button
                class="rounded-full bg-white/90 p-2 text-primary shadow-soft"
                type="button"
                @click="move(item.key, 1)"
              >
                <MoveRight class="h-4 w-4" />
              </button>
              <button
                class="flex-1 rounded-full bg-primary/90 px-3 py-2 text-center text-xs font-semibold text-white shadow-soft"
                type="button"
                @click="setCover(item.key)"
              >
                <Star class="mr-1 inline h-4 w-4" /> Cover
              </button>
              <button
                class="rounded-full bg-white/90 px-2 py-1 text-xs font-semibold text-red-500 shadow-soft"
                @click="removeItem(item.key)"
                type="button"
              >
                Remove
              </button>
            </div>
          </div>
        </div>
      </div>

      <div class="flex justify-end gap-2">
        <Button variant="secondary" @click="router.push('/landlord/listings')">Cancel</Button>
        <Button v-if="isEdit" variant="primary" :disabled="!canPublish || submitting" @click="publishNow">
          Publish
        </Button>
        <Button :disabled="!isValid || submitting" @click="save">
          {{ submitting ? 'Saving...' : isEdit ? 'Save changes' : 'Save draft' }}
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
