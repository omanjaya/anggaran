<script setup lang="ts">
withDefaults(defineProps<{
  title?: string
  padding?: boolean
}>(), {
  padding: true
})
</script>

<template>
  <div class="bg-white rounded-lg shadow">
    <div v-if="title" class="px-6 py-4 border-b border-gray-200">
      <h3 class="text-lg font-semibold text-gray-800">{{ title }}</h3>
    </div>
    <div :class="padding ? 'p-6' : ''">
      <slot />
    </div>
  </div>
</template>
