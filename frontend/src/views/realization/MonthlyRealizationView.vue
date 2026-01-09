<script setup lang="ts">
import { h, ref, onMounted, computed } from 'vue'
import { NButton, NDataTable, NModal, NSpace, NIcon, NTag, NUpload, NUploadDragger, NText } from 'naive-ui'
import type { DataTableColumns, UploadFileInfo } from 'naive-ui'
import { Add, CloudUploadOutline, DocumentOutline, DownloadOutline, TrashOutline, SendOutline } from '@vicons/ionicons5'
import { PageHeader, PageCard, LoadingSpinner } from '@/components/ui'
import { FormSelect, FormInputNumber, FormInput } from '@/components/ui'
import { useFormat, useMessage } from '@/composables'
import { useAuthStore } from '@/stores/auth'
import api from '@/services/api'
import type { MonthlyRealization, MonthlyPlan, RealizationDocument } from '@/types/models'

const authStore = useAuthStore()
const { formatCurrency } = useFormat()
const message = useMessage()

const loading = ref(true)
const realizations = ref<MonthlyRealization[]>([])
const monthlyPlans = ref<MonthlyPlan[]>([])
const showModal = ref(false)
const showDocumentsModal = ref(false)
const isEditing = ref(false)
const saving = ref(false)
const uploading = ref(false)
const formError = ref('')
const selectedRealization = ref<MonthlyRealization | null>(null)
const documents = ref<RealizationDocument[]>([])

// Filters
const selectedYear = ref(new Date().getFullYear())
const selectedMonth = ref<number | undefined>(undefined)
const selectedStatus = ref<string | undefined>(undefined)

const form = ref({
  id: 0,
  monthly_plan_id: 0,
  realized_volume: 0,
  realized_amount: 0,
  notes: '',
})

const canCreate = computed(() => authStore.hasPermission('realization.create'))
const canEdit = computed(() => authStore.hasPermission('realization.update'))
const canSubmit = computed(() => authStore.hasPermission('realization.submit'))

const months = [
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

const monthOptions = [{ value: undefined as number | undefined, label: 'Semua' }, ...months]

const statusOptions = [
  { value: undefined as string | undefined, label: 'Semua' },
  { value: 'DRAFT', label: 'Draft' },
  { value: 'SUBMITTED', label: 'Diajukan' },
  { value: 'VERIFIED', label: 'Diverifikasi' },
  { value: 'APPROVED', label: 'Disetujui' },
  { value: 'REJECTED', label: 'Ditolak' },
]

const statusTypes: Record<string, 'default' | 'warning' | 'info' | 'success' | 'error'> = {
  DRAFT: 'default',
  SUBMITTED: 'warning',
  VERIFIED: 'info',
  APPROVED: 'success',
  REJECTED: 'error',
}

const years = computed(() => {
  const currentYear = new Date().getFullYear()
  return Array.from({ length: 5 }, (_, i) => ({
    label: String(currentYear - 2 + i),
    value: currentYear - 2 + i,
  }))
})

const planOptions = computed(() =>
  monthlyPlans.value.map((p) => ({
    label: `${p.budget_item?.name || '-'} - ${getMonthName(p.month)} ${p.year}`,
    value: p.id,
  }))
)

async function fetchRealizations() {
  loading.value = true
  try {
    const params: any = { year: selectedYear.value }
    if (selectedMonth.value) params.month = selectedMonth.value
    if (selectedStatus.value) params.status = selectedStatus.value
    const response = await api.get('/realizations', { params })
    const data = response.data.data
    realizations.value = Array.isArray(data) ? data : (data?.data || [])
  } catch (err) {
    console.error('Failed to fetch realizations:', err)
  } finally {
    loading.value = false
  }
}

async function fetchMonthlyPlans() {
  try {
    const params: any = { year: selectedYear.value }
    if (selectedMonth.value) params.month = selectedMonth.value
    const response = await api.get('/monthly-plans', { params })
    const data = response.data.data
    monthlyPlans.value = Array.isArray(data) ? data : (data?.data || [])
  } catch (err) {
    console.error('Failed to fetch monthly plans:', err)
  }
}

function openCreateModal() {
  isEditing.value = false
  form.value = {
    id: 0,
    monthly_plan_id: 0,
    realized_volume: 0,
    realized_amount: 0,
    notes: '',
  }
  formError.value = ''
  fetchMonthlyPlans()
  showModal.value = true
}

function openEditModal(realization: MonthlyRealization) {
  isEditing.value = true
  form.value = {
    id: realization.id,
    monthly_plan_id: realization.monthly_plan_id,
    realized_volume: realization.realized_volume,
    realized_amount: realization.realized_amount,
    notes: realization.notes || '',
  }
  formError.value = ''
  fetchMonthlyPlans()
  showModal.value = true
}

async function saveRealization() {
  saving.value = true
  formError.value = ''
  try {
    if (isEditing.value) {
      await api.put(`/realizations/${form.value.id}`, form.value)
    } else {
      await api.post('/realizations', form.value)
    }
    showModal.value = false
    message.success('Realisasi berhasil disimpan')
    await fetchRealizations()
  } catch (err: any) {
    if (err.response?.data?.errors) {
      const errors = err.response.data.errors
      formError.value = Object.values(errors).flat().join(', ')
    } else {
      formError.value = err.response?.data?.message || 'Gagal menyimpan data'
    }
  } finally {
    saving.value = false
  }
}

async function submitRealization(realization: MonthlyRealization) {
  const confirmed = await message.confirm('Ajukan realisasi ini untuk verifikasi?')
  if (!confirmed) return
  try {
    await api.post(`/realizations/${realization.id}/submit`)
    message.success('Realisasi berhasil diajukan')
    await fetchRealizations()
  } catch (err: any) {
    message.error(err.response?.data?.message || 'Gagal mengajukan realisasi')
  }
}

function getMonthName(month: number) {
  return months.find((m) => m.value === month)?.label || '-'
}

function updateRealizedAmount() {
  const plan = monthlyPlans.value.find((p) => p.id === form.value.monthly_plan_id)
  if (plan && plan.planned_volume > 0) {
    const unitPrice = plan.planned_amount / plan.planned_volume
    form.value.realized_amount = form.value.realized_volume * unitPrice
  }
}

async function openDocumentsModal(realization: MonthlyRealization) {
  selectedRealization.value = realization
  documents.value = []
  showDocumentsModal.value = true
  await fetchDocuments(realization.id)
}

async function fetchDocuments(realizationId: number) {
  try {
    const response = await api.get(`/realizations/${realizationId}/documents`)
    documents.value = response.data.data
  } catch (err) {
    console.error('Failed to fetch documents:', err)
  }
}

async function handleUpload(options: { file: UploadFileInfo }) {
  if (!selectedRealization.value || !options.file.file) return

  const formData = new FormData()
  formData.append('document', options.file.file)

  uploading.value = true
  try {
    await api.post(`/realizations/${selectedRealization.value.id}/documents`, formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    message.success('Dokumen berhasil diupload')
    await fetchDocuments(selectedRealization.value.id)
  } catch (err: any) {
    message.error(err.response?.data?.message || 'Gagal upload dokumen')
  } finally {
    uploading.value = false
  }
}

async function downloadDocument(doc: RealizationDocument) {
  if (!selectedRealization.value) return
  try {
    const response = await api.get(
      `/realizations/${selectedRealization.value.id}/documents/${doc.id}/download`,
      { responseType: 'blob' }
    )
    const url = window.URL.createObjectURL(new Blob([response.data]))
    const link = document.createElement('a')
    link.href = url
    link.setAttribute('download', doc.original_filename)
    document.body.appendChild(link)
    link.click()
    link.remove()
  } catch (err) {
    message.error('Gagal download dokumen')
  }
}

async function deleteDocument(doc: RealizationDocument) {
  if (!selectedRealization.value) return
  message.confirmDelete({
    content: `dokumen "${doc.original_filename}"`,
    onConfirm: async () => {
      try {
        await api.delete(`/realizations/${selectedRealization.value!.id}/documents/${doc.id}`)
        message.success('Dokumen berhasil dihapus')
        await fetchDocuments(selectedRealization.value!.id)
      } catch (err: any) {
        message.error(err.response?.data?.message || 'Gagal hapus dokumen')
      }
    },
  })
}

function formatFileSize(bytes: number): string {
  if (bytes < 1024) return bytes + ' B'
  if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB'
  return (bytes / (1024 * 1024)).toFixed(1) + ' MB'
}

function canManageDocuments(realization: MonthlyRealization): boolean {
  return realization.status === 'DRAFT' || realization.status === 'REJECTED'
}

const columns: DataTableColumns<MonthlyRealization> = [
  {
    title: 'Item Anggaran',
    key: 'budget_item',
    render: (row) => row.monthly_plan?.budget_item?.name || '-',
  },
  {
    title: 'Periode',
    key: 'period',
    width: 150,
    align: 'center',
    render: (row) => `${getMonthName(row.monthly_plan?.month || 0)} ${row.monthly_plan?.year}`,
  },
  {
    title: 'Rencana',
    key: 'planned',
    width: 140,
    align: 'right',
    render: (row) => formatCurrency(row.monthly_plan?.planned_amount || 0),
  },
  {
    title: 'Realisasi',
    key: 'realized_amount',
    width: 140,
    align: 'right',
    render: (row) => formatCurrency(row.realized_amount),
  },
  {
    title: 'Deviasi',
    key: 'deviation',
    width: 120,
    align: 'right',
    render: (row) => {
      const color = row.deviation_amount < 0 ? 'text-red-600' : 'text-green-600'
      return h('span', { class: color }, [
        formatCurrency(row.deviation_amount),
        h('span', { class: 'text-xs block' }, `(${row.deviation_percentage.toFixed(1)}%)`),
      ])
    },
  },
  {
    title: 'Status',
    key: 'status',
    width: 110,
    align: 'center',
    render: (row) => h(NTag, { type: statusTypes[row.status], size: 'small' }, () => row.status),
  },
  {
    title: 'Aksi',
    key: 'actions',
    width: 140,
    align: 'center',
    render: (row) =>
      h(NSpace, { justify: 'center', size: 4 }, () => [
        h(
          NButton,
          { size: 'small', quaternary: true, onClick: () => openDocumentsModal(row) },
          { icon: () => h(NIcon, null, { default: () => h(DocumentOutline) }) }
        ),
        row.status === 'DRAFT' || row.status === 'REJECTED'
          ? [
              canEdit.value
                ? h(
                    NButton,
                    { size: 'small', quaternary: true, type: 'info', onClick: () => openEditModal(row) },
                    { default: () => 'Edit' }
                  )
                : null,
              row.status === 'DRAFT' && canSubmit.value
                ? h(
                    NButton,
                    { size: 'small', quaternary: true, type: 'success', onClick: () => submitRealization(row) },
                    { icon: () => h(NIcon, null, { default: () => h(SendOutline) }) }
                  )
                : null,
            ]
          : null,
      ]),
  },
]

onMounted(() => {
  fetchRealizations()
})
</script>

<template>
  <div>
    <PageHeader title="Realisasi Bulanan" subtitle="Kelola realisasi anggaran bulanan">
      <template #actions>
        <NButton v-if="canCreate" type="primary" @click="openCreateModal">
          <template #icon>
            <NIcon><Add /></NIcon>
          </template>
          Tambah Realisasi
        </NButton>
      </template>
    </PageHeader>

    <!-- Filter -->
    <PageCard class="mb-6">
      <NSpace :size="16" wrap>
        <FormSelect
          v-model="selectedYear"
          label="Tahun"
          :options="years"
          style="min-width: 120px"
          @update:model-value="fetchRealizations"
        />
        <FormSelect
          v-model="selectedMonth"
          label="Bulan"
          :options="monthOptions"
          style="min-width: 150px"
          @update:model-value="fetchRealizations"
        />
        <FormSelect
          v-model="selectedStatus"
          label="Status"
          :options="statusOptions"
          style="min-width: 150px"
          @update:model-value="fetchRealizations"
        />
      </NSpace>
    </PageCard>

    <PageCard :padding="false">
      <LoadingSpinner v-if="loading" />
      <template v-else>
        <div v-if="realizations.length === 0" class="text-center py-12 text-gray-500">
          Belum ada realisasi untuk periode ini
        </div>
        <NDataTable
          v-else
          :columns="columns"
          :data="realizations"
          :bordered="false"
          striped
          :scroll-x="1000"
        />
      </template>
    </PageCard>

    <!-- Form Modal -->
    <NModal
      v-model:show="showModal"
      preset="card"
      :title="isEditing ? 'Edit Realisasi' : 'Tambah Realisasi'"
      style="width: 600px"
    >
      <form @submit.prevent="saveRealization" class="space-y-4">
        <div v-if="formError" class="p-3 bg-red-50 border border-red-200 text-red-700 rounded-md text-sm">
          {{ formError }}
        </div>

        <FormSelect
          v-model="form.monthly_plan_id"
          label="Rencana Bulanan"
          placeholder="Pilih rencana bulanan"
          :options="planOptions"
          :disabled="isEditing"
          filterable
          required
          @update:model-value="updateRealizedAmount"
        />

        <div class="grid grid-cols-2 gap-4">
          <FormInputNumber
            v-model="form.realized_volume"
            label="Volume Realisasi"
            :min="0"
            :step="0.01"
            required
            @update:model-value="updateRealizedAmount"
          />
          <FormInputNumber
            v-model="form.realized_amount"
            label="Jumlah Realisasi"
            :min="0"
            :format="(v) => v ? `Rp ${v.toLocaleString('id-ID')}` : ''"
            :parse="(v) => Number(v.replace(/[^0-9]/g, ''))"
            required
          />
        </div>

        <FormInput
          v-model="form.notes"
          label="Catatan"
          placeholder="Catatan tambahan (opsional)"
          type="textarea"
        />

        <div class="flex justify-end gap-3 pt-4">
          <NButton @click="showModal = false">Batal</NButton>
          <NButton type="primary" attr-type="submit" :loading="saving">
            {{ saving ? 'Menyimpan...' : 'Simpan' }}
          </NButton>
        </div>
      </form>
    </NModal>

    <!-- Documents Modal -->
    <NModal
      v-model:show="showDocumentsModal"
      preset="card"
      title="Dokumen Pendukung"
      style="width: 700px"
    >
      <div v-if="selectedRealization" class="mb-4 p-3 bg-gray-50 rounded-lg text-sm">
        <p class="font-medium">{{ selectedRealization.monthly_plan?.budget_item?.name }}</p>
        <p class="text-gray-600">
          {{ getMonthName(selectedRealization.monthly_plan?.month || 0) }} {{ selectedRealization.monthly_plan?.year }}
          - {{ formatCurrency(selectedRealization.realized_amount) }}
        </p>
      </div>

      <!-- Upload Area -->
      <div v-if="selectedRealization && canManageDocuments(selectedRealization)" class="mb-4">
        <NUpload
          :custom-request="handleUpload"
          :show-file-list="false"
          accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png"
        >
          <NUploadDragger>
            <div class="py-4">
              <NIcon size="48" :depth="3">
                <CloudUploadOutline />
              </NIcon>
              <NText class="block mt-2">Klik atau drag file kesini untuk upload</NText>
              <NText depth="3" class="text-xs">PDF, DOC, DOCX, XLS, XLSX, JPG, PNG (Maks. 10MB)</NText>
            </div>
          </NUploadDragger>
        </NUpload>
      </div>

      <!-- Documents List -->
      <div v-if="documents.length === 0" class="text-center py-8 text-gray-500">
        Belum ada dokumen
      </div>
      <div v-else class="space-y-3">
        <div
          v-for="doc in documents"
          :key="doc.id"
          class="flex items-center justify-between p-3 bg-gray-50 rounded-lg"
        >
          <div class="flex items-center gap-3">
            <NIcon size="24" class="text-gray-500"><DocumentOutline /></NIcon>
            <div>
              <p class="font-medium text-gray-800 truncate max-w-md">{{ doc.original_filename }}</p>
              <p class="text-xs text-gray-500">{{ formatFileSize(doc.file_size) }}</p>
            </div>
          </div>
          <NSpace :size="4">
            <NButton size="small" quaternary @click="downloadDocument(doc)">
              <template #icon><NIcon><DownloadOutline /></NIcon></template>
            </NButton>
            <NButton
              v-if="selectedRealization && canManageDocuments(selectedRealization)"
              size="small"
              quaternary
              type="error"
              @click="deleteDocument(doc)"
            >
              <template #icon><NIcon><TrashOutline /></NIcon></template>
            </NButton>
          </NSpace>
        </div>
      </div>

      <div class="flex justify-end pt-4 mt-4 border-t">
        <NButton @click="showDocumentsModal = false">Tutup</NButton>
      </div>
    </NModal>
  </div>
</template>
