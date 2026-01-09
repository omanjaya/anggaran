<script setup lang="ts">
interface Props {
  label: string
  value: string | number
  prefix?: string
  suffix?: string
  variant?: 'default' | 'primary' | 'success' | 'warning' | 'error'
}

withDefaults(defineProps<Props>(), {
  variant: 'default'
})

const variantClasses: Record<string, string> = {
  default: 'bg-white border-gray-200',
  primary: 'bg-blue-50 border-blue-200',
  success: 'bg-green-50 border-green-200',
  warning: 'bg-yellow-50 border-yellow-200',
  error: 'bg-red-50 border-red-200',
}

const textClasses: Record<string, string> = {
  default: 'text-gray-900',
  primary: 'text-blue-700',
  success: 'text-green-700',
  warning: 'text-yellow-700',
  error: 'text-red-700',
}

const labelClasses: Record<string, string> = {
  default: 'text-gray-600',
  primary: 'text-blue-600',
  success: 'text-green-600',
  warning: 'text-yellow-600',
  error: 'text-red-600',
}
</script>

<template>
  <div :class="['rounded-lg shadow p-4 border', variantClasses[variant]]">
    <p :class="['text-sm', labelClasses[variant]]">{{ label }}</p>
    <p :class="['text-2xl font-bold', textClasses[variant]]">
      <span v-if="prefix">{{ prefix }}</span>
      {{ value }}
      <span v-if="suffix">{{ suffix }}</span>
    </p>
    <slot />
  </div>
</template>
