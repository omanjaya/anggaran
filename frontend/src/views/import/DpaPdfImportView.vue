<script setup lang="ts">
import { h, ref, computed } from 'vue'
import {
  NButton,
  NUpload,
  NUploadDragger,
  NIcon,
  NText,
  NTag,
  NDataTable,
  NSpace,
  NAlert,
  NCard,
  NDescriptions,
  NDescriptionsItem,
  NCollapse,
  NCollapseItem,
  NProgress,
  NSpin,
  NResult,
} from 'naive-ui'
import type { DataTableColumns, UploadFileInfo } from 'naive-ui'
import {
  CloudUploadOutline,
  DocumentOutline,
  CloseOutline,
  CheckmarkCircleOutline,
  AlertCircleOutline,
  TrashOutline,
  EyeOutline,
} from '@vicons/ionicons5'
import { PageHeader, PageCard } from '@/components/ui'
import { useFormat, useMessage } from '@/composables'
import api from '@/services/api'

interface ParsedDpaData {
  header: {
    nomor_dpa: string | null
    tahun_anggaran: number | null
    program: { code: string | null; name: string | null }
    kegiatan: { code: string | null; name: string | null }
    organisasi: { code: string | null; name: string | null }
    alokasi_tahun: number
  }
  sub_activity: {
    code: string | null
    name: string | null
    sumber_pendanaan: string | null
  }
  budget_items: Array<{
    code: string
    description: string
    amount: number
  }>
  monthly_plan: Record<number, number>
}

interface FileItem {
  id: string
  file: File
  status: 'pending' | 'previewing' | 'previewed' | 'importing' | 'success' | 'error'
  data?: ParsedDpaData
  error?: string
  log?: Array<{ type: string; message: string; details?: string }>
}

const { formatCurrency } = useFormat()
const message = useMessage()

const files = ref<FileItem[]>([])
const isProcessing = ref(false)
const activePreviewId = ref<string | null>(null)

const totalFiles = computed(() => files.value.length)
const successFiles = computed(() => files.value.filter(f => f.status === 'success').length)
const errorFiles = computed(() => files.value.filter(f => f.status === 'error').length)
const pendingFiles = computed(() => files.value.filter(f => f.status === 'pending' || f.status === 'previewed').length)

function handleFileChange(options: { fileList: UploadFileInfo[] }) {
  const newFiles = options.fileList
    .filter(f => f.file && !files.value.some(existing => existing.file.name === f.file?.name))
    .map(f => ({
      id: crypto.randomUUID(),
      file: f.file!,
      status: 'pending' as const,
    }))

  files.value.push(...newFiles)
}

function removeFile(id: string) {
  files.value = files.value.filter(f => f.id !== id)
  if (activePreviewId.value === id) {
    activePreviewId.value = null
  }
}

function clearAllFiles() {
  files.value = []
  activePreviewId.value = null
}

async function previewFile(fileItem: FileItem) {
  fileItem.status = 'previewing'
  activePreviewId.value = fileItem.id

  try {
    const formData = new FormData()
    formData.append('file', fileItem.file)

    const response = await api.post('/import/dpa-pdf/preview', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })

    fileItem.data = response.data.data
    fileItem.status = 'previewed'
  } catch (err: any) {
    fileItem.status = 'error'
    fileItem.error = err.response?.data?.message || 'Gagal membaca PDF'
  }
}

async function importSingleFile(fileItem: FileItem) {
  fileItem.status = 'importing'

  try {
    const formData = new FormData()
    formData.append('file', fileItem.file)

    const response = await api.post('/import/dpa-pdf', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })

    if (response.data.success) {
      fileItem.status = 'success'
      fileItem.log = response.data.log
      message.success(`Import ${fileItem.file.name} berhasil!`)
    } else {
      fileItem.status = 'error'
      fileItem.error = response.data.message
      fileItem.log = response.data.log
    }
  } catch (err: any) {
    fileItem.status = 'error'
    fileItem.error = err.response?.data?.message || 'Gagal import'
    fileItem.log = err.response?.data?.log
  }
}

async function importAllFiles() {
  const filesToImport = files.value.filter(f => f.status === 'pending' || f.status === 'previewed')
  if (filesToImport.length === 0) {
    message.warning('Tidak ada file untuk diimport')
    return
  }

  isProcessing.value = true

  for (const fileItem of filesToImport) {
    await importSingleFile(fileItem)
  }

  isProcessing.value = false
  message.success(`Import selesai: ${successFiles.value} berhasil, ${errorFiles.value} gagal`)
}

const activePreviewData = computed(() => {
  const file = files.value.find(f => f.id === activePreviewId.value)
  return file?.data
})

const monthNames = [
  '', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
  'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
]

const budgetItemColumns: DataTableColumns<{ code: string; description: string; amount: number }> = [
  { title: 'Kode Rekening', key: 'code', width: 180 },
  { title: 'Uraian', key: 'description', ellipsis: { tooltip: true } },
  {
    title: 'Jumlah (Rp)',
    key: 'amount',
    width: 160,
    align: 'right',
    render: row => formatCurrency(row.amount),
  },
]

function getStatusTag(status: FileItem['status']) {
  const config: Record<string, { type: 'default' | 'info' | 'success' | 'error' | 'warning'; label: string }> = {
    pending: { type: 'default', label: 'Menunggu' },
    previewing: { type: 'info', label: 'Membaca...' },
    previewed: { type: 'warning', label: 'Siap Import' },
    importing: { type: 'info', label: 'Importing...' },
    success: { type: 'success', label: 'Berhasil' },
    error: { type: 'error', label: 'Gagal' },
  }
  return config[status] || config.pending
}
</script>

<template>
  <div>
    <PageHeader
      title="Import DPA dari PDF"
      subtitle="Import data DPA dari file PDF hasil export sistem lain"
    >
      <template #actions>
        <NSpace>
          <NButton :disabled="files.length === 0" @click="clearAllFiles">
            Hapus Semua
          </NButton>
          <NButton
            type="primary"
            :loading="isProcessing"
            :disabled="pendingFiles === 0"
            @click="importAllFiles"
          >
            Import {{ pendingFiles }} File
          </NButton>
        </NSpace>
      </template>
    </PageHeader>

    <!-- Upload Area -->
    <PageCard class="mb-6">
      <NUpload
        multiple
        :show-file-list="false"
        accept=".pdf"
        @change="handleFileChange"
      >
        <NUploadDragger>
          <div class="py-8">
            <NIcon size="48" :depth="3">
              <CloudUploadOutline />
            </NIcon>
            <NText class="block mt-4">Drag & drop file PDF DPA disini, atau klik untuk memilih</NText>
            <NText depth="3" class="text-sm">Format: .pdf (max 10MB per file, max 20 file)</NText>
          </div>
        </NUploadDragger>
      </NUpload>
    </PageCard>

    <!-- File List -->
    <div v-if="files.length > 0" class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <!-- Left: File List -->
      <PageCard>
        <template #header>
          <div class="flex justify-between items-center">
            <h3 class="font-semibold">Daftar File ({{ totalFiles }})</h3>
            <div class="text-sm">
              <span class="text-green-600">{{ successFiles }} berhasil</span> |
              <span class="text-red-600">{{ errorFiles }} gagal</span>
            </div>
          </div>
        </template>

        <div class="space-y-3 max-h-[500px] overflow-y-auto">
          <div
            v-for="fileItem in files"
            :key="fileItem.id"
            :class="[
              'p-3 rounded-lg border cursor-pointer transition-colors',
              activePreviewId === fileItem.id ? 'border-blue-500 bg-blue-50' : 'border-gray-200 hover:border-gray-300',
            ]"
            @click="activePreviewId = fileItem.id"
          >
            <div class="flex items-center gap-3">
              <NIcon size="32" class="text-red-500">
                <DocumentOutline />
              </NIcon>
              <div class="flex-1 min-w-0">
                <p class="font-medium text-gray-900 truncate">{{ fileItem.file.name }}</p>
                <p class="text-xs text-gray-500">{{ (fileItem.file.size / 1024).toFixed(1) }} KB</p>
              </div>
              <NTag :type="getStatusTag(fileItem.status).type" size="small">
                {{ getStatusTag(fileItem.status).label }}
              </NTag>
              <NSpace>
                <NButton
                  v-if="fileItem.status === 'pending'"
                  size="small"
                  quaternary
                  @click.stop="previewFile(fileItem)"
                >
                  <template #icon><NIcon><EyeOutline /></NIcon></template>
                </NButton>
                <NButton
                  v-if="fileItem.status === 'previewed'"
                  size="small"
                  type="primary"
                  @click.stop="importSingleFile(fileItem)"
                >
                  Import
                </NButton>
                <NButton
                  size="small"
                  quaternary
                  type="error"
                  @click.stop="removeFile(fileItem.id)"
                >
                  <template #icon><NIcon><TrashOutline /></NIcon></template>
                </NButton>
              </NSpace>
            </div>

            <!-- Error message -->
            <div v-if="fileItem.error" class="mt-2">
              <NAlert type="error" :show-icon="false" size="small">
                {{ fileItem.error }}
              </NAlert>
            </div>

            <!-- Progress for importing -->
            <div v-if="fileItem.status === 'importing' || fileItem.status === 'previewing'" class="mt-2">
              <NProgress type="line" :percentage="100" processing :show-indicator="false" />
            </div>
          </div>
        </div>
      </PageCard>

      <!-- Right: Preview -->
      <PageCard>
        <template #header>
          <h3 class="font-semibold">Preview Data</h3>
        </template>

        <div v-if="!activePreviewId" class="py-12 text-center text-gray-500">
          Pilih file untuk melihat preview
        </div>

        <div v-else-if="files.find(f => f.id === activePreviewId)?.status === 'previewing'" class="py-12 text-center">
          <NSpin size="large" />
          <p class="mt-4 text-gray-600">Membaca PDF...</p>
        </div>

        <div v-else-if="activePreviewData" class="space-y-4">
          <!-- Header Info -->
          <NCard size="small" title="Informasi DPA">
            <NDescriptions :column="1" label-placement="left" size="small">
              <NDescriptionsItem label="Nomor DPA">
                {{ activePreviewData.header.nomor_dpa || '-' }}
              </NDescriptionsItem>
              <NDescriptionsItem label="Tahun Anggaran">
                {{ activePreviewData.header.tahun_anggaran || '-' }}
              </NDescriptionsItem>
              <NDescriptionsItem label="SKPD">
                {{ activePreviewData.header.organisasi?.name || '-' }}
              </NDescriptionsItem>
              <NDescriptionsItem label="Program">
                <span class="font-mono text-xs">{{ activePreviewData.header.program?.code }}</span>
                {{ activePreviewData.header.program?.name || '-' }}
              </NDescriptionsItem>
              <NDescriptionsItem label="Kegiatan">
                <span class="font-mono text-xs">{{ activePreviewData.header.kegiatan?.code }}</span>
                {{ activePreviewData.header.kegiatan?.name || '-' }}
              </NDescriptionsItem>
              <NDescriptionsItem label="Sub Kegiatan">
                <span class="font-mono text-xs">{{ activePreviewData.sub_activity?.code }}</span>
                {{ activePreviewData.sub_activity?.name || '-' }}
              </NDescriptionsItem>
              <NDescriptionsItem label="Total Anggaran">
                <span class="font-semibold text-green-600">
                  {{ formatCurrency(activePreviewData.header.alokasi_tahun) }}
                </span>
              </NDescriptionsItem>
            </NDescriptions>
          </NCard>

          <!-- Budget Items -->
          <NCollapse>
            <NCollapseItem
              :title="`Rincian Belanja (${activePreviewData.budget_items?.length || 0} item)`"
              name="budget-items"
            >
              <NDataTable
                :columns="budgetItemColumns"
                :data="activePreviewData.budget_items || []"
                :bordered="false"
                size="small"
                :max-height="200"
              />
            </NCollapseItem>

            <NCollapseItem title="Rencana Bulanan" name="monthly">
              <div class="grid grid-cols-3 gap-2 text-sm">
                <div
                  v-for="month in 12"
                  :key="month"
                  class="p-2 bg-gray-50 rounded"
                >
                  <span class="text-gray-600">{{ monthNames[month] }}:</span>
                  <span class="font-medium ml-1">
                    {{ formatCurrency(activePreviewData.monthly_plan?.[month] || 0) }}
                  </span>
                </div>
              </div>
            </NCollapseItem>
          </NCollapse>
        </div>

        <!-- Success Result -->
        <div v-else-if="files.find(f => f.id === activePreviewId)?.status === 'success'">
          <NResult status="success" title="Import Berhasil" description="Data DPA berhasil diimport ke database">
            <template #footer>
              <NCollapse v-if="files.find(f => f.id === activePreviewId)?.log?.length">
                <NCollapseItem title="Log Import" name="log">
                  <div class="text-sm space-y-1">
                    <div
                      v-for="(logItem, idx) in files.find(f => f.id === activePreviewId)?.log"
                      :key="idx"
                      :class="[
                        'p-2 rounded',
                        logItem.type === 'success' ? 'bg-green-50 text-green-700' :
                        logItem.type === 'error' ? 'bg-red-50 text-red-700' :
                        'bg-gray-50 text-gray-700'
                      ]"
                    >
                      {{ logItem.message }}
                      <span v-if="logItem.details" class="text-xs opacity-75 ml-1">({{ logItem.details }})</span>
                    </div>
                  </div>
                </NCollapseItem>
              </NCollapse>
            </template>
          </NResult>
        </div>

        <!-- Error Result -->
        <div v-else-if="files.find(f => f.id === activePreviewId)?.status === 'error'">
          <NResult status="error" title="Gagal" :description="files.find(f => f.id === activePreviewId)?.error" />
        </div>
      </PageCard>
    </div>

    <!-- Empty State -->
    <PageCard v-else>
      <div class="py-12 text-center text-gray-500">
        <NIcon size="64" :depth="3"><DocumentOutline /></NIcon>
        <p class="mt-4">Belum ada file yang dipilih</p>
        <p class="text-sm">Upload file PDF DPA untuk memulai import</p>
      </div>
    </PageCard>
  </div>
</template>
