<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps({
  variant: { type: String, default: 'primary' },
  block: { type: Boolean, default: false },
  size: { type: String, default: 'md' },
  type: { type: String, default: 'button' },
  disabled: { type: Boolean, default: false },
})

const classes = computed(() => {
  const base = 'inline-flex items-center justify-center rounded-full font-semibold transition shadow-soft'
  const variants: Record<string, string> = {
    primary: 'bg-primary text-white hover:bg-primary-dark',
    secondary: 'bg-white text-slate-900 border border-line hover:border-primary/50',
    ghost: 'bg-white/60 text-slate-900 border border-white/70',
    danger: 'bg-red-500 text-white hover:bg-red-600 shadow-none',
  }
  const sizes: Record<string, string> = {
    md: 'h-12 px-5 text-sm',
    lg: 'h-14 px-6 text-base',
  }

  return [
    base,
    variants[props.variant] || variants.primary,
    sizes[props.size] || sizes.md,
    props.block ? 'w-full' : '',
    props.disabled ? 'opacity-60 cursor-not-allowed' : '',
  ]
})
</script>

<template>
  <button :type="(type as any)" :class="classes" :disabled="disabled">
    <slot />
  </button>
</template>
