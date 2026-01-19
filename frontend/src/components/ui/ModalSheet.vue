<script setup lang="ts">
const props = defineProps<{ modelValue: boolean; title?: string }>()
const emit = defineEmits(['update:modelValue'])

const close = () => emit('update:modelValue', false)
</script>

<template>
  <teleport to="body">
    <transition name="fade">
      <div v-if="modelValue" class="fixed inset-0 z-50 bg-black/40" @click.self="close"></div>
    </transition>
    <transition name="slide-up">
      <div
        v-if="modelValue"
        class="fixed inset-x-0 bottom-0 z-50 flex justify-center px-3 pb-3"
      >
        <div
          class="w-full max-w-3xl rounded-t-3xl bg-white p-4 shadow-card md:pb-6"
          :class="'modal-sheet-body'"
        >
          <div class="mx-auto mb-3 h-1.5 w-10 rounded-full bg-slate-200 md:hidden" />
          <div class="space-y-4 max-h-[88vh] overflow-y-auto">
            <div v-if="title" class="flex items-center justify-between px-2">
              <h3 class="text-lg font-semibold text-slate-900">{{ title }}</h3>
              <button class="text-muted" @click="close">Close</button>
            </div>
            <slot />
          </div>
          <div class="h-2"></div>
        </div>
      </div>
    </transition>
  </teleport>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}

.slide-up-enter-active,
.slide-up-leave-active {
  transition: transform 0.28s ease, opacity 0.28s ease;
}
.slide-up-enter-from,
.slide-up-leave-to {
  transform: translateY(100%);
  opacity: 0;
}
</style>
