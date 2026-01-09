<script setup lang="ts">
import { h, ref, onMounted } from 'vue'
import { NDataTable, NSpace, NButton, NTag, NModal, NPagination, NInput } from 'naive-ui'
import type { DataTableColumns } from 'naive-ui'
import { PageHeader, PageCard, StatCard, LoadingSpinner } from '@/components/ui'
import { FormSelect, FormDatePicker } from '@/components/ui'
import { useFormat } from '@/composables'
import api from '@/services/api'

interface AuditLog {
  id: number
  user_id: number | null
  action: string
  auditable_type: string
  auditable_id: number | null
  old_values: Record<string, any> | null
  new_values: Record<string, any> | null
  ip_address: string | null
  user_agent: string | null
  url: string | null
  method: string | null
  created_at: string
  user?: { id: number; name: string; email: string }
}

interface Stats {
  total: number
  by_action: Record<string, number>
}

const { formatDate } = useFormat()

const loading = ref(false)
const auditLogs = ref<AuditLog[]>([])
const stats = ref<Stats | null>(null)
const actions = ref<Array<{ value: string; label: string }>>([])
const selectedLog = ref<AuditLog | null>(null)
const showDetailModal = ref(false)

// Filters
const filters = ref({
  action: null as string | null,
  from: null as number | null,
  to: null as number | null,
  search: '',
})

const pagination = ref({
  page: 1,
  pageSize: 20,
  itemCount: 0,
  pageCount: 1,
})

// Action colors
const actionTypes: Record<string, 'success' | 'info' | 'error' | 'warning' | 'default'> = {
  CREATE: 'success',
  UPDATE: 'info',
  DELETE: 'error',
  APPROVE: 'success',
  REJECT: 'warning',
  VERIFY: 'info',
  SUBMIT: 'info',
  LOGIN: 'default',
  LOGOUT: 'default',
  IMPORT: 'warning',
  EXPORT: 'info',
}

function getModelName(type: string) {
  const parts = type.split('\\')
  return parts[parts.length - 1]
}

async function fetchAuditLogs() {
  loading.value = true
  try {
    const params: Record<string, any> = {
      page: pagination.value.page,
      per_page: pagination.value.pageSize,
    }
    if (filters.value.action) params.action = filters.value.action
    if (filters.value.from) params.from = new Date(filters.value.from).toISOString().split('T')[0]
    if (filters.value.to) params.to = new Date(filters.value.to).toISOString().split('T')[0]
    if (filters.value.search) params.search = filters.value.search

    const response = await api.get('/audit-logs', { params })
    const data = response.data.data
    auditLogs.value = Array.isArray(data) ? data : (data?.data || [])
    const meta = response.data.meta || (data?.current_page ? data : null)
    if (meta) {
      pagination.value.itemCount = meta.total || 0
      pagination.value.pageCount = meta.last_page || 1
    }
  } catch (error) {
    console.error('Failed to fetch audit logs:', error)
  } finally {
    loading.value = false
  }
}

async function fetchStats() {
  try {
    const response = await api.get('/audit-logs/stats')
    stats.value = response.data.data
  } catch (error) {
    console.error('Failed to fetch stats:', error)
  }
}

async function fetchActions() {
  try {
    const response = await api.get('/audit-logs/actions')
    actions.value = response.data.data
  } catch (error) {
    console.error('Failed to fetch actions:', error)
  }
}

function showDetail(log: AuditLog) {
  selectedLog.value = log
  showDetailModal.value = true
}

function resetFilters() {
  filters.value = { action: null, from: null, to: null, search: '' }
  pagination.value.page = 1
  fetchAuditLogs()
}

function getChanges(old: Record<string, any> | null, newVal: Record<string, any> | null) {
  const changes: Array<{ field: string; old: any; new: any }> = []
  const allKeys = new Set([...Object.keys(old || {}), ...Object.keys(newVal || {})])
  for (const key of allKeys) {
    const oldValue = old?.[key]
    const newValue = newVal?.[key]
    if (JSON.stringify(oldValue) !== JSON.stringify(newValue)) {
      changes.push({ field: key, old: oldValue, new: newValue })
    }
  }
  return changes
}

function handlePageChange(page: number) {
  pagination.value.page = page
  fetchAuditLogs()
}

const columns: DataTableColumns<AuditLog> = [
  {
    title: 'Waktu',
    key: 'created_at',
    width: 160,
    render: (row) => formatDate(row.created_at, { format: 'datetime' }),
  },
  {
    title: 'User',
    key: 'user',
    width: 180,
    render: (row) =>
      row.user
        ? h('div', [
            h('div', { class: 'font-medium' }, row.user.name),
            h('div', { class: 'text-xs text-gray-500' }, row.user.email),
          ])
        : h('span', { class: 'text-gray-400' }, 'System'),
  },
  {
    title: 'Action',
    key: 'action',
    width: 100,
    align: 'center',
    render: (row) =>
      h(NTag, { type: actionTypes[row.action] || 'default', size: 'small' }, () => row.action),
  },
  {
    title: 'Model',
    key: 'auditable_type',
    width: 150,
    render: (row) =>
      h('div', [
        h('div', { class: 'font-medium' }, getModelName(row.auditable_type)),
        h('div', { class: 'text-xs text-gray-500' }, `ID: ${row.auditable_id || '-'}`),
      ]),
  },
  {
    title: 'IP Address',
    key: 'ip_address',
    width: 130,
    render: (row) => h('code', { class: 'text-xs' }, row.ip_address || '-'),
  },
  {
    title: 'Detail',
    key: 'actions',
    width: 80,
    align: 'center',
    render: (row) =>
      h(
        NButton,
        { size: 'small', quaternary: true, onClick: () => showDetail(row) },
        { default: () => 'Lihat' }
      ),
  },
]

onMounted(async () => {
  await Promise.all([fetchAuditLogs(), fetchStats(), fetchActions()])
})
</script>

<template>
  <div>
    <PageHeader title="Audit Log" subtitle="Rekam jejak semua perubahan data dalam sistem" />

    <!-- Stats -->
    <div v-if="stats" class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
      <StatCard label="Total Logs (30 Hari)" :value="stats.total.toLocaleString()" />
      <StatCard label="Create" :value="stats.by_action.CREATE || 0" variant="success" />
      <StatCard label="Update" :value="stats.by_action.UPDATE || 0" variant="primary" />
      <StatCard label="Delete" :value="stats.by_action.DELETE || 0" variant="error" />
    </div>

    <!-- Filters -->
    <PageCard class="mb-6">
      <NSpace :size="16" wrap align="end">
        <FormSelect
          v-model="filters.action"
          label="Action"
          placeholder="Semua Action"
          :options="actions.map(a => ({ label: a.label, value: a.value }))"
          clearable
          style="min-width: 150px"
          @update:model-value="fetchAuditLogs"
        />
        <FormDatePicker
          v-model="filters.from"
          label="Dari Tanggal"
          placeholder="Pilih tanggal"
          @update:model-value="fetchAuditLogs"
        />
        <FormDatePicker
          v-model="filters.to"
          label="Sampai Tanggal"
          placeholder="Pilih tanggal"
          @update:model-value="fetchAuditLogs"
        />
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
          <NInput
            v-model:value="filters.search"
            placeholder="Cari..."
            clearable
            @update:value="fetchAuditLogs"
          />
        </div>
        <NButton @click="resetFilters">Reset Filter</NButton>
      </NSpace>
    </PageCard>

    <!-- Table -->
    <PageCard :padding="false">
      <LoadingSpinner v-if="loading" />
      <template v-else>
        <NDataTable
          :columns="columns"
          :data="auditLogs"
          :bordered="false"
          striped
        />
        <div class="p-4 border-t flex items-center justify-between">
          <div class="text-sm text-gray-600">
            Menampilkan {{ auditLogs.length }} dari {{ pagination.itemCount }} data
          </div>
          <NPagination
            v-model:page="pagination.page"
            :page-count="pagination.pageCount"
            :page-size="pagination.pageSize"
            @update:page="handlePageChange"
          />
        </div>
      </template>
    </PageCard>

    <!-- Detail Modal -->
    <NModal v-model:show="showDetailModal" preset="card" title="Detail Audit Log" style="width: 700px">
      <template v-if="selectedLog">
        <div class="grid grid-cols-2 gap-4 mb-6">
          <div>
            <div class="text-sm text-gray-600">Waktu</div>
            <div class="font-medium">{{ formatDate(selectedLog.created_at, { format: 'datetime' }) }}</div>
          </div>
          <div>
            <div class="text-sm text-gray-600">User</div>
            <div class="font-medium">{{ selectedLog.user?.name || 'System' }}</div>
          </div>
          <div>
            <div class="text-sm text-gray-600">Action</div>
            <NTag :type="actionTypes[selectedLog.action]" size="small">{{ selectedLog.action }}</NTag>
          </div>
          <div>
            <div class="text-sm text-gray-600">Model</div>
            <div class="font-medium">{{ getModelName(selectedLog.auditable_type) }} (ID: {{ selectedLog.auditable_id }})</div>
          </div>
          <div>
            <div class="text-sm text-gray-600">IP Address</div>
            <code>{{ selectedLog.ip_address || '-' }}</code>
          </div>
          <div>
            <div class="text-sm text-gray-600">URL</div>
            <div class="text-xs truncate">{{ selectedLog.url || '-' }}</div>
          </div>
        </div>

        <div v-if="selectedLog.old_values || selectedLog.new_values" class="mb-4">
          <h4 class="font-medium text-gray-700 mb-3">Perubahan Data</h4>
          <div class="bg-gray-50 rounded-lg p-4 overflow-x-auto">
            <table class="w-full text-sm">
              <thead>
                <tr class="bg-gray-100">
                  <th class="text-left py-2 px-3">Field</th>
                  <th class="text-left py-2 px-3">Nilai Lama</th>
                  <th class="text-left py-2 px-3">Nilai Baru</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="change in getChanges(selectedLog.old_values, selectedLog.new_values)" :key="change.field" class="border-t">
                  <td class="py-2 px-3 font-medium">{{ change.field }}</td>
                  <td class="py-2 px-3 text-red-600 font-mono text-xs">{{ JSON.stringify(change.old) || '-' }}</td>
                  <td class="py-2 px-3 text-green-600 font-mono text-xs">{{ JSON.stringify(change.new) || '-' }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <div>
          <h4 class="font-medium text-gray-700 mb-2">User Agent</h4>
          <div class="bg-gray-50 rounded-lg p-3 text-xs font-mono text-gray-600 break-all">
            {{ selectedLog.user_agent || '-' }}
          </div>
        </div>
      </template>
    </NModal>
  </div>
</template>
