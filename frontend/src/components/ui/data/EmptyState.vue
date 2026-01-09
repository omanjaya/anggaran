<script setup lang="ts">
import { NEmpty, NButton } from 'naive-ui'

interface Props {
  title?: string
  description?: string
  actionLabel?: string
  icon?: string
}

withDefaults(defineProps<Props>(), {
  title: 'Tidak ada data',
  description: 'Belum ada data yang tersedia'
})

const emit = defineEmits<{
  (e: 'action'): void
}>()
</script>

<template>
  <div class="flex flex-col items-center justify-center py-12">
    <NEmpty :description="title">
      <template #extra>
        <p class="text-gray-500 text-sm mb-4">{{ description }}</p>
        <NButton v-if="actionLabel" type="primary" @click="emit('action')">
          {{ actionLabel }}
        </NButton>
      </template>
    </NEmpty>
  </div>
</template>
