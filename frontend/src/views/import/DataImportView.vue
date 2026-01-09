<script setup lang="ts">
import { h, ref, computed } from 'vue'
import { NButton, NRadioGroup, NRadio, NUpload, NUploadDragger, NIcon, NText, NTag, NDataTable, NSpace, NAlert } from 'naive-ui'
import type { DataTableColumns, UploadFileInfo } from 'naive-ui'
import { CloudUploadOutline, DocumentOutline, CloseOutline, CheckmarkCircleOutline, AlertCircleOutline } from '@vicons/ionicons5'
import { PageHeader, PageCard } from '@/components/ui'
import { FormSelect } from '@/components/ui'
import { useFormat, useMessage } from '@/composables'
import api from '@/services/api'

interface PreviewRow {
  row_number: number
  program_code: string
  program_name: string
  activity_code: string
  activity_name: string
  sub_activity_code: string
  sub_activity_name: string
  item_code: string
  item_name: string
  unit: string
  price: number
  quantity: number
  total: number
  status: 'valid' | 'warning' | 'error'
  message?: string
}

interface ImportResult {
  success: boolean
  total_rows: number
  imported: number
  skipped: number
  errors: string[]
  warnings: string[]
}

const { formatCurrency } = useFormat()
const message = useMessage()

const importType = ref<'dpa' | 'plgk'>('dpa')
const selectedFile = ref<File | null>(null)
const uploading = ref(false)
const importing = ref(false)
const previewData = ref<PreviewRow[]>([])
const importResult = ref<ImportResult | null>(null)
const selectedYear = ref(new Date().getFullYear())

const years = computed(() => {
  const currentYear = new Date().getFullYear()
  return Array.from({ length: 5 }, (_, i) => ({
    label: String(currentYear - 2 + i),
    value: currentYear - 2 + i,
  }))
})

const validRows = computed(() => previewData.value.filter((r) => r.status === 'valid').length)
const warningRows = computed(() => previewData.value.filter((r) => r.status === 'warning').length)
const errorRows = computed(() => previewData.value.filter((r) => r.status === 'error').length)

const statusTypes: Record<string, 'success' | 'warning' | 'error'> = {
  valid: 'success',
  warning: 'warning',
  error: 'error',
}

async function handleUpload(options: { file: UploadFileInfo }) {
  if (options.file.file) {
    selectedFile.value = options.file.file
    previewData.value = []
    importResult.value = null
  }
}

async function previewFile() {
  if (!selectedFile.value) return

  uploading.value = true
  try {
    const formData = new FormData()
    formData.append('file', selectedFile.value)
    formData.append('type', importType.value)
    formData.append('year', selectedYear.value.toString())

    const response = await api.post('/import/preview', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })

    previewData.value = response.data.data.rows || []
  } catch (err: any) {
    console.error('Failed to preview file:', err)
    message.error(err.response?.data?.message || 'Gagal memproses file')
  } finally {
    uploading.value = false
  }
}

async function executeImport() {
  if (!selectedFile.value || previewData.value.length === 0) return

  importing.value = true
  try {
    const formData = new FormData()
    formData.append('file', selectedFile.value)
    formData.append('year', selectedYear.value.toString())

    const endpoint = importType.value === 'dpa' ? '/import/dpa' : '/import/plgk'
    const response = await api.post(endpoint, formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })

    importResult.value = response.data.data
    if (importResult.value?.success) {
      previewData.value = []
      selectedFile.value = null
      message.success('Import berhasil!')
    }
  } catch (err: any) {
    console.error('Failed to import data:', err)
    message.error(err.response?.data?.message || 'Gagal mengimport data')
  } finally {
    importing.value = false
  }
}

async function downloadTemplate() {
  try {
    const response = await api.get(`/import/template/${importType.value}`, {
      responseType: 'blob',
    })

    const url = window.URL.createObjectURL(new Blob([response.data]))
    const link = document.createElement('a')
    link.href = url
    link.setAttribute('download', `template-${importType.value}.xlsx`)
    document.body.appendChild(link)
    link.click()
    link.remove()
  } catch (err) {
    console.error('Failed to download template:', err)
    message.error('Gagal mengunduh template')
  }
}

function clearFile() {
  selectedFile.value = null
  previewData.value = []
  importResult.value = null
}

const columns: DataTableColumns<PreviewRow> = [
  { title: 'No', key: 'row_number', width: 60 },
  {
    title: 'Status',
    key: 'status',
    width: 100,
    render: (row) => h(NTag, { type: statusTypes[row.status], size: 'small' }, () => row.status),
  },
  { title: 'Kode Program', key: 'program_code', width: 120 },
  { title: 'Nama Program', key: 'program_name', ellipsis: { tooltip: true } },
  { title: 'Kode Kegiatan', key: 'activity_code', width: 120 },
  { title: 'Nama Kegiatan', key: 'activity_name', ellipsis: { tooltip: true } },
  { title: 'Item', key: 'item_name', ellipsis: { tooltip: true } },
  {
    title: 'Jumlah',
    key: 'total',
    width: 140,
    align: 'right',
    render: (row) => formatCurrency(row.total),
  },
  { title: 'Pesan', key: 'message', width: 200, ellipsis: { tooltip: true } },
]
</script>

<template>
  <div>
    <PageHeader title="Import Data" subtitle="Import data DPA atau PLGK dari file Excel" />

    <!-- Import Type Selection -->
    <PageCard class="mb-6">
      <div class="grid grid-cols-3 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">Jenis Import</label>
          <NRadioGroup v-model:value="importType">
            <NSpace>
              <NRadio value="dpa">DPA (Dokumen Pelaksanaan Anggaran)</NRadio>
              <NRadio value="plgk">PLGK (Rencana Penarikan)</NRadio>
            </NSpace>
          </NRadioGroup>
        </div>
        <div>
          <FormSelect
            v-model="selectedYear"
            label="Tahun Anggaran"
            :options="years"
          />
        </div>
        <div class="flex items-end">
          <NButton @click="downloadTemplate">
            Download Template {{ importType.toUpperCase() }}
          </NButton>
        </div>
      </div>
    </PageCard>

    <!-- File Upload Area -->
    <PageCard class="mb-6">
      <NUpload
        v-if="!selectedFile"
        :custom-request="handleUpload"
        :show-file-list="false"
        accept=".xlsx,.xls"
      >
        <NUploadDragger>
          <div class="py-8">
            <NIcon size="48" :depth="3">
              <CloudUploadOutline />
            </NIcon>
            <NText class="block mt-4">Drag & drop file Excel disini, atau klik untuk memilih</NText>
            <NText depth="3" class="text-sm">Format: .xlsx, .xls (max 10MB)</NText>
          </div>
        </NUploadDragger>
      </NUpload>

      <div v-else class="flex items-center justify-center gap-4 py-4">
        <div class="flex items-center gap-3">
          <NIcon size="40" class="text-green-600"><DocumentOutline /></NIcon>
          <div class="text-left">
            <p class="font-medium text-gray-900">{{ selectedFile.name }}</p>
            <p class="text-sm text-gray-500">{{ (selectedFile.size / 1024).toFixed(1) }} KB</p>
          </div>
        </div>
        <NButton quaternary circle type="error" @click="clearFile">
          <template #icon><NIcon><CloseOutline /></NIcon></template>
        </NButton>
      </div>

      <div v-if="selectedFile && previewData.length === 0" class="mt-4 flex justify-center">
        <NButton type="warning" :loading="uploading" @click="previewFile">
          {{ uploading ? 'Memproses...' : 'Preview Data' }}
        </NButton>
      </div>
    </PageCard>

    <!-- Preview Data -->
    <PageCard v-if="previewData.length > 0" class="mb-6">
      <template #header>
        <div class="flex justify-between items-center">
          <div>
            <h3 class="text-lg font-semibold text-gray-800">Preview Data</h3>
            <p class="text-sm text-gray-600">
              Total: {{ previewData.length }} baris |
              <span class="text-green-600">Valid: {{ validRows }}</span> |
              <span class="text-yellow-600">Warning: {{ warningRows }}</span> |
              <span class="text-red-600">Error: {{ errorRows }}</span>
            </p>
          </div>
          <NSpace>
            <NButton @click="clearFile">Batal</NButton>
            <NButton
              type="primary"
              :loading="importing"
              :disabled="errorRows > 0"
              @click="executeImport"
            >
              {{ importing ? 'Mengimport...' : `Import ${validRows + warningRows} Data` }}
            </NButton>
          </NSpace>
        </div>
      </template>

      <NDataTable
        :columns="columns"
        :data="previewData"
        :bordered="false"
        :max-height="400"
        striped
        :scroll-x="1200"
      />
    </PageCard>

    <!-- Import Result -->
    <PageCard v-if="importResult">
      <div class="flex items-center gap-3 mb-4">
        <div
          :class="[
            'w-12 h-12 rounded-full flex items-center justify-center',
            importResult.success ? 'bg-green-100' : 'bg-red-100',
          ]"
        >
          <NIcon size="24" :class="importResult.success ? 'text-green-600' : 'text-red-600'">
            <CheckmarkCircleOutline v-if="importResult.success" />
            <AlertCircleOutline v-else />
          </NIcon>
        </div>
        <div>
          <h3 class="text-lg font-semibold" :class="importResult.success ? 'text-green-700' : 'text-red-700'">
            {{ importResult.success ? 'Import Berhasil!' : 'Import Gagal' }}
          </h3>
          <p class="text-gray-600">
            Total: {{ importResult.total_rows }} | Imported: {{ importResult.imported }} | Skipped: {{ importResult.skipped }}
          </p>
        </div>
      </div>

      <NAlert v-if="importResult.errors.length > 0" type="error" title="Errors" class="mt-4">
        <ul class="list-disc list-inside text-sm">
          <li v-for="(error, idx) in importResult.errors" :key="idx">{{ error }}</li>
        </ul>
      </NAlert>

      <NAlert v-if="importResult.warnings.length > 0" type="warning" title="Warnings" class="mt-4">
        <ul class="list-disc list-inside text-sm">
          <li v-for="(warning, idx) in importResult.warnings" :key="idx">{{ warning }}</li>
        </ul>
      </NAlert>
    </PageCard>
  </div>
</template>
