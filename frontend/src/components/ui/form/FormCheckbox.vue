<script setup lang="ts">
import { NCheckbox } from 'naive-ui'

interface Props {
  label?: string
  disabled?: boolean
}

defineProps<Props>()

const modelValue = defineModel<boolean>()
</script>

<template>
  <NCheckbox v-model:checked="modelValue" :disabled="disabled">
    {{ label }}
  </NCheckbox>
</template>
