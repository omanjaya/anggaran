<script setup lang="ts">
import { NSelect } from 'naive-ui'
import type { SelectOption } from 'naive-ui'

interface Props {
  label?: string
  required?: boolean
  error?: string
  placeholder?: string
  options: SelectOption[]
  disabled?: boolean
  clearable?: boolean
  filterable?: boolean
  multiple?: boolean
}

withDefaults(defineProps<Props>(), {
  clearable: false,
  filterable: false,
  multiple: false
})

const modelValue = defineModel<string | number | Array<string | number> | null>()
</script>

<template>
  <div class="space-y-1">
    <label v-if="label" class="block text-sm font-medium text-gray-700">
      {{ label }}
      <span v-if="required" class="text-red-500">*</span>
    </label>
    <NSelect
      v-model:value="modelValue"
      :placeholder="placeholder"
      :options="options"
      :disabled="disabled"
      :clearable="clearable"
      :filterable="filterable"
      :multiple="multiple"
      :status="error ? 'error' : undefined"
    />
    <p v-if="error" class="text-sm text-red-500">{{ error }}</p>
  </div>
</template>
