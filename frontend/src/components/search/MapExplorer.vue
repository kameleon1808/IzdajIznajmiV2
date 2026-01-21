<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import 'leaflet/dist/leaflet.css'
import type { Listing } from '../../types'

const props = defineProps<{
  listings: Listing[]
  center: { lat: number; lng: number } | null
  radiusKm: number
  loading?: boolean
  missingGeoCount?: number
}>()

const emit = defineEmits<{
  (e: 'search-area', payload: { lat: number; lng: number }): void
  (e: 'radius-change', value: number): void
  (e: 'select-listing', id: string | number): void
}>()

const mapContainer = ref<HTMLElement | null>(null)
const mapInstance = ref<any>(null)
const markersLayer = ref<any>(null)
const radiusCircle = ref<any>(null)
const leafletRef = ref<any>(null)
const showSearchHere = ref(false)
const pendingCenter = ref<{ lat: number; lng: number } | null>(null)
const localRadius = ref(props.radiusKm ?? 10)
const ready = ref(false)

const effectiveCenter = computed(() => props.center ?? { lat: 45.815, lng: 15.9819 })
const missingGeoMessage = computed(() => {
  if (!props.missingGeoCount) return ''
  const suffix = props.missingGeoCount === 1 ? 'listing' : 'listings'
  return `${props.missingGeoCount} ${suffix} are missing map coordinates`
})

const initMap = async () => {
  if (ready.value || !mapContainer.value) return
  const leafletImport = await import('leaflet')
  const leaflet = leafletImport.default ?? leafletImport
  leafletRef.value = leaflet

  mapInstance.value = leaflet.map(mapContainer.value, {
    zoomControl: false,
    scrollWheelZoom: true,
    zoomSnap: 0.25,
    worldCopyJump: true,
  })
  mapInstance.value.setView([effectiveCenter.value.lat, effectiveCenter.value.lng], 11)

  leaflet
    .tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      maxZoom: 18,
      minZoom: 3,
      attribution: '&copy; OpenStreetMap',
    })
    .addTo(mapInstance.value)

  markersLayer.value = leaflet.layerGroup().addTo(mapInstance.value)
  radiusCircle.value = leaflet
    .circle([effectiveCenter.value.lat, effectiveCenter.value.lng], {
      radius: (props.radiusKm ?? 10) * 1000,
      color: '#2F80ED',
      weight: 1.5,
      fillColor: '#2F80ED',
      fillOpacity: 0.08,
    })
    .addTo(mapInstance.value)

  mapInstance.value.on('moveend', () => {
    const center = mapInstance.value.getCenter()
    pendingCenter.value = { lat: center.lat, lng: center.lng }
    showSearchHere.value = true
  })

  ready.value = true
  setTimeout(() => {
    mapInstance.value?.invalidateSize()
  }, 80)
  renderMarkers()
}

const renderMarkers = () => {
  if (!ready.value || !leafletRef.value || !markersLayer.value) return
  markersLayer.value.clearLayers()
  const leaflet = leafletRef.value
  const icon = leaflet.divIcon({
    className: 'map-pin',
    html: '<div class="pin-dot"></div>',
    iconSize: [20, 20],
    iconAnchor: [10, 10],
  })

  props.listings.forEach((listing) => {
    if (listing.lat == null || listing.lng == null) return
    const marker = leaflet
      .marker([listing.lat, listing.lng], { icon })
      .bindTooltip(
        `<div class="font-semibold text-slate-800">${listing.title}</div><div class="text-xs text-slate-500">${listing.city}</div>`,
        { direction: 'top', opacity: 0.9, offset: [0, -8] },
      )
    marker.on('click', () => emit('select-listing', listing.id))
    markersLayer.value.addLayer(marker)
  })
}

const updateCircle = () => {
  if (!radiusCircle.value || !props.radiusKm) return
  radiusCircle.value.setLatLng([effectiveCenter.value.lat, effectiveCenter.value.lng])
  radiusCircle.value.setRadius((props.radiusKm ?? 10) * 1000)
}

const recenter = () => {
  if (!mapInstance.value) return
  mapInstance.value.setView([effectiveCenter.value.lat, effectiveCenter.value.lng])
  updateCircle()
  showSearchHere.value = false
  pendingCenter.value = null
}

const emitSearchHere = () => {
  const center = pendingCenter.value ?? effectiveCenter.value
  emit('search-area', center)
  showSearchHere.value = false
}

const onRadiusInput = () => {
  emit('radius-change', localRadius.value)
}

watch(
  () => props.listings,
  () => renderMarkers(),
  { deep: true },
)

watch(
  () => props.center,
  () => {
    localRadius.value = props.radiusKm ?? localRadius.value
    recenter()
  },
)

watch(
  () => props.radiusKm,
  (val) => {
    if (val) {
      localRadius.value = val
      updateCircle()
    }
  },
)

onMounted(() => {
  initMap()
})

onBeforeUnmount(() => {
  mapInstance.value?.remove?.()
})
</script>

<template>
  <div class="relative overflow-hidden rounded-3xl border border-line bg-gradient-to-br from-white via-surface to-primary/5 shadow-card">
    <div ref="mapContainer" class="h-[420px] w-full" />

    <div class="pointer-events-none absolute inset-0">
      <div class="pointer-events-none absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2">
        <div class="h-3 w-3 rounded-full bg-primary shadow-soft" />
        <div class="mt-1 h-8 w-8 rounded-full border border-primary/30" />
      </div>

      <div class="pointer-events-auto absolute left-4 right-4 top-4 flex flex-col gap-3 md:left-6 md:right-6">
        <div class="flex flex-wrap items-center justify-between gap-3 rounded-2xl bg-white/90 p-3 shadow-soft backdrop-blur">
          <div>
            <p class="text-xs font-semibold uppercase tracking-[0.08em] text-muted">Radius</p>
            <p class="text-lg font-semibold text-slate-900">{{ localRadius }} km</p>
          </div>
          <input
            v-model.number="localRadius"
            type="range"
            min="1"
            max="50"
            step="1"
            class="h-2 w-full flex-1 appearance-none rounded-full bg-surface accent-primary md:w-64"
            @input="onRadiusInput"
          />
        </div>
        <div
          v-if="missingGeoMessage"
          class="flex items-center gap-2 rounded-2xl bg-amber-50 px-3 py-2 text-xs font-semibold text-amber-800 shadow-soft"
        >
          <span class="h-2 w-2 rounded-full bg-amber-500" />
          {{ missingGeoMessage }}
        </div>
      </div>

      <div class="pointer-events-auto absolute bottom-5 left-0 right-0 flex flex-col items-center gap-3">
        <button
          v-if="showSearchHere"
          class="rounded-full bg-primary px-4 py-2 text-sm font-semibold text-white shadow-soft transition hover:-translate-y-0.5"
          @click="emitSearchHere"
        >
          Search this area
        </button>
        <div class="flex items-center gap-2 rounded-full bg-white/90 px-4 py-2 text-xs font-semibold text-slate-700 shadow-soft">
          <span class="h-2.5 w-2.5 rounded-full bg-emerald-500 animate-pulse" />
          {{ listings.length }} {{ listings.length === 1 ? 'home' : 'homes' }} in view
          <span v-if="loading" class="text-muted">(refreshing...)</span>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.map-pin {
  background: none;
}
.map-pin .pin-dot {
  width: 16px;
  height: 16px;
  background: #2f80ed;
  border-radius: 999px;
  box-shadow: 0 10px 30px rgba(47, 128, 237, 0.28);
  border: 2px solid white;
}
</style>
