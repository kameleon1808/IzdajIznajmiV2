<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import 'leaflet/dist/leaflet.css'
import type { Listing } from '../../types'
import markerIconUrl from 'leaflet/dist/images/marker-icon.png'
import markerIconRetinaUrl from 'leaflet/dist/images/marker-icon-2x.png'
import markerShadowUrl from 'leaflet/dist/images/marker-shadow.png'

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
  (e: 'center-change', payload: { lat: number; lng: number }): void
}>()

const mapContainer = ref<HTMLElement | null>(null)
const mapInstance = ref<any>(null)
const markersLayer = ref<any>(null)
const radiusCircle = ref<any>(null)
const leafletRef = ref<any>(null)
const showSearchHere = ref(false)
const mapCenter = ref<{ lat: number; lng: number } | null>(props.center)
const ready = ref(false)
const pinIcon = ref<any>(null)

const defaultCenter = { lat: 44.8125, lng: 20.4612 } // Belgrade
const effectiveCenter = computed(() => mapCenter.value ?? props.center ?? defaultCenter)
const initMap = async () => {
  if (ready.value || !mapContainer.value) return
  const leafletImport = await import('leaflet')
  const leaflet = leafletImport.default ?? leafletImport
  leafletRef.value = leaflet
  pinIcon.value = leaflet.icon({
    iconUrl: markerIconUrl,
    iconRetinaUrl: markerIconRetinaUrl,
    shadowUrl: markerShadowUrl,
    iconSize: [25, 41],
    iconAnchor: [12, 41],
    popupAnchor: [1, -34],
    shadowSize: [41, 41],
  })

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

  mapInstance.value.on('move', () => {
    const center = mapInstance.value.getCenter()
    mapCenter.value = { lat: center.lat, lng: center.lng }
    radiusCircle.value?.setLatLng(center)
    emit('center-change', { lat: center.lat, lng: center.lng })
  })

  mapInstance.value.on('moveend', () => {
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
      .marker([listing.lat, listing.lng], { icon: pinIcon.value ?? icon })
      .bindTooltip(
        `<div class="font-semibold text-slate-800">${listing.title}</div><div class="text-xs text-slate-500">${listing.city}</div>`,
        { direction: 'top', opacity: 0.9, offset: [0, -8] },
      )
    marker.on('click', () => emit('select-listing', listing.id))
    markersLayer.value.addLayer(marker)
  })
}

const updateCircle = () => {
  if (!ready.value || !radiusCircle.value || !radiusCircle.value._map) return
  radiusCircle.value.setLatLng([effectiveCenter.value.lat, effectiveCenter.value.lng])
  radiusCircle.value.setRadius((props.radiusKm ?? 10) * 1000)
}

const recenter = () => {
  if (!ready.value || !mapInstance.value) return
  mapInstance.value.setView([effectiveCenter.value.lat, effectiveCenter.value.lng])
  updateCircle()
}

watch(
  () => props.listings,
  () => renderMarkers(),
  { deep: true },
)

watch(
  () => props.center,
  () => {
    mapCenter.value = props.center
    recenter()
  },
)

watch(
  () => props.radiusKm,
  (val) => {
    if (val) {
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

      <div class="pointer-events-none absolute inset-0"></div>
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
