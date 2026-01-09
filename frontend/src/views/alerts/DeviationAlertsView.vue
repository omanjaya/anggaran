<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { NButton, NSelect, NTag, NSpace, NCard, NModal, NInput } from 'naive-ui'
import { PageHeader, PageCard, StatCard, LoadingSpinner } from '@/components/ui'
import { FormSelect } from '@/components/ui'
import { useFormat, useMessage } from '@/composables'
import api from '@/services/api'

interface DeviationAlert {
  id: number
  budget_item_id: number
  month: number
  year: number
  alert_type: 'UNDER_REALIZATION' | 'OVER_REALIZATION' | 'NOT_REALIZED' | 'DEADLINE_APPROACHING'
  severity: 'CRITICAL' | 'HIGH' | 'MEDIUM' | 'LOW'
  severity_label: string
  planned_amount: number
  realized_amount: number
  deviation_percentage: number
  message: string
  status: 'ACTIVE' | 'ACKNOWLEDGED' | 'RESOLVED' | 'DISMISSED'
  acknowledged_at: string | null
  resolved_at: string | null
  created_at: string
  budget_item?: {
    name: string
    code: string
    sub_activity?: { name: string }
  }
}

interface DashboardStats {
  total_active: number
  critical_count: number
  high_count: number
  medium_count: number
  low_count: number
  by_type: Record<string, number>
  recent_alerts?: DeviationAlert[]
}

const { formatCurrency, formatDate } = useFormat()
const message = useMessage()

const loading = ref(true)
const alerts = ref<DeviationAlert[]>([])
const dashboardStats = ref<DashboardStats | null>(null)
const selectedStatus = ref<string | undefined>('ACTIVE')
const selectedSeverity = ref<string | undefined>(undefined)
const selectedYear = ref(new Date().getFullYear())
const checking = ref(false)
const showResolveModal = ref(false)
const resolvingAlert = ref<DeviationAlert | null>(null)
const resolutionNotes = ref('')

const severityTypes: Record<string, 'error' | 'warning' | 'info' | 'default'> = {
  CRITICAL: 'error',
  HIGH: 'warning',
  MEDIUM: 'info',
  LOW: 'default',
}

const statusTypes: Record<string, 'error' | 'warning' | 'success' | 'default'> = {
  ACTIVE: 'error',
  ACKNOWLEDGED: 'warning',
  RESOLVED: 'success',
  DISMISSED: 'default',
}

const typeLabels: Record<string, string> = {
  UNDER_REALIZATION: 'Realisasi Kurang',
  OVER_REALIZATION: 'Realisasi Lebih',
  NOT_REALIZED: 'Belum Direalisasi',
  DEADLINE_APPROACHING: 'Deadline Mendekat',
}

const monthNames = [
  '', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
  'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
]

const years = computed(() => {
  const currentYear = new Date().getFullYear()
  return Array.from({ length: 5 }, (_, i) => ({ label: String(currentYear - 2 + i), value: currentYear - 2 + i }))
})

const statusOptions = [
  { label: 'Semua Status', value: undefined as string | undefined },
  { label: 'Aktif', value: 'ACTIVE' },
  { label: 'Diakui', value: 'ACKNOWLEDGED' },
  { label: 'Selesai', value: 'RESOLVED' },
  { label: 'Diabaikan', value: 'DISMISSED' },
]

const severityOptions = [
  { label: 'Semua Tingkat', value: undefined as string | undefined },
  { label: 'Kritis', value: 'CRITICAL' },
  { label: 'Tinggi', value: 'HIGH' },
  { label: 'Sedang', value: 'MEDIUM' },
  { label: 'Rendah', value: 'LOW' },
]

const validAlerts = computed(() => alerts.value.filter((a): a is DeviationAlert => a != null))

async function fetchAlerts() {
  loading.value = true
  try {
    const params: Record<string, any> = { year: selectedYear.value }
    if (selectedStatus.value) params.status = selectedStatus.value
    if (selectedSeverity.value) params.severity = selectedSeverity.value

    const response = await api.get('/deviation-alerts', { params })
    const data = response.data.data
    alerts.value = Array.isArray(data) ? data : (data?.data || [])
  } catch (err) {
    console.error('Failed to fetch alerts:', err)
    alerts.value = []
  } finally {
    loading.value = false
  }
}

async function fetchDashboard() {
  try {
    const response = await api.get('/deviation-alerts/dashboard')
    const data = response.data.data
    dashboardStats.value = {
      total_active: data?.total_active ?? 0,
      critical_count: data?.critical ?? 0,
      high_count: data?.high ?? 0,
      medium_count: data?.by_severity?.MEDIUM ?? 0,
      low_count: data?.by_severity?.LOW ?? 0,
      by_type: data?.by_type ?? {},
      recent_alerts: data?.recent_alerts ?? [],
    }
  } catch (err) {
    console.error('Failed to fetch dashboard:', err)
    dashboardStats.value = null
  }
}

async function checkAlerts() {
  checking.value = true
  try {
    await api.post('/deviation-alerts/check', { year: selectedYear.value })
    await Promise.all([fetchAlerts(), fetchDashboard()])
    message.success('Pengecekan deviasi selesai')
  } catch (err) {
    console.error('Failed to check alerts:', err)
    message.error('Gagal menjalankan pengecekan')
  } finally {
    checking.value = false
  }
}

async function acknowledgeAlert(id: number) {
  try {
    await api.post(`/deviation-alerts/${id}/acknowledge`, {
      notes: 'Acknowledged via dashboard',
    })
    await Promise.all([fetchAlerts(), fetchDashboard()])
    message.success('Peringatan berhasil diakui')
  } catch (err) {
    console.error('Failed to acknowledge alert:', err)
    message.error('Gagal mengakui peringatan')
  }
}

function openResolveModal(alert: DeviationAlert) {
  resolvingAlert.value = alert
  resolutionNotes.value = ''
  showResolveModal.value = true
}

async function resolveAlert() {
  if (!resolvingAlert.value || !resolutionNotes.value) return

  try {
    await api.post(`/deviation-alerts/${resolvingAlert.value.id}/resolve`, {
      resolution_notes: resolutionNotes.value,
    })
    showResolveModal.value = false
    await Promise.all([fetchAlerts(), fetchDashboard()])
    message.success('Peringatan berhasil diselesaikan')
  } catch (err) {
    console.error('Failed to resolve alert:', err)
    message.error('Gagal menyelesaikan peringatan')
  }
}

async function dismissAlert(id: number) {
  message.confirm({
    title: 'Konfirmasi',
    content: 'Apakah Anda yakin ingin mengabaikan peringatan ini?',
    onPositiveClick: async () => {
      try {
        await api.post(`/deviation-alerts/${id}/dismiss`)
        await Promise.all([fetchAlerts(), fetchDashboard()])
        message.success('Peringatan berhasil diabaikan')
      } catch (err) {
        console.error('Failed to dismiss alert:', err)
        message.error('Gagal mengabaikan peringatan')
      }
    },
  })
}

onMounted(() => {
  Promise.all([fetchAlerts(), fetchDashboard()])
})
</script>

<template>
  <div>
    <PageHeader title="Peringatan Deviasi" subtitle="Monitor dan kelola peringatan deviasi anggaran">
      <template #actions>
        <NSpace>
          <NSelect
            v-model:value="selectedYear"
            :options="years"
            style="width: 120px"
            @update:value="fetchAlerts"
          />
          <NButton type="warning" :loading="checking" @click="checkAlerts">
            {{ checking ? 'Memeriksa...' : 'Cek Deviasi' }}
          </NButton>
        </NSpace>
      </template>
    </PageHeader>

    <!-- Dashboard Stats -->
    <div v-if="dashboardStats" class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-6">
      <StatCard label="Total Aktif" :value="dashboardStats.total_active" />
      <StatCard label="Kritis" :value="dashboardStats.critical_count" variant="error" />
      <StatCard label="Tinggi" :value="dashboardStats.high_count" variant="warning" />
      <StatCard label="Sedang" :value="dashboardStats.medium_count" variant="primary" />
      <StatCard label="Rendah" :value="dashboardStats.low_count" variant="default" />
    </div>

    <!-- Filters -->
    <PageCard class="mb-6">
      <NSpace :size="16" wrap>
        <FormSelect
          v-model="selectedStatus"
          label="Status"
          placeholder="Semua Status"
          :options="statusOptions"
          style="min-width: 150px"
          @update:model-value="fetchAlerts"
        />
        <FormSelect
          v-model="selectedSeverity"
          label="Tingkat"
          placeholder="Semua Tingkat"
          :options="severityOptions"
          style="min-width: 150px"
          @update:model-value="fetchAlerts"
        />
      </NSpace>
    </PageCard>

    <!-- Alerts List -->
    <LoadingSpinner v-if="loading" />

    <div v-else class="space-y-4">
      <NCard
        v-for="alert in validAlerts"
        :key="alert.id"
        :bordered="true"
        size="small"
      >
        <div class="flex justify-between items-start">
          <div class="flex-1">
            <NSpace :size="8" class="mb-2">
              <NTag :type="severityTypes[alert.severity] || 'default'" size="small">
                {{ alert.severity_label || alert.severity || '-' }}
              </NTag>
              <NTag :type="statusTypes[alert.status] || 'default'" size="small">
                {{ alert.status || '-' }}
              </NTag>
              <NTag type="info" size="small">
                {{ typeLabels[alert.alert_type] || alert.alert_type || '-' }}
              </NTag>
              <span class="text-sm text-gray-500">
                {{ monthNames[alert.month] || '' }} {{ alert.year || '' }}
              </span>
            </NSpace>
            <p class="text-gray-900 font-medium mb-1">{{ alert.message || '-' }}</p>
            <p class="text-sm text-gray-600">
              Item: {{ alert.budget_item?.name || '-' }}
              <span v-if="alert.budget_item?.sub_activity">
                ({{ alert.budget_item.sub_activity.name }})
              </span>
            </p>
            <div class="mt-2 flex items-center gap-6 text-sm text-gray-600">
              <span>Rencana: {{ formatCurrency(alert.planned_amount ?? 0) }}</span>
              <span>Realisasi: {{ formatCurrency(alert.realized_amount ?? 0) }}</span>
              <span
                v-if="alert.deviation_percentage != null && alert.deviation_percentage !== 0"
                :class="alert.deviation_percentage > 0 ? 'text-red-600' : 'text-orange-600'"
              >
                Deviasi: {{ alert.deviation_percentage > 0 ? '+' : '' }}{{ (alert.deviation_percentage ?? 0).toFixed(1) }}%
              </span>
            </div>
            <p v-if="alert.created_at" class="text-xs text-gray-400 mt-2">
              Dibuat: {{ formatDate(alert.created_at, { format: 'datetime' }) }}
              <span v-if="alert.acknowledged_at"> | Diakui: {{ formatDate(alert.acknowledged_at, { format: 'datetime' }) }}</span>
              <span v-if="alert.resolved_at"> | Selesai: {{ formatDate(alert.resolved_at, { format: 'datetime' }) }}</span>
            </p>
          </div>
          <NSpace v-if="alert.status === 'ACTIVE' || alert.status === 'ACKNOWLEDGED'" :size="8">
            <NButton
              v-if="alert.status === 'ACTIVE'"
              size="small"
              type="warning"
              @click="acknowledgeAlert(alert.id)"
            >
              Akui
            </NButton>
            <NButton size="small" type="success" @click="openResolveModal(alert)">
              Selesaikan
            </NButton>
            <NButton size="small" @click="dismissAlert(alert.id)">
              Abaikan
            </NButton>
          </NSpace>
        </div>
      </NCard>

      <PageCard v-if="validAlerts.length === 0">
        <div class="text-center py-8 text-gray-500">
          Tidak ada peringatan deviasi yang ditemukan
        </div>
      </PageCard>
    </div>

    <!-- Resolve Modal -->
    <NModal
      v-model:show="showResolveModal"
      preset="card"
      title="Selesaikan Peringatan"
      style="width: 500px"
    >
      <div class="space-y-4">
        <div v-if="resolvingAlert" class="p-3 bg-gray-50 rounded-lg text-sm">
          <p class="font-medium">{{ resolvingAlert.message }}</p>
          <p class="text-gray-600">{{ resolvingAlert.budget_item?.name }}</p>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Catatan Resolusi</label>
          <NInput
            v-model:value="resolutionNotes"
            type="textarea"
            placeholder="Masukkan catatan resolusi..."
            :rows="3"
          />
        </div>
        <div class="flex justify-end gap-3">
          <NButton @click="showResolveModal = false">Batal</NButton>
          <NButton type="primary" :disabled="!resolutionNotes" @click="resolveAlert">
            Selesaikan
          </NButton>
        </div>
      </div>
    </NModal>
  </div>
</template>
