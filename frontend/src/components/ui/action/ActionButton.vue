<script setup lang="ts">
import { NButton } from 'naive-ui'

interface Props {
  type?: 'default' | 'primary' | 'success' | 'warning' | 'error' | 'info'
  size?: 'tiny' | 'small' | 'medium' | 'large'
  loading?: boolean
  disabled?: boolean
  ghost?: boolean
  secondary?: boolean
  tertiary?: boolean
  quaternary?: boolean
  dashed?: boolean
  circle?: boolean
  round?: boolean
  block?: boolean
  text?: boolean
  tag?: keyof HTMLElementTagNameMap
}

withDefaults(defineProps<Props>(), {
  type: 'default',
  size: 'medium',
  loading: false,
  disabled: false
})

const emit = defineEmits<{
  (e: 'click', event: MouseEvent): void
}>()
</script>

<template>
  <NButton
    :type="type"
    :size="size"
    :loading="loading"
    :disabled="disabled"
    :ghost="ghost"
    :secondary="secondary"
    :tertiary="tertiary"
    :quaternary="quaternary"
    :dashed="dashed"
    :circle="circle"
    :round="round"
    :block="block"
    :text="text"
    :tag="tag"
    @click="emit('click', $event)"
  >
    <slot />
  </NButton>
</template>
