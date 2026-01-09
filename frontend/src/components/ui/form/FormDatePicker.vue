<script setup lang="ts">
import { NDatePicker } from 'naive-ui'

interface Props {
  label?: string
  required?: boolean
  error?: string
  placeholder?: string
  type?: 'date' | 'datetime' | 'month' | 'year' | 'daterange' | 'monthrange'
  disabled?: boolean
  clearable?: boolean
  format?: string
}

withDefaults(defineProps<Props>(), {
  type: 'date',
  clearable: true,
  format: 'dd/MM/yyyy'
})

const modelValue = defineModel<number | [number, number] | null>()
</script>

<template>
  <div class="space-y-1">
    <label v-if="label" class="block text-sm font-medium text-gray-700">
      {{ label }}
      <span v-if="required" class="text-red-500">*</span>
    </label>
    <NDatePicker
      v-model:value="modelValue"
      :placeholder="placeholder"
      :type="type"
      :disabled="disabled"
      :clearable="clearable"
      :format="format"
      :status="error ? 'error' : undefined"
      class="w-full"
    />
    <p v-if="error" class="text-sm text-red-500">{{ error }}</p>
  </div>
</template>
