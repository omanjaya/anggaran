<script setup lang="ts">
import { NModal, NButton, NSpace, NSpin } from 'naive-ui'

interface Props {
  show: boolean
  title: string
  width?: string | number
  loading?: boolean
  submitText?: string
  cancelText?: string
  submitDisabled?: boolean
  showFooter?: boolean
}

withDefaults(defineProps<Props>(), {
  width: 520,
  loading: false,
  submitText: 'Simpan',
  cancelText: 'Batal',
  submitDisabled: false,
  showFooter: true
})

const emit = defineEmits<{
  (e: 'update:show', value: boolean): void
  (e: 'submit'): void
  (e: 'cancel'): void
}>()

function handleClose() {
  emit('cancel')
  emit('update:show', false)
}

function handleSubmit() {
  emit('submit')
}
</script>

<template>
  <NModal
    :show="show"
    :style="{ width: typeof width === 'number' ? `${width}px` : width }"
    preset="card"
    :title="title"
    :bordered="false"
    :segmented="{ content: true, footer: 'soft' }"
    @close="handleClose"
    @mask-click="handleClose"
  >
    <NSpin :show="loading">
      <slot />
    </NSpin>

    <template v-if="showFooter" #footer>
      <NSpace justify="end">
        <NButton @click="handleClose">
          {{ cancelText }}
        </NButton>
        <NButton
          type="primary"
          :loading="loading"
          :disabled="submitDisabled"
          @click="handleSubmit"
        >
          {{ submitText }}
        </NButton>
      </NSpace>
    </template>
  </NModal>
</template>
