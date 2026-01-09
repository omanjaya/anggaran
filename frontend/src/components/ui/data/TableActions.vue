<script setup lang="ts">
import { NSpace, NButton, NIcon, NTooltip, NPopconfirm } from 'naive-ui'
import { CreateOutline, TrashOutline, EyeOutline } from '@vicons/ionicons5'

interface Props {
  showView?: boolean
  showEdit?: boolean
  showDelete?: boolean
  canEdit?: boolean
  canDelete?: boolean
  canView?: boolean
  deleteConfirmText?: string
  size?: 'tiny' | 'small' | 'medium'
}

withDefaults(defineProps<Props>(), {
  showView: false,
  showEdit: true,
  showDelete: true,
  canEdit: true,
  canDelete: true,
  canView: true,
  deleteConfirmText: 'Yakin hapus data ini?',
  size: 'small',
})

const emit = defineEmits<{
  (e: 'view'): void
  (e: 'edit'): void
  (e: 'delete'): void
}>()
</script>

<template>
  <NSpace :size="4" justify="center">
    <NTooltip v-if="showView && canView" trigger="hover">
      <template #trigger>
        <NButton quaternary circle :size="size" @click="emit('view')">
          <template #icon>
            <NIcon><EyeOutline /></NIcon>
          </template>
        </NButton>
      </template>
      Lihat
    </NTooltip>

    <NTooltip v-if="showEdit && canEdit" trigger="hover">
      <template #trigger>
        <NButton quaternary circle :size="size" type="info" @click="emit('edit')">
          <template #icon>
            <NIcon><CreateOutline /></NIcon>
          </template>
        </NButton>
      </template>
      Edit
    </NTooltip>

    <NPopconfirm
      v-if="showDelete && canDelete"
      :positive-text="'Hapus'"
      :negative-text="'Batal'"
      @positive-click="emit('delete')"
    >
      <template #trigger>
        <NTooltip trigger="hover">
          <template #trigger>
            <NButton quaternary circle :size="size" type="error">
              <template #icon>
                <NIcon><TrashOutline /></NIcon>
              </template>
            </NButton>
          </template>
          Hapus
        </NTooltip>
      </template>
      {{ deleteConfirmText }}
    </NPopconfirm>

    <slot />
  </NSpace>
</template>
