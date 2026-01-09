<script setup lang="ts">
import { h, ref, onMounted, computed } from 'vue'
import { NButton, NModal, NDataTable, NSpace, NIcon, NTag, NInput } from 'naive-ui'
import type { DataTableColumns } from 'naive-ui'
import { EyeOutline, CheckmarkOutline, CloseOutline } from '@vicons/ionicons5'
import { PageHeader, PageCard, LoadingSpinner, FormSelect } from '@/components/ui'
import { useFormat, useMessage } from '@/composables'
import { useAuthStore } from '@/stores/auth'
import api from '@/services/api'
import type { MonthlyRealization } from '@/types/models'

const authStore = useAuthStore()
const { formatCurrency } = useFormat()
const message = useMessage()

const loading = ref(true)
const pendingItems = ref<MonthlyRealization[]>([])
const showDetailModal = ref(false)
const showRejectModal = ref(false)
const showBatchRejectModal = ref(false)
const selectedItem = ref<MonthlyRealization | null>(null)
const rejectionReason = ref('')
const batchRejectionReason = ref('')
const processing = ref(false)
const selectedIds = ref<number[]>([])

// Filters
const selectedYear = ref(new Date().getFullYear())
const selectedMonth = ref<number | undefined>(undefined)

const canVerify = computed(() => authStore.hasPermission('approval.verify'))
const canApprove = computed(() => authStore.hasPermission('approval.approve'))

const months = [
  { value: undefined as number | undefined, label: 'Semua Bulan' },
  { value: 1, label: 'Januari' },
  { value: 2, label: 'Februari' },
  { value: 3, label: 'Maret' },
  { value: 4, label: 'April' },
  { value: 5, label: 'Mei' },
  { value: 6, label: 'Juni' },
  { value: 7, label: 'Juli' },
  { value: 8, label: 'Agustus' },
  { value: 9, label: 'September' },
  { value: 10, label: 'Oktober' },
  { value: 11, label: 'November' },
  { value: 12, label: 'Desember' },
]

const years = computed(() => {
  const currentYear = new Date().getFullYear()
  return Array.from({ length: 5 }, (_, i) => ({ label: String(currentYear - 2 + i), value: currentYear - 2 + i }))
})

const pendingStatus = computed(() => {
  if (canVerify.value) return 'SUBMITTED'
  if (canApprove.value) return 'VERIFIED'
  return ''
})

const actionLabel = computed(() => {
  if (canVerify.value) return 'Verifikasi'
  if (canApprove.value) return 'Setujui'
  return ''
})

const selectedCount = computed(() => selectedIds.value.length)

function handleCheckedRowKeysChange(keys: (string | number)[]) {
  selectedIds.value = keys as number[]
}

const statusTypes: Record<string, 'default' | 'info' | 'success' | 'warning' | 'error'> = {
  DRAFT: 'default',
  SUBMITTED: 'warning',
  VERIFIED: 'info',
  APPROVED: 'success',
  REJECTED: 'error',
}

const statusLabels: Record<string, string> = {
  DRAFT: 'Draft',
  SUBMITTED: 'Diajukan',
  VERIFIED: 'Diverifikasi',
  APPROVED: 'Disetujui',
  REJECTED: 'Ditolak',
}

function getMonthName(month: number) {
  return months.find((m) => m.value === month)?.label || '-'
}

async function fetchPendingItems() {
  loading.value = true
  selectedIds.value = []
  try {
    const params: any = {
      year: selectedYear.value,
      status: pendingStatus.value,
    }
    if (selectedMonth.value) params.month = selectedMonth.value
    const response = await api.get('/realizations', { params })
    const data = response.data.data
    pendingItems.value = Array.isArray(data) ? data : (data?.data || [])
  } catch (err) {
    console.error('Failed to fetch pending items:', err)
  } finally {
    loading.value = false
  }
}

function viewDetail(item: MonthlyRealization) {
  selectedItem.value = item
  showDetailModal.value = true
}

function openRejectModal(item: MonthlyRealization) {
  selectedItem.value = item
  rejectionReason.value = ''
  showRejectModal.value = true
}

function openBatchRejectModal() {
  batchRejectionReason.value = ''
  showBatchRejectModal.value = true
}

async function approveItem(item: MonthlyRealization) {
  const confirmed = await message.confirm(`${actionLabel.value} realisasi ini?`)
  if (!confirmed) return

  processing.value = true
  try {
    if (canVerify.value) {
      await api.post(`/realizations/${item.id}/verify`, { action: 'verify' })
    } else if (canApprove.value) {
      await api.post(`/realizations/${item.id}/approve`, { action: 'approve' })
    }
    await fetchPendingItems()
    showDetailModal.value = false
    message.success('Realisasi berhasil diproses')
  } catch (err: any) {
    message.error(err.response?.data?.message || 'Gagal memproses')
  } finally {
    processing.value = false
  }
}

async function rejectItem() {
  if (!selectedItem.value) return
  if (!rejectionReason.value.trim()) {
    message.warning('Alasan penolakan harus diisi')
    return
  }
  processing.value = true
  try {
    if (canVerify.value) {
      await api.post(`/realizations/${selectedItem.value.id}/verify`, {
        action: 'reject',
        rejection_reason: rejectionReason.value,
      })
    } else if (canApprove.value) {
      await api.post(`/realizations/${selectedItem.value.id}/approve`, {
        action: 'reject',
        rejection_reason: rejectionReason.value,
      })
    }
    await fetchPendingItems()
    showRejectModal.value = false
    showDetailModal.value = false
    message.success('Realisasi berhasil ditolak')
  } catch (err: any) {
    message.error(err.response?.data?.message || 'Gagal menolak')
  } finally {
    processing.value = false
  }
}

async function batchApprove() {
  if (selectedIds.value.length === 0) return
  const confirmed = await message.confirm(`${actionLabel.value} ${selectedIds.value.length} realisasi terpilih?`)
  if (!confirmed) return

  processing.value = true
  try {
    const endpoint = canVerify.value ? '/realizations/batch-verify' : '/realizations/batch-approve'
    const action = canVerify.value ? 'verify' : 'approve'

    await api.post(endpoint, { ids: selectedIds.value, action })
    message.success(`${selectedIds.value.length} realisasi berhasil di${canVerify.value ? 'verifikasi' : 'setujui'}`)
    await fetchPendingItems()
  } catch (err: any) {
    message.error(err.response?.data?.message || 'Gagal memproses')
  } finally {
    processing.value = false
  }
}

async function batchReject() {
  if (selectedIds.value.length === 0) return
  if (!batchRejectionReason.value.trim()) {
    message.warning('Alasan penolakan harus diisi')
    return
  }

  processing.value = true
  try {
    const endpoint = canVerify.value ? '/realizations/batch-verify' : '/realizations/batch-approve'

    await api.post(endpoint, {
      ids: selectedIds.value,
      action: 'reject',
      rejection_reason: batchRejectionReason.value,
    })

    message.success(`${selectedIds.value.length} realisasi berhasil ditolak`)
    showBatchRejectModal.value = false
    await fetchPendingItems()
  } catch (err: any) {
    message.error(err.response?.data?.message || 'Gagal menolak')
  } finally {
    processing.value = false
  }
}

const columns: DataTableColumns<MonthlyRealization> = [
  {
    type: 'selection',
    disabled: () => false,
  },
  {
    title: 'Item Anggaran',
    key: 'monthly_plan.budget_item.name',
    render: (row) => row.monthly_plan?.budget_item?.name || '-',
  },
  {
    title: 'Periode',
    key: 'period',
    width: 120,
    align: 'center',
    render: (row) => `${getMonthName(row.monthly_plan?.month || 0)} ${row.monthly_plan?.year}`,
  },
  {
    title: 'Rencana',
    key: 'planned_amount',
    width: 150,
    align: 'right',
    render: (row) => formatCurrency(row.monthly_plan?.planned_amount || 0),
  },
  {
    title: 'Realisasi',
    key: 'realized_amount',
    width: 150,
    align: 'right',
    render: (row) => formatCurrency(row.realized_amount),
  },
  {
    title: 'Deviasi',
    key: 'deviation',
    width: 150,
    align: 'right',
    render: (row) =>
      h(
        'span',
        { class: row.deviation_amount < 0 ? 'text-red-600' : 'text-green-600' },
        `${formatCurrency(row.deviation_amount)} (${row.deviation_percentage.toFixed(1)}%)`
      ),
  },
  {
    title: 'Status',
    key: 'status',
    width: 120,
    align: 'center',
    render: (row) => h(NTag, { type: statusTypes[row.status] || 'default', size: 'small' }, () => statusLabels[row.status] || row.status),
  },
  {
    title: 'Aksi',
    key: 'actions',
    width: 120,
    align: 'center',
    render: (row) =>
      h(NSpace, { justify: 'center' }, () => [
        h(NButton, { quaternary: true, size: 'small', onClick: () => viewDetail(row) }, () => h(NIcon, null, () => h(EyeOutline))),
        h(NButton, { quaternary: true, type: 'success', size: 'small', onClick: () => approveItem(row) }, () => h(NIcon, null, () => h(CheckmarkOutline))),
        h(NButton, { quaternary: true, type: 'error', size: 'small', onClick: () => openRejectModal(row) }, () => h(NIcon, null, () => h(CloseOutline))),
      ]),
  },
]

onMounted(() => {
  if (pendingStatus.value) {
    fetchPendingItems()
  }
})
</script>

<template>
  <div>
    <PageHeader
      title="Persetujuan Realisasi"
      :subtitle="canVerify ? 'Verifikasi realisasi yang diajukan' : 'Setujui realisasi yang telah diverifikasi'"
    />

    <!-- Filter -->
    <PageCard class="mb-6">
      <div class="flex flex-wrap items-end gap-4">
        <FormSelect v-model="selectedYear" label="Tahun" :options="years" style="width: 120px" @update:model-value="fetchPendingItems" />
        <FormSelect v-model="selectedMonth" label="Bulan" :options="months" style="width: 150px" @update:model-value="fetchPendingItems" />
        <div class="ml-auto">
          <NTag type="warning">{{ pendingItems.length }} menunggu {{ actionLabel.toLowerCase() }}</NTag>
        </div>
      </div>
    </PageCard>

    <!-- Batch Actions -->
    <div v-if="selectedCount > 0" class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-4">
      <div class="flex items-center justify-between">
        <span class="text-blue-800 font-medium">{{ selectedCount }} item terpilih</span>
        <NSpace>
          <NButton type="success" :loading="processing" @click="batchApprove">
            <template #icon><NIcon><CheckmarkOutline /></NIcon></template>
            {{ actionLabel }} Semua ({{ selectedCount }})
          </NButton>
          <NButton type="error" :loading="processing" @click="openBatchRejectModal">
            <template #icon><NIcon><CloseOutline /></NIcon></template>
            Tolak Semua ({{ selectedCount }})
          </NButton>
        </NSpace>
      </div>
    </div>

    <PageCard :padding="false">
      <LoadingSpinner v-if="loading" />

      <div v-else-if="!pendingStatus" class="text-center py-12 text-gray-500">
        Anda tidak memiliki akses untuk menyetujui realisasi
      </div>

      <div v-else-if="pendingItems.length === 0" class="text-center py-12 text-gray-500">
        Tidak ada realisasi yang menunggu {{ actionLabel.toLowerCase() }}
      </div>

      <NDataTable
        v-else
        :columns="columns"
        :data="pendingItems"
        :row-key="(row) => row.id"
        :checked-row-keys="selectedIds"
        @update:checked-row-keys="handleCheckedRowKeysChange"
        :bordered="false"
        striped
      />
    </PageCard>

    <!-- Detail Modal -->
    <NModal v-model:show="showDetailModal" preset="card" title="Detail Realisasi" style="width: 700px">
      <div v-if="selectedItem" class="space-y-4">
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-500">Item Anggaran</label>
            <p class="text-gray-900">{{ selectedItem.monthly_plan?.budget_item?.name }}</p>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-500">Periode</label>
            <p class="text-gray-900">{{ getMonthName(selectedItem.monthly_plan?.month || 0) }} {{ selectedItem.monthly_plan?.year }}</p>
          </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-500">Volume Rencana</label>
            <p class="text-gray-900">{{ selectedItem.monthly_plan?.planned_volume.toLocaleString('id-ID') }}</p>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-500">Volume Realisasi</label>
            <p class="text-gray-900">{{ selectedItem.realized_volume.toLocaleString('id-ID') }}</p>
          </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-500">Jumlah Rencana</label>
            <p class="text-gray-900">{{ formatCurrency(selectedItem.monthly_plan?.planned_amount || 0) }}</p>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-500">Jumlah Realisasi</label>
            <p class="text-gray-900">{{ formatCurrency(selectedItem.realized_amount) }}</p>
          </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-500">Deviasi</label>
            <p :class="selectedItem.deviation_amount < 0 ? 'text-red-600' : 'text-green-600'" class="font-medium">
              {{ formatCurrency(selectedItem.deviation_amount) }} ({{ selectedItem.deviation_percentage.toFixed(1) }}%)
            </p>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-500">Status</label>
            <NTag :type="statusTypes[selectedItem.status]" size="small">{{ statusLabels[selectedItem.status] }}</NTag>
          </div>
        </div>

        <div v-if="selectedItem.notes">
          <label class="block text-sm font-medium text-gray-500">Catatan</label>
          <p class="text-gray-900">{{ selectedItem.notes }}</p>
        </div>

        <div v-if="selectedItem.rejection_reason" class="p-3 bg-red-50 border border-red-200 rounded-md">
          <label class="block text-sm font-medium text-red-700">Alasan Penolakan</label>
          <p class="text-red-800">{{ selectedItem.rejection_reason }}</p>
        </div>

        <div class="flex justify-end gap-3 pt-4 border-t">
          <NButton @click="showDetailModal = false">Tutup</NButton>
          <NButton type="error" @click="openRejectModal(selectedItem)">
            <template #icon><NIcon><CloseOutline /></NIcon></template>
            Tolak
          </NButton>
          <NButton type="success" :loading="processing" @click="approveItem(selectedItem)">
            <template #icon><NIcon><CheckmarkOutline /></NIcon></template>
            {{ processing ? 'Memproses...' : actionLabel }}
          </NButton>
        </div>
      </div>
    </NModal>

    <!-- Reject Modal -->
    <NModal v-model:show="showRejectModal" preset="card" title="Tolak Realisasi" style="width: 500px">
      <div v-if="selectedItem">
        <div class="mb-4">
          <p class="text-sm text-gray-600 mb-2">Anda akan menolak realisasi untuk:</p>
          <p class="font-medium text-gray-900">{{ selectedItem.monthly_plan?.budget_item?.name }}</p>
          <p class="text-sm text-gray-600">
            {{ getMonthName(selectedItem.monthly_plan?.month || 0) }} {{ selectedItem.monthly_plan?.year }}
          </p>
        </div>

        <div class="mb-4">
          <label class="block text-sm font-medium text-gray-700 mb-1">Alasan Penolakan *</label>
          <NInput v-model:value="rejectionReason" type="textarea" :rows="4" placeholder="Masukkan alasan penolakan..." />
        </div>

        <div class="flex justify-end gap-3 pt-4 border-t">
          <NButton @click="showRejectModal = false">Batal</NButton>
          <NButton type="error" :loading="processing" :disabled="!rejectionReason.trim()" @click="rejectItem">
            Tolak Realisasi
          </NButton>
        </div>
      </div>
    </NModal>

    <!-- Batch Reject Modal -->
    <NModal v-model:show="showBatchRejectModal" preset="card" :title="`Tolak ${selectedCount} Realisasi`" style="width: 500px">
      <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-md">
        <p class="text-sm text-red-700">
          Anda akan menolak <strong>{{ selectedCount }}</strong> realisasi terpilih. Semua realisasi akan ditolak dengan alasan yang sama.
        </p>
      </div>

      <div class="mb-4">
        <label class="block text-sm font-medium text-gray-700 mb-1">Alasan Penolakan *</label>
        <NInput v-model:value="batchRejectionReason" type="textarea" :rows="4" placeholder="Masukkan alasan penolakan..." />
      </div>

      <div class="flex justify-end gap-3 pt-4 border-t">
        <NButton @click="showBatchRejectModal = false">Batal</NButton>
        <NButton type="error" :loading="processing" :disabled="!batchRejectionReason.trim()" @click="batchReject">
          Tolak {{ selectedCount }} Realisasi
        </NButton>
      </div>
    </NModal>
  </div>
</template>
