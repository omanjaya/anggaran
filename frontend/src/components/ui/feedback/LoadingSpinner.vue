<script setup lang="ts">
import { NSpin } from 'naive-ui'

interface Props {
  size?: 'small' | 'medium' | 'large'
  text?: string
  fullscreen?: boolean
}

withDefaults(defineProps<Props>(), {
  size: 'medium',
  fullscreen: false
})
</script>

<template>
  <div
    :class="[
      'flex flex-col items-center justify-center',
      fullscreen ? 'fixed inset-0 bg-white/80 z-50' : 'h-64'
    ]"
  >
    <NSpin :size="size" />
    <p v-if="text" class="mt-3 text-gray-600">{{ text }}</p>
  </div>
</template>
