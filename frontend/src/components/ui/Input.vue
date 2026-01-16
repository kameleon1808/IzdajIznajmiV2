<script setup lang="ts">
import { computed, type Component } from 'vue'

const props = defineProps({
  modelValue: { type: String, default: '' },
  placeholder: { type: String, default: '' },
  leftIcon: { type: Object as () => Component, default: undefined },
  rightIcon: { type: Object as () => Component, default: undefined },
  disabled: { type: Boolean, default: false },
  readonly: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue', 'rightIconClick', 'focus'])

const classes = computed(() =>
  [
    'flex items-center gap-3 rounded-2xl bg-white px-4 py-3 shadow-soft border border-white/70',
    props.disabled ? 'opacity-60' : ''],
)

const onInput = (e: Event) => emit('update:modelValue', (e.target as HTMLInputElement).value)
</script>

<template>
  <label :class="classes">
    <component v-if="leftIcon" :is="leftIcon" class="h-5 w-5 text-muted" />
    <input
      :value="modelValue"
      :placeholder="placeholder"
      :disabled="disabled"
      :readonly="readonly"
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
