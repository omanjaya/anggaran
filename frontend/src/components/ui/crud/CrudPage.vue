<script setup lang="ts">
import { NButton, NDataTable } from 'naive-ui'
import type { DataTableColumns } from 'naive-ui'
import { Add } from '@vicons/ionicons5'
import { PageHeader, PageCard } from '../layout'
import { LoadingSpinner, FormModal } from '../feedback'
import { EmptyState } from '../data'

interface Props {
  title: string
  subtitle?: string
  loading: boolean
  data: any[]
  columns: DataTableColumns<any>
  showModal: boolean
  modalTitle: string
  modalLoading?: boolean
  addButtonText?: string
  emptyText?: string
  emptyActionText?: string
  canCreate?: boolean
  rowKey?: string
  scrollX?: number
}

withDefaults(defineProps<Props>(), {
  addButtonText: 'Tambah Data',
  emptyText: 'Belum ada data',
  emptyActionText: 'Tambah Data',
  canCreate: true,
  rowKey: 'id',
  modalLoading: false,
})

const emit = defineEmits<{
  (e: 'create'): void
  (e: 'update:showModal', value: boolean): void
  (e: 'submit'): void
}>()
</script>

<template>
  <div>
    <PageHeader :title="title" :subtitle="subtitle">
      <template #actions>
        <slot name="header-actions">
          <NButton v-if="canCreate" type="primary" @click="emit('create')">
            <template #icon>
              <Add />
            </template>
            {{ addButtonText }}
          </NButton>
        </slot>
      </template>
    </PageHeader>

    <!-- Filter slot -->
    <slot name="filters" />

    <PageCard :padding="false">
      <LoadingSpinner v-if="loading" />

      <EmptyState
        v-else-if="data.length === 0"
        :title="emptyText"
        :action-label="canCreate ? emptyActionText : undefined"
        @action="emit('create')"
      />

      <NDataTable
        v-else
        :columns="columns"
        :data="data"
        :row-key="(row: any) => row[rowKey]"
        :scroll-x="scrollX"
        :bordered="false"
        striped
      />
    </PageCard>

    <!-- Modal -->
    <FormModal
      :show="showModal"
      :title="modalTitle"
      :loading="modalLoading"
      @update:show="emit('update:showModal', $event)"
      @submit="emit('submit')"
      @cancel="emit('update:showModal', false)"
    >
      <slot name="form" />
    </FormModal>
  </div>
</template>
