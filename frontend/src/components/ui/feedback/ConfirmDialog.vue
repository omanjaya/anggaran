<script setup lang="ts">
import { NModal } from 'naive-ui'

interface Props {
  show: boolean
  title?: string
  message: string
  confirmText?: string
  cancelText?: string
  confirmType?: 'primary' | 'error' | 'warning' | 'success'
  loading?: boolean
}

withDefaults(defineProps<Props>(), {
  title: 'Konfirmasi',
  confirmText: 'Ya',
  cancelText: 'Batal',
  confirmType: 'primary',
  loading: false
})

const emit = defineEmits<{
  (e: 'update:show', value: boolean): void
  (e: 'confirm'): void
  (e: 'cancel'): void
}>()

function handleConfirm() {
  emit('confirm')
}

function handleCancel() {
  emit('cancel')
  emit('update:show', false)
}
</script>

<template>
  <NModal
    :show="show"
    preset="dialog"
    :title="title"
    :positive-text="confirmText"
    :negative-text="cancelText"
    :loading="loading"
    @positive-click="handleConfirm"
    @negative-click="handleCancel"
    @close="handleCancel"
  >
    <p class="text-gray-600">{{ message }}</p>
  </NModal>
</template>
