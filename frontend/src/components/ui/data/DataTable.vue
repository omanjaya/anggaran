<script setup lang="ts">
import { NDataTable } from 'naive-ui'
import type { DataTableColumns, PaginationProps, DataTableCreateRowKey } from 'naive-ui'
import { computed } from 'vue'

interface Props {
  columns: DataTableColumns<any>
  data: any[]
  loading?: boolean
  rowKey?: DataTableCreateRowKey<any>
  bordered?: boolean
  striped?: boolean
  singleLine?: boolean
  pagination?: boolean | PaginationProps
  pageSize?: number
  scrollX?: number | string
  maxHeight?: number | string
}

const props = withDefaults(defineProps<Props>(), {
  loading: false,
  rowKey: (row: any) => row.id,
  bordered: false,
  striped: true,
  singleLine: true,
  pagination: true,
  pageSize: 10
})

const emit = defineEmits<{
  (e: 'update:page', page: number): void
  (e: 'update:pageSize', pageSize: number): void
}>()

const paginationConfig = computed(() => {
  if (!props.pagination) return false
  if (typeof props.pagination === 'object') return props.pagination
  return {
    pageSize: props.pageSize,
    showSizePicker: true,
    pageSizes: [10, 20, 50, 100],
    showQuickJumper: true,
  }
})
</script>

<template>
  <NDataTable
    :columns="columns"
    :data="data"
    :loading="loading"
    :row-key="rowKey"
    :bordered="bordered"
    :striped="striped"
    :single-line="singleLine"
    :pagination="paginationConfig"
    :scroll-x="scrollX"
    :max-height="maxHeight"
  />
</template>
