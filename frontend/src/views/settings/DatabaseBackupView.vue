<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import {
  NCard, NButton, NIcon, NSpace, NTag, NModal, NEmpty, NSpin, NDataTable, NPopconfirm, NUpload, NAlert
} from 'naive-ui'
import type { UploadFileInfo } from 'naive-ui'
import {
  CloudDownloadOutline, TrashOutline, RefreshOutline, ServerOutline,
  DocumentOutline, FolderOutline, CloudUploadOutline, SwapVerticalOutline
} from '@vicons/ionicons5'
import { PageHeader, StatCard } from '@/components/ui'
import { useFormat, useMessage } from '@/composables'
import api from '@/services/api'

interface Backup {
  filename: string
  path: string
  size: number
  size_formatted: string
  created_at: string
}

interface BackupStats {
  total_backups: number
  total_size: number
  total_size_formatted: string
  last_backup: string | null
  database_stats: Record<string, number>
}

const { formatDate } = useFormat()
const message = useMessage()

const loading = ref(false)
const creating = ref(false)
const restoring = ref(false)
const backups = ref<Backup[]>([])
const stats = ref<BackupStats | null>(null)
const showStatsModal = ref(false)
const showImportModal = ref(false)
const uploadedFile = ref<File | null>(null)

const columns = [
  {
    title: 'Nama File',
    key: 'filename',
    render: (row: Backup) => h('div', { class: 'flex items-center gap-2' }, [
      h(NIcon, { size: 18, color: '#3b82f6' }, () => h(DocumentOutline)),
      h('span', { class: 'font-medium' }, row.filename)
    ])
  },
  {
    title: 'Ukuran',
    key: 'size_formatted',
    width: 120
  },
  {
    title: 'Dibuat',
    key: 'created_at',
    width: 180,
    render: (row: Backup) => formatDate(row.created_at, { format: 'datetime' })
  },
  {
    title: 'Aksi',
    key: 'actions',
    width: 250,
    render: (row: Backup) => h(NSpace, { size: 8 }, () => [
      h(NButton, {
        size: 'small',
        type: 'primary',
        onClick: () => downloadBackup(row.filename)
      }, {
        icon: () => h(NIcon, null, () => h(CloudDownloadOutline)),
        default: () => 'Unduh'
      }),
      h(NPopconfirm, {
        onPositiveClick: () => restoreFromFile(row.filename)
      }, {
        trigger: () => h(NButton, { size: 'small', type: 'warning' }, {
          icon: () => h(NIcon, null, () => h(SwapVerticalOutline)),
          default: () => 'Pulihkan'
        }),
        default: () => 'Pulihkan database dari backup ini? Data saat ini akan diganti.'
      }),
      h(NPopconfirm, {
        onPositiveClick: () => deleteBackup(row.filename)
      }, {
        trigger: () => h(NButton, { size: 'small', type: 'error' }, {
          icon: () => h(NIcon, null, () => h(TrashOutline))
        }),
        default: () => 'Hapus backup ini?'
      })
    ])
  }
]

const totalRecords = computed(() => {
  if (!stats.value?.database_stats) return 0
  return Object.values(stats.value.database_stats).reduce((a, b) => a + b, 0)
})

async function fetchBackups() {
  loading.value = true
  try {
    const [backupsRes, statsRes] = await Promise.all([
      api.get('/backups'),
      api.get('/backups/stats')
    ])
    backups.value = backupsRes.data.data || []
    stats.value = statsRes.data.data || null
  } catch (err) {
    console.error('Failed to fetch backups:', err)
    message.error('Gagal memuat daftar backup')
  } finally {
    loading.value = false
  }
}

async function createBackup() {
  creating.value = true
  try {
    const response = await api.post('/backups/create')
    if (response.data.success) {
      message.success(response.data.message)
      await fetchBackups()
    }
  } catch (err: any) {
    console.error('Failed to create backup:', err)
    message.error(err.response?.data?.message || 'Gagal membuat backup')
  } finally {
    creating.value = false
  }
}

async function downloadBackup(filename: string) {
  try {
    const response = await api.get(`/backups/download/${filename}`, {
      responseType: 'blob'
    })

    const url = window.URL.createObjectURL(new Blob([response.data]))
    const link = document.createElement('a')
    link.href = url
    link.setAttribute('download', filename)
    document.body.appendChild(link)
    link.click()
    link.remove()
    window.URL.revokeObjectURL(url)

    message.success('Backup berhasil diunduh')
  } catch (err) {
    console.error('Failed to download backup:', err)
    message.error('Gagal mengunduh backup')
  }
}

async function deleteBackup(filename: string) {
  try {
    await api.delete(`/backups/${filename}`)
    message.success('Backup berhasil dihapus')
    await fetchBackups()
  } catch (err) {
    console.error('Failed to delete backup:', err)
    message.error('Gagal menghapus backup')
  }
}

async function restoreFromFile(filename: string) {
  restoring.value = true
  try {
    const response = await api.post(`/backups/restore/${filename}`)
    if (response.data.success) {
      message.success(response.data.message)
      await fetchBackups()
    }
  } catch (err: any) {
    console.error('Failed to restore backup:', err)
    message.error(err.response?.data?.message || 'Gagal memulihkan database')
  } finally {
    restoring.value = false
  }
}

function handleUploadChange(options: { file: UploadFileInfo }) {
  if (options.file.status === 'pending') {
    uploadedFile.value = options.file.file || null
  }
}

async function restoreFromUpload() {
  if (!uploadedFile.value) {
    message.warning('Pilih file backup terlebih dahulu')
    return
  }

  restoring.value = true
  try {
    const formData = new FormData()
    formData.append('file', uploadedFile.value)

    const response = await api.post('/backups/restore', formData, {
      headers: { 'Content-Type': 'multipart/form-data' }
    })

    if (response.data.success) {
      message.success(response.data.message)
      showImportModal.value = false
      uploadedFile.value = null
      await fetchBackups()
    }
  } catch (err: any) {
    console.error('Failed to restore backup:', err)
    message.error(err.response?.data?.message || 'Gagal memulihkan database')
  } finally {
    restoring.value = false
  }
}

// Import h for render functions
import { h } from 'vue'

onMounted(() => {
  fetchBackups()
})
</script>

<template>
  <div>
    <PageHeader title="Backup Database" subtitle="Kelola backup database untuk keamanan data">
      <template #actions>
        <NSpace>
          <NButton @click="fetchBackups" :disabled="loading">
            <template #icon>
              <NIcon><RefreshOutline /></NIcon>
            </template>
            Refresh
          </NButton>
          <NButton type="warning" @click="showImportModal = true" :loading="restoring">
            <template #icon>
              <NIcon><CloudUploadOutline /></NIcon>
            </template>
            Import Backup
          </NButton>
          <NButton type="primary" @click="createBackup" :loading="creating">
            <template #icon>
              <NIcon><ServerOutline /></NIcon>
            </template>
            Buat Backup Baru
          </NButton>
        </NSpace>
      </template>
    </PageHeader>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
      <StatCard
        label="Total Backup"
        :value="stats?.total_backups || 0"
        icon="folder"
      />
      <StatCard
        label="Total Ukuran"
        :value="stats?.total_size_formatted || '0 B'"
        icon="storage"
        variant="primary"
      />
      <StatCard
        label="Total Record"
        :value="totalRecords"
        icon="database"
        variant="success"
      />
      <NCard size="small" class="stat-card-custom">
        <div class="text-sm text-gray-500 mb-1">Backup Terakhir</div>
        <div class="text-lg font-semibold">
          {{ stats?.last_backup ? formatDate(stats.last_backup, { format: 'datetime' }) : 'Belum ada' }}
        </div>
      </NCard>
    </div>

    <!-- Database Stats Button -->
    <NCard class="mb-6">
      <div class="flex items-center justify-between">
        <div>
          <h3 class="font-semibold">Statistik Database</h3>
          <p class="text-sm text-gray-500">Lihat jumlah data per tabel</p>
        </div>
        <NButton @click="showStatsModal = true">
          <template #icon>
            <NIcon><FolderOutline /></NIcon>
          </template>
          Lihat Detail
        </NButton>
      </div>
    </NCard>

    <!-- Backups Table -->
    <NCard title="Daftar Backup">
      <NSpin :show="loading">
        <NDataTable
          v-if="backups.length > 0"
          :columns="columns"
          :data="backups"
          :bordered="false"
          :single-line="false"
        />
        <NEmpty v-else description="Belum ada backup. Klik 'Buat Backup Baru' untuk membuat backup pertama." />
      </NSpin>
    </NCard>

    <!-- Stats Modal -->
    <NModal
      v-model:show="showStatsModal"
      preset="card"
      title="Statistik Database"
      style="width: 500px"
    >
      <div v-if="stats?.database_stats" class="space-y-3">
        <div
          v-for="(count, table) in stats.database_stats"
          :key="table"
          class="flex items-center justify-between p-3 bg-gray-50 rounded-lg"
        >
          <div class="flex items-center gap-2">
            <NIcon size="18" color="#6366f1"><DocumentOutline /></NIcon>
            <span class="font-medium">{{ table }}</span>
          </div>
          <NTag :type="count > 0 ? 'success' : 'default'" size="small">
            {{ count.toLocaleString() }} records
          </NTag>
        </div>
        <div class="pt-3 border-t flex items-center justify-between">
          <span class="font-semibold">Total</span>
          <NTag type="primary">{{ totalRecords.toLocaleString() }} records</NTag>
        </div>
      </div>
    </NModal>

    <!-- Import Modal -->
    <NModal
      v-model:show="showImportModal"
      preset="card"
      title="Import Backup Database"
      style="width: 500px"
    >
      <div class="space-y-4">
        <NAlert type="warning" title="Perhatian" class="mb-4">
          Proses import akan <strong>mengganti semua data</strong> saat ini dengan data dari file backup.
          Pastikan Anda telah membuat backup terlebih dahulu jika diperlukan.
        </NAlert>

        <NUpload
          accept=".sql,.txt"
          :max="1"
          :default-upload="false"
          @change="handleUploadChange"
        >
          <NButton>
            <template #icon>
              <NIcon><CloudUploadOutline /></NIcon>
            </template>
            Pilih File Backup (.sql)
          </NButton>
        </NUpload>

        <div v-if="uploadedFile" class="p-3 bg-gray-100 rounded-lg flex items-center gap-2">
          <NIcon size="18" color="#3b82f6"><DocumentOutline /></NIcon>
          <span class="font-medium">{{ uploadedFile.name }}</span>
          <span class="text-gray-500 text-sm">({{ (uploadedFile.size / 1024).toFixed(1) }} KB)</span>
        </div>

        <div class="flex justify-end gap-2 pt-4">
          <NButton @click="showImportModal = false">Batal</NButton>
          <NButton
            type="warning"
            :loading="restoring"
            :disabled="!uploadedFile"
            @click="restoreFromUpload"
          >
            <template #icon>
              <NIcon><SwapVerticalOutline /></NIcon>
            </template>
            Pulihkan Database
          </NButton>
        </div>
      </div>
    </NModal>
  </div>
</template>

<style scoped>
.stat-card-custom {
  display: flex;
  flex-direction: column;
  justify-content: center;
}
</style>
