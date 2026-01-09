<script setup lang="ts">
import { NAlert } from 'naive-ui'

interface Props {
  type?: 'info' | 'success' | 'warning' | 'error'
  title?: string
  message: string
  closable?: boolean
  showIcon?: boolean
}

withDefaults(defineProps<Props>(), {
  type: 'info',
  closable: false,
  showIcon: true
})

const emit = defineEmits<{
  (e: 'close'): void
}>()
</script>

<template>
  <NAlert
    :type="type"
    :title="title"
    :closable="closable"
    :show-icon="showIcon"
    @close="emit('close')"
  >
    {{ message }}
  </NAlert>
</template>
