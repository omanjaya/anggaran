<script setup lang="ts">
import { h, ref, onMounted, computed, watch } from 'vue'
import {
  NButton,
  NSelect,
  NModal,
  NDataTable,
  NSpace,
  NIcon,
  NTag,
  NInput,
  NProgress,
  NButtonGroup,
  NDatePicker,
} from 'naive-ui'
import type { DataTableColumns } from 'naive-ui'
import { AddOutline, BuildOutline, CreateOutline, TrashOutline } from '@vicons/ionicons5'
import { PageHeader, PageCard, LoadingSpinner, FormInput, FormSelect, FormInputNumber } from '@/components/ui'
import { useFormat, useMessage } from '@/composables'
import api from '@/services/api'

interface OperationalSchedule {
  id: number
  budget_item_id: number
  activity_name: string
  start_date: string
  end_date: string
  planned_amount: number
  status: 'NOT_STARTED' | 'IN_PROGRESS' | 'COMPLETED' | 'DELAYED'
  progress_percentage: number
  pic_name: string | null
  notes: string | null
  budget_item?: {
    id: number
    name: string
    code: string
    sub_activity?: {
      name: string
    }
  }
}

const { formatCurrency, formatDate } = useFormat()
const message = useMessage()

const loading = ref(true)
const schedules = ref<OperationalSchedule[]>([])
const viewMode = ref<'table' | 'calendar' | 'gantt'>('table')
const selectedYear = ref(new Date().getFullYear())
const selectedMonth = ref(new Date().getMonth() + 1)
const showModal = ref(false)
const editingSchedule = ref<OperationalSchedule | null>(null)
const submitting = ref(false)

const form = ref<{
  budget_item_id: number
  activity_name: string
  start_date: string | null
  end_date: string | null
  planned_amount: number
  status: 'NOT_STARTED' | 'IN_PROGRESS' | 'COMPLETED' | 'DELAYED'
  progress_percentage: number
  pic_name: string
  notes: string
}>({
  budget_item_id: 0,
  activity_name: '',
  start_date: null,
  end_date: null,
  planned_amount: 0,
  status: 'NOT_STARTED',
  progress_percentage: 0,
  pic_name: '',
  notes: '',
})

const years = computed(() => {
  const currentYear = new Date().getFullYear()
  return Array.from({ length: 5 }, (_, i) => ({ label: String(currentYear - 2 + i), value: currentYear - 2 + i }))
})

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

const statusTypes: Record<string, 'default' | 'info' | 'success' | 'error'> = {
  NOT_STARTED: 'default',
  IN_PROGRESS: 'info',
  COMPLETED: 'success',
  DELAYED: 'error',
}

const statusLabels: Record<string, string> = {
  NOT_STARTED: 'Belum Dimulai',
  IN_PROGRESS: 'Berjalan',
  COMPLETED: 'Selesai',
  DELAYED: 'Tertunda',
}

const statusOptions = [
  { value: 'NOT_STARTED', label: 'Belum Dimulai' },
  { value: 'IN_PROGRESS', label: 'Berjalan' },
  { value: 'COMPLETED', label: 'Selesai' },
  { value: 'DELAYED', label: 'Tertunda' },
]

async function fetchSchedules() {
  loading.value = true
  try {
    const response = await api.get('/operational-schedules', {
      params: {
        year: selectedYear.value,
        month: selectedMonth.value,
      },
    })
    // Handle both paginated and non-paginated responses
    const data = response.data.data
    schedules.value = Array.isArray(data) ? data : (data?.data || [])
  } catch (err) {
    console.error('Failed to fetch schedules:', err)
  } finally {
    loading.value = false
  }
}

function openCreateModal() {
  editingSchedule.value = null
  form.value = {
    budget_item_id: 0,
    activity_name: '',
    start_date: null,
    end_date: null,
    planned_amount: 0,
    status: 'NOT_STARTED',
    progress_percentage: 0,
    pic_name: '',
    notes: '',
  }
  showModal.value = true
}

function openEditModal(schedule: OperationalSchedule) {
  editingSchedule.value = schedule
  form.value = {
    budget_item_id: schedule.budget_item_id,
    activity_name: schedule.activity_name,
    start_date: schedule.start_date || null,
    end_date: schedule.end_date || null,
    planned_amount: schedule.planned_amount,
    status: schedule.status,
    progress_percentage: schedule.progress_percentage,
    pic_name: schedule.pic_name || '',
    notes: schedule.notes || '',
  }
  showModal.value = true
}

async function submitForm() {
  submitting.value = true
  try {
    if (editingSchedule.value) {
      await api.put(`/operational-schedules/${editingSchedule.value.id}`, form.value)
      message.success('Jadwal berhasil diupdate')
    } else {
      await api.post('/operational-schedules', form.value)
      message.success('Jadwal berhasil ditambahkan')
    }
    showModal.value = false
    fetchSchedules()
  } catch (err) {
    message.error('Gagal menyimpan jadwal')
  } finally {
    submitting.value = false
  }
}

async function deleteSchedule(id: number) {
  const confirmed = await message.confirm('Apakah Anda yakin ingin menghapus jadwal ini?')
  if (!confirmed) return
  try {
    await api.delete(`/operational-schedules/${id}`)
    message.success('Jadwal berhasil dihapus')
    fetchSchedules()
  } catch (err) {
    message.error('Gagal menghapus jadwal')
  }
}

async function updateStatus(schedule: OperationalSchedule, newStatus: string) {
  try {
    await api.post(`/operational-schedules/${schedule.id}/status`, {
      status: newStatus,
      progress_percentage: newStatus === 'COMPLETED' ? 100 : schedule.progress_percentage,
    })
    message.success('Status berhasil diupdate')
    fetchSchedules()
  } catch (err) {
    message.error('Gagal mengupdate status')
  }
}

async function generateFromPlgk() {
  const confirmed = await message.confirm('Generate jadwal dari PLGK yang sudah ada?')
  if (!confirmed) return
  try {
    await api.post('/operational-schedules/generate-from-plgk', {
      year: selectedYear.value,
      month: selectedMonth.value,
    })
    message.success('Jadwal berhasil di-generate dari PLGK')
    fetchSchedules()
  } catch (err) {
    message.error('Gagal generate jadwal dari PLGK')
  }
}

watch([selectedYear, selectedMonth], () => {
  fetchSchedules()
})

const columns: DataTableColumns<OperationalSchedule> = [
  { title: 'Kegiatan', key: 'activity_name', ellipsis: { tooltip: true } },
  {
    title: 'Item Belanja',
    key: 'budget_item',
    width: 180,
    ellipsis: { tooltip: true },
    render: (row) => row.budget_item?.name || '-',
  },
  {
    title: 'Tanggal',
    key: 'dates',
    width: 200,
    align: 'center',
    render: (row) =>
      `${formatDate(row.start_date, { format: 'short' })} - ${formatDate(row.end_date, { format: 'short' })}`,
  },
  {
    title: 'Nilai',
    key: 'planned_amount',
    width: 150,
    align: 'right',
    render: (row) => formatCurrency(row.planned_amount),
  },
  {
    title: 'Status',
    key: 'status',
    width: 130,
    align: 'center',
    render: (row) =>
      h(NTag, { type: statusTypes[row.status], size: 'small' }, () => statusLabels[row.status]),
  },
  {
    title: 'Progress',
    key: 'progress_percentage',
    width: 150,
    render: (row) =>
      h('div', { class: 'flex items-center gap-2' }, [
        h(NProgress, {
          type: 'line',
          percentage: row.progress_percentage,
          showIndicator: false,
          style: 'flex: 1',
        }),
        h('span', { class: 'text-xs' }, `${row.progress_percentage}%`),
      ]),
  },
  { title: 'PIC', key: 'pic_name', width: 120, render: (row) => row.pic_name || '-' },
  {
    title: 'Aksi',
    key: 'actions',
    width: 150,
    align: 'center',
    render: (row) =>
      h(NSpace, { justify: 'center', align: 'center' }, () => [
        h(
          NSelect,
          {
            value: row.status,
            options: statusOptions,
            size: 'small',
            style: 'width: 50px',
            onUpdateValue: (val: string) => updateStatus(row, val),
          }
        ),
        h(
          NButton,
          { quaternary: true, size: 'small', onClick: () => openEditModal(row) },
          () => h(NIcon, null, () => h(CreateOutline))
        ),
        h(
          NButton,
          { quaternary: true, type: 'error', size: 'small', onClick: () => deleteSchedule(row.id) },
          () => h(NIcon, null, () => h(TrashOutline))
        ),
      ]),
  },
]

onMounted(fetchSchedules)
</script>

<template>
  <div>
    <PageHeader title="ROK OP - Jadwal Operasional" subtitle="Kelola jadwal operasional kegiatan">
      <template #actions>
        <NSpace>
          <NSelect v-model:value="selectedYear" :options="years" style="width: 100px" />
          <NSelect v-model:value="selectedMonth" :options="months" style="width: 130px" />
          <NButtonGroup>
            <NButton :type="viewMode === 'table' ? 'primary' : 'default'" @click="viewMode = 'table'">
              Tabel
            </NButton>
            <NButton :type="viewMode === 'calendar' ? 'primary' : 'default'" @click="viewMode = 'calendar'">
              Kalender
            </NButton>
            <NButton :type="viewMode === 'gantt' ? 'primary' : 'default'" @click="viewMode = 'gantt'">
              Gantt
            </NButton>
          </NButtonGroup>
          <NButton type="warning" @click="generateFromPlgk">
            <template #icon><NIcon><BuildOutline /></NIcon></template>
            Generate dari PLGK
          </NButton>
          <NButton type="primary" @click="openCreateModal">
            <template #icon><NIcon><AddOutline /></NIcon></template>
            Tambah Jadwal
          </NButton>
        </NSpace>
      </template>
    </PageHeader>

    <LoadingSpinner v-if="loading" />

    <template v-else>
      <!-- Table View -->
      <PageCard v-if="viewMode === 'table'" :padding="false">
        <NDataTable
          :columns="columns"
          :data="schedules"
          :bordered="false"
          striped
          :scroll-x="1200"
        >
          <template #empty>
            <div class="py-12 text-center text-gray-500">
              Tidak ada jadwal untuk periode ini
            </div>
          </template>
        </NDataTable>
      </PageCard>

      <!-- Calendar View -->
      <PageCard v-else-if="viewMode === 'calendar'">
        <div class="grid grid-cols-7 gap-1 mb-4">
          <div
            v-for="day in ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab']"
            :key="day"
            class="text-center font-medium text-gray-600 py-2"
          >
            {{ day }}
          </div>
        </div>
        <p class="text-center text-gray-500 py-8">
          Tampilan kalender akan menampilkan kegiatan berdasarkan tanggal
        </p>
      </PageCard>

      <!-- Gantt View -->
      <PageCard v-else-if="viewMode === 'gantt'">
        <div class="space-y-3">
          <div
            v-for="schedule in schedules"
            :key="schedule.id"
            class="flex items-center gap-4"
          >
            <div class="w-48 text-sm text-gray-700 truncate">
              {{ schedule.activity_name }}
            </div>
            <div class="flex-1 h-8 bg-gray-100 rounded relative">
              <div
                :class="[
                  'h-full rounded',
                  schedule.status === 'COMPLETED' ? 'bg-green-500' :
                  schedule.status === 'IN_PROGRESS' ? 'bg-blue-500' :
                  schedule.status === 'DELAYED' ? 'bg-red-500' : 'bg-gray-400',
                ]"
                :style="{ width: `${schedule.progress_percentage}%` }"
              >
                <span class="absolute inset-0 flex items-center justify-center text-xs text-white font-medium">
                  {{ schedule.progress_percentage }}%
                </span>
              </div>
            </div>
            <div class="w-32 text-xs text-gray-500">
              {{ formatDate(schedule.start_date, { format: 'short' }) }}
            </div>
          </div>
        </div>
        <p v-if="schedules.length === 0" class="text-center text-gray-500 py-8">
          Tidak ada data untuk ditampilkan
        </p>
      </PageCard>
    </template>

    <!-- Modal -->
    <NModal v-model:show="showModal" preset="card" :title="editingSchedule ? 'Edit Jadwal' : 'Tambah Jadwal Baru'" style="width: 600px">
      <form @submit.prevent="submitForm" class="space-y-4">
        <FormInput v-model="form.activity_name" label="Nama Kegiatan" placeholder="Nama kegiatan" required />

        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal Mulai</label>
            <NDatePicker v-model:formatted-value="form.start_date" type="date" value-format="yyyy-MM-dd" clearable class="w-full" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal Selesai</label>
            <NDatePicker v-model:formatted-value="form.end_date" type="date" value-format="yyyy-MM-dd" clearable class="w-full" />
          </div>
        </div>

        <FormInputNumber v-model="form.planned_amount" label="Nilai Rencana (Rp)" :min="0" />

        <div class="grid grid-cols-2 gap-4">
          <FormSelect v-model="form.status" label="Status" :options="statusOptions" />
          <FormInputNumber v-model="form.progress_percentage" label="Progress (%)" :min="0" :max="100" />
        </div>

        <FormInput v-model="form.pic_name" label="PIC" placeholder="Nama PIC" />

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Catatan</label>
          <NInput v-model:value="form.notes" type="textarea" :rows="2" placeholder="Catatan tambahan" />
        </div>

        <div class="flex justify-end gap-3 pt-4">
          <NButton @click="showModal = false">Batal</NButton>
          <NButton type="primary" attr-type="submit" :loading="submitting">
            {{ submitting ? 'Menyimpan...' : 'Simpan' }}
          </NButton>
        </div>
      </form>
    </NModal>
  </div>
</template>
