<script setup lang="ts">
defineProps<{
  title: string
  subtitle?: string
}>()
</script>

<template>
  <div class="flex justify-between items-start mb-6">
    <div>
      <h1 class="text-2xl font-bold text-gray-800">{{ title }}</h1>
      <p v-if="subtitle" class="text-gray-600 mt-1">{{ subtitle }}</p>
    </div>
    <div class="flex items-center gap-3">
      <slot name="actions" />
    </div>
  </div>
</template>
