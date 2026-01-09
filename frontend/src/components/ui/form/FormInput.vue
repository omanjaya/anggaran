<script setup lang="ts">
import { NInput } from 'naive-ui'

interface Props {
  label?: string
  required?: boolean
  error?: string
  placeholder?: string
  type?: 'text' | 'password' | 'textarea'
  disabled?: boolean
  rows?: number
}

withDefaults(defineProps<Props>(), {
  type: 'text',
  rows: 3
})

const modelValue = defineModel<string>()
</script>

<template>
  <div class="space-y-1">
    <label v-if="label" class="block text-sm font-medium text-gray-700">
      {{ label }}
      <span v-if="required" class="text-red-500">*</span>
    </label>
    <NInput
      v-model:value="modelValue"
      :placeholder="placeholder"
      :type="type"
      :disabled="disabled"
      :rows="rows"
      :status="error ? 'error' : undefined"
    />
    <p v-if="error" class="text-sm text-red-500">{{ error }}</p>
  </div>
</template>
