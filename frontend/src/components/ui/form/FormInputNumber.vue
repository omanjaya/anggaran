<script setup lang="ts">
import { NInputNumber } from 'naive-ui'

interface Props {
  label?: string
  required?: boolean
  error?: string
  placeholder?: string
  disabled?: boolean
  min?: number
  max?: number
  step?: number
  showButton?: boolean
  format?: (value: number | null) => string
  parse?: (value: string) => number | null
}

withDefaults(defineProps<Props>(), {
  showButton: true,
  step: 1
})

const modelValue = defineModel<number | null>()
</script>

<template>
  <div class="space-y-1">
    <label v-if="label" class="block text-sm font-medium text-gray-700">
      {{ label }}
      <span v-if="required" class="text-red-500">*</span>
    </label>
    <NInputNumber
      v-model:value="modelValue"
      :placeholder="placeholder"
      :disabled="disabled"
      :min="min"
      :max="max"
      :step="step"
      :show-button="showButton"
      :format="format"
      :parse="parse"
      :status="error ? 'error' : undefined"
      class="w-full"
    />
    <p v-if="error" class="text-sm text-red-500">{{ error }}</p>
  </div>
</template>
