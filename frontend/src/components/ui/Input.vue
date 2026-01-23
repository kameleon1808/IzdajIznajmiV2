<script setup lang="ts">
import { computed, type Component, type PropType } from 'vue'

const props = defineProps({
  modelValue: { type: [String, Number], default: '' },
  placeholder: { type: String, default: '' },
  // allow both component objects and functional components to avoid prop warnings
  leftIcon: { type: [Object, Function] as PropType<Component>, default: undefined },
  rightIcon: { type: [Object, Function] as PropType<Component>, default: undefined },
  disabled: { type: Boolean, default: false },
  readonly: { type: Boolean, default: false },
  type: { type: String, default: 'text' },
})

const emit = defineEmits(['update:modelValue', 'rightIconClick', 'leftIconClick', 'focus'])

const classes = computed(() =>
  [
    'flex items-center gap-3 rounded-2xl bg-white px-4 py-3 shadow-soft border border-white/70',
    props.disabled ? 'opacity-60' : ''],
)

const onInput = (e: Event) => emit('update:modelValue', (e.target as HTMLInputElement).value)
</script>

<template>
  <label :class="classes">
    <button
      v-if="leftIcon"
      type="button"
      class="flex items-center justify-center"
      @click="emit('leftIconClick')"
    >
      <component :is="leftIcon" class="h-5 w-5 text-muted" />
    </button>
    <input
      :value="modelValue"
      :placeholder="placeholder"
      :disabled="disabled"
      :readonly="readonly"
      :type="type"
      class="flex-1 bg-transparent text-sm font-medium text-slate-900 placeholder:text-muted focus:outline-none"
      @input="onInput"
      @focus="emit('focus')"
    />
    <button
      v-if="rightIcon"
      type="button"
      class="rounded-full bg-primary/10 p-2"
      @click="emit('rightIconClick')"
    >
      <component :is="rightIcon" class="h-4 w-4 text-primary" />
    </button>
  </label>
</template>
