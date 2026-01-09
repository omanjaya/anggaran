<script setup lang="ts">
import { NButton, NIcon, NTooltip } from 'naive-ui'

interface Props {
  icon: any
  tooltip?: string
  type?: 'default' | 'primary' | 'success' | 'warning' | 'error' | 'info'
  size?: 'tiny' | 'small' | 'medium' | 'large'
  loading?: boolean
  disabled?: boolean
  quaternary?: boolean
  tertiary?: boolean
  secondary?: boolean
  ghost?: boolean
}

withDefaults(defineProps<Props>(), {
  type: 'default',
  size: 'medium',
  quaternary: true
})

const emit = defineEmits<{
  (e: 'click', event: MouseEvent): void
}>()
</script>

<template>
  <NTooltip v-if="tooltip" trigger="hover">
    <template #trigger>
      <NButton
        circle
        :type="type"
        :size="size"
        :loading="loading"
        :disabled="disabled"
        :quaternary="quaternary"
        :tertiary="tertiary"
        :secondary="secondary"
        :ghost="ghost"
        @click="emit('click', $event)"
      >
        <template #icon>
          <NIcon :component="icon" />
        </template>
      </NButton>
    </template>
    {{ tooltip }}
  </NTooltip>
  <NButton
    v-else
    circle
    :type="type"
    :size="size"
    :loading="loading"
    :disabled="disabled"
    :quaternary="quaternary"
    :tertiary="tertiary"
    :secondary="secondary"
    :ghost="ghost"
    @click="emit('click', $event)"
  >
    <template #icon>
      <NIcon :component="icon" />
    </template>
  </NButton>
</template>
