<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref, shallowRef, watch } from 'vue'

const props = defineProps<{
  lat?: number
  lng?: number
  draggable?: boolean
  zoom?: number
}>()

const emit = defineEmits<{
  (e: 'update', value: { lat: number; lng: number }): void
}>()

const mapEl = ref<HTMLDivElement | null>(null)
const ready = ref(false)
const loading = ref(false)
const error = ref('')
const map = shallowRef<any>(null)
const marker = shallowRef<any>(null)
let leaflet: typeof import('leaflet') | null = null

const initMap = async () => {
  if (ready.value || loading.value || props.lat == null || props.lng == null || !mapEl.value) return
  loading.value = true
  error.value = ''
  try {
    leaflet = await import('leaflet')
    await import('leaflet/dist/leaflet.css')

    map.value = leaflet
      .map(mapEl.value, { zoomControl: true, attributionControl: false })
      .setView([props.lat, props.lng], props.zoom ?? 15)

    leaflet
      .tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap contributors',
      })
      .addTo(map.value)

    marker.value = leaflet.marker([props.lat, props.lng], { draggable: props.draggable ?? false }).addTo(map.value)

    if (props.draggable && marker.value) {
      marker.value.on('drag', handleDrag)
      marker.value.on('dragend', handleDrag)
      marker.value.on('moveend', handleDrag)
    }

    ready.value = true
  } catch (err) {
    error.value = (err as Error).message || 'Map unavailable'
  } finally {
    loading.value = false
  }
}

const handleDrag = () => {
  if (!marker.value) return
  const pos = marker.value.getLatLng()
  emit('update', { lat: Number(pos.lat), lng: Number(pos.lng) })
}

watch(
  () => [props.lat, props.lng],
  () => {
    if (props.lat == null || props.lng == null) {
      return
    }
    if (!ready.value) {
      initMap()
      return
    }
    if (marker.value && leaflet) {
      marker.value.setLatLng([props.lat, props.lng])
    }
    if (map.value && leaflet) {
      map.value.setView([props.lat, props.lng])
    }
  },
)

watch(
  () => props.draggable,
  (draggable) => {
    if (!marker.value) return
    if (draggable) {
      marker.value.dragging?.enable()
    } else {
      marker.value.dragging?.disable()
    }
  },
)

onMounted(initMap)

onBeforeUnmount(() => {
  if (map.value) {
    map.value.remove()
  }
})
</script>

<template>
  <div class="relative h-64 w-full overflow-hidden rounded-2xl border border-line bg-surface">
    <div ref="mapEl" class="h-full w-full"></div>
    <div v-if="!ready && !error" class="absolute inset-0 bg-surface shimmer"></div>
    <div
      v-if="error"
      class="absolute inset-0 flex items-center justify-center bg-surface/80 px-3 text-center text-sm text-muted"
    >
      {{ error }}
    </div>
  </div>
</template>
