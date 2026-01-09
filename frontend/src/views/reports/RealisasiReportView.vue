<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import {
  NCard, NSelect, NSpin, NEmpty, NButton, NIcon, NTag, NSpace
} from 'naive-ui'
import { DocumentOutline, DocumentTextOutline } from '@vicons/ionicons5'
import { useFormat } from '@/composables'
import api from '@/services/api'

interface BudgetItemDetail {
  id: number
  description: string
  volume: number
  unit: string
  unit_price: number
  amount: number
}

interface BudgetItem {
  id: number
  sub_activity_id: number
  code: string
  name: string
  group_name: string | null
  sumber_dana: string | null
  level: number
  is_detail_code: boolean
  unit: string
  volume: number
  unit_price: number
  total_budget: number
  is_active: boolean
  details: BudgetItemDetail[]
  // Realisasi fields
  planned: number
  realized: number
  physical_target: number
  physical_progress: number
}

interface SubActivity {
  id: number
  code: string
  name: string
  activity_id: number
  nomor_dpa: string
  sumber_pendanaan: string
  lokasi: string
  keluaran: string
  waktu_pelaksanaan: string
}

const { formatCurrency } = useFormat()

const loading = ref(false)
const exporting = ref(false)
const subActivities = ref<SubActivity[]>([])
const budgetItems = ref<BudgetItem[]>([])
const selectedSubActivityId = ref<number | null>(null)
const selectedSubActivity = ref<SubActivity | null>(null)
const selectedYear = ref(new Date().getFullYear())
const selectedMonth = ref<number | null>(null)

// Year options
const yearOptions = computed(() => {
  const currentYear = new Date().getFullYear()
  return Array.from({ length: 5 }, (_, i) => ({
    label: String(currentYear - 2 + i),
    value: currentYear - 2 + i,
  }))
})

// Month options
const monthOptions = [
  { value: null, label: 'Semua Bulan' },
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

// Sorted budget items for display
const sortedBudgetItems = computed(() => {
  return [...budgetItems.value].sort((a, b) => a.code.localeCompare(b.code))
})

// Sub activity options for dropdown
const subActivityOptions = computed(() =>
  subActivities.value.map(sa => ({
    label: `${sa.code} - ${sa.name}`,
    value: sa.id
  }))
)

// Calculate totals
const totals = computed(() => {
  const detailItems = budgetItems.value.filter(item => item.is_detail_code)
  return {
    budget: detailItems.reduce((sum, item) => sum + Number(item.total_budget || 0), 0),
    planned: detailItems.reduce((sum, item) => sum + Number(item.planned || 0), 0),
    realized: detailItems.reduce((sum, item) => sum + Number(item.realized || 0), 0),
  }
})

const overallPercentage = computed(() => {
  if (totals.value.budget === 0) return 0
  return (totals.value.realized / totals.value.budget) * 100
})

async function fetchSubActivities() {
  try {
    const response = await api.get('/sub-activities')
    subActivities.value = response.data.data
  } catch (err) {
    console.error('Failed to fetch sub-activities:', err)
  }
}

async function fetchBudgetItems() {
  if (!selectedSubActivityId.value) {
    budgetItems.value = []
    return
  }

  loading.value = true
  try {
    const params: Record<string, any> = {
      sub_activity_id: selectedSubActivityId.value,
      per_page: 500,
      year: selectedYear.value
    }
    if (selectedMonth.value) {
      params.month = selectedMonth.value
    }

    const response = await api.get('/budget-items', { params })
    budgetItems.value = response.data.data
  } catch (err) {
    console.error('Failed to fetch budget items:', err)
  } finally {
    loading.value = false
  }
}

function onSubActivityChange(value: number | null) {
  selectedSubActivityId.value = value
  selectedSubActivity.value = subActivities.value.find(sa => sa.id === value) || null
  fetchBudgetItems()
}

function getIndentStyle(item: BudgetItem) {
  const level = item.level || 1
  return { paddingLeft: `${(level - 1) * 16}px` }
}

function getRowClass(item: BudgetItem) {
  const level = item.level || 1
  if (item.is_detail_code) return 'row-detail'
  if (level === 1) return 'row-level-1'
  if (level === 2) return 'row-level-2'
  if (level === 3) return 'row-level-3'
  if (level === 4) return 'row-level-4'
  if (level === 5) return 'row-level-5'
  return ''
}

function isParentLevel(item: BudgetItem) {
  return !item.is_detail_code
}

function getRealizationPercentage(item: BudgetItem) {
  if (!item.total_budget || item.total_budget === 0) return 0
  return (Number(item.realized || 0) / Number(item.total_budget)) * 100
}

async function exportPdf() {
  if (!selectedSubActivityId.value) return
  exporting.value = true
  try {
    const params: Record<string, any> = {
      sub_activity_id: selectedSubActivityId.value,
      year: selectedYear.value
    }
    if (selectedMonth.value) params.month = selectedMonth.value

    const response = await api.get('/reports/export/realisasi/pdf', {
      params,
      responseType: 'blob',
    })

    const url = window.URL.createObjectURL(new Blob([response.data]))
    const link = document.createElement('a')
    link.href = url
    link.setAttribute('download', `laporan_realisasi_${selectedYear.value}.pdf`)
    document.body.appendChild(link)
    link.click()
    link.remove()
  } catch (err) {
    console.error('Failed to export PDF:', err)
  } finally {
    exporting.value = false
  }
}

async function exportExcel() {
  if (!selectedSubActivityId.value) return
  exporting.value = true
  try {
    const params: Record<string, any> = {
      sub_activity_id: selectedSubActivityId.value,
      year: selectedYear.value
    }
    if (selectedMonth.value) params.month = selectedMonth.value

    const response = await api.get('/reports/export/realisasi/excel', {
      params,
      responseType: 'blob',
    })

    const url = window.URL.createObjectURL(new Blob([response.data]))
    const link = document.createElement('a')
    link.href = url
    link.setAttribute('download', `laporan_realisasi_${selectedYear.value}.xlsx`)
    document.body.appendChild(link)
    link.click()
    link.remove()
  } catch (err) {
    console.error('Failed to export Excel:', err)
  } finally {
    exporting.value = false
  }
}

watch([selectedYear, selectedMonth], () => {
  if (selectedSubActivityId.value) {
    fetchBudgetItems()
  }
})

onMounted(() => {
  fetchSubActivities()
})
</script>

<template>
  <div class="realisasi-report-view">
    <!-- Header -->
    <div class="mb-6">
      <h1 class="text-2xl font-bold text-gray-800">Laporan Realisasi</h1>
      <p class="text-gray-500 mt-1">Format Laporan Realisasi Fisik dan Keuangan</p>
    </div>

    <!-- Filter Card -->
    <NCard class="mb-6">
      <div class="flex flex-wrap items-end gap-4">
        <div class="flex-1 min-w-[300px]">
          <label class="block text-sm font-medium text-gray-700 mb-1">Pilih Sub Kegiatan</label>
          <NSelect
            v-model:value="selectedSubActivityId"
            :options="subActivityOptions"
            placeholder="Pilih Sub Kegiatan"
            filterable
            clearable
            size="large"
            @update:value="onSubActivityChange"
          />
        </div>
        <div class="w-32">
          <label class="block text-sm font-medium text-gray-700 mb-1">Tahun</label>
          <NSelect
            v-model:value="selectedYear"
            :options="yearOptions"
            size="large"
          />
        </div>
        <div class="w-40">
          <label class="block text-sm font-medium text-gray-700 mb-1">Bulan</label>
          <NSelect
            v-model:value="selectedMonth"
            :options="monthOptions"
            size="large"
          />
        </div>
        <NSpace>
          <NButton type="error" :loading="exporting" :disabled="!selectedSubActivityId" @click="exportPdf">
            <template #icon>
              <NIcon><DocumentOutline /></NIcon>
            </template>
            PDF
          </NButton>
          <NButton type="success" :loading="exporting" :disabled="!selectedSubActivityId" @click="exportExcel">
            <template #icon>
              <NIcon><DocumentTextOutline /></NIcon>
            </template>
            Excel
          </NButton>
        </NSpace>
      </div>

      <!-- Sub Activity Info -->
      <div v-if="selectedSubActivity" class="mt-4 p-4 bg-gray-50 rounded-lg">
        <div class="grid grid-cols-2 gap-4 text-sm">
          <div>
            <span class="text-gray-500">Nomor DPA:</span>
            <span class="ml-2 font-medium">{{ selectedSubActivity.nomor_dpa || '-' }}</span>
          </div>
          <div>
            <span class="text-gray-500">Sumber Pendanaan:</span>
            <span class="ml-2 font-medium">{{ selectedSubActivity.sumber_pendanaan || '-' }}</span>
          </div>
          <div>
            <span class="text-gray-500">Lokasi:</span>
            <span class="ml-2 font-medium">{{ selectedSubActivity.lokasi || '-' }}</span>
          </div>
          <div>
            <span class="text-gray-500">Waktu Pelaksanaan:</span>
            <span class="ml-2 font-medium">{{ selectedSubActivity.waktu_pelaksanaan || '-' }}</span>
          </div>
        </div>
      </div>
    </NCard>

    <!-- Summary Cards -->
    <div v-if="selectedSubActivityId && budgetItems.length > 0" class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
      <div class="bg-white p-4 rounded-lg border shadow-sm">
        <div class="text-sm text-gray-500">Total Anggaran</div>
        <div class="text-xl font-bold text-gray-800">{{ formatCurrency(totals.budget) }}</div>
      </div>
      <div class="bg-blue-50 p-4 rounded-lg border border-blue-200 shadow-sm">
        <div class="text-sm text-blue-600">Total Rencana</div>
        <div class="text-xl font-bold text-blue-700">{{ formatCurrency(totals.planned) }}</div>
      </div>
      <div class="bg-green-50 p-4 rounded-lg border border-green-200 shadow-sm">
        <div class="text-sm text-green-600">Total Realisasi</div>
        <div class="text-xl font-bold text-green-700">{{ formatCurrency(totals.realized) }}</div>
      </div>
      <div class="bg-amber-50 p-4 rounded-lg border border-amber-200 shadow-sm">
        <div class="text-sm text-amber-600">Persentase Realisasi</div>
        <div class="text-xl font-bold text-amber-700">{{ overallPercentage.toFixed(1) }}%</div>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="flex justify-center py-12">
      <NSpin size="large" />
    </div>

    <!-- Empty State -->
    <NCard v-else-if="!selectedSubActivityId">
      <NEmpty description="Pilih Sub Kegiatan untuk melihat laporan realisasi" />
    </NCard>

    <NCard v-else-if="budgetItems.length === 0">
      <NEmpty description="Tidak ada data untuk Sub Kegiatan ini" />
    </NCard>

    <!-- Budget Items Table - PDF Style -->
    <NCard v-else title="Rincian Belanja" class="report-table-card">
      <template #header-extra>
        <NTag type="info">{{ budgetItems.length }} item</NTag>
      </template>

      <div class="report-table-wrapper">
        <table class="report-table">
          <thead>
            <tr>
              <th rowspan="2" class="col-code">Kode Rekening</th>
              <th rowspan="2" class="col-name">Uraian</th>
              <th rowspan="2" class="col-amount">Anggaran (Rp)</th>
              <th colspan="2" class="col-header-group">Progres Fisik</th>
              <th colspan="2" class="col-header-group">Progres Keuangan</th>
              <th rowspan="2" class="col-amount">Saldo (Rp)</th>
            </tr>
            <tr>
              <th class="col-percent">Target</th>
              <th class="col-percent">Real</th>
              <th class="col-amount-sm">Real (Rp)</th>
              <th class="col-percent">%</th>
            </tr>
          </thead>
          <tbody>
            <template v-for="item in sortedBudgetItems" :key="item.id">
              <!-- Parent level row (levels 1-5) - merged style -->
              <tr v-if="isParentLevel(item)" :class="getRowClass(item)">
                <td class="col-code">
                  <div :style="getIndentStyle(item)">{{ item.code }}</div>
                </td>
                <td colspan="7" class="col-name-merged">
                  <div class="item-name">{{ item.name }}</div>
                </td>
              </tr>

              <!-- Detail level row (level 6) - full columns -->
              <tr v-else :class="getRowClass(item)">
                <td class="col-code">
                  <div :style="getIndentStyle(item)">{{ item.code }}</div>
                </td>
                <td class="col-name">
                  <div class="item-name">{{ item.name }}</div>
                  <div v-if="item.group_name" class="item-meta">[ # ] {{ item.group_name }}</div>
                  <div v-if="item.sumber_dana" class="item-meta">Sumber Dana: {{ item.sumber_dana }}</div>
                </td>
                <td class="col-amount">{{ formatCurrency(item.total_budget) }}</td>
                <td class="col-percent">{{ (item.physical_target || 0).toFixed(1) }}%</td>
                <td class="col-percent" :class="(item.physical_progress || 0) >= (item.physical_target || 0) ? 'text-success' : 'text-warning'">
                  {{ (item.physical_progress || 0).toFixed(1) }}%
                </td>
                <td class="col-amount-sm">{{ formatCurrency(item.realized || 0) }}</td>
                <td class="col-percent" :class="getRealizationPercentage(item) >= 80 ? 'text-success' : 'text-warning'">
                  {{ getRealizationPercentage(item).toFixed(1) }}%
                </td>
                <td class="col-amount">{{ formatCurrency((item.total_budget || 0) - (item.realized || 0)) }}</td>
              </tr>
            </template>
          </tbody>
          <tfoot>
            <tr>
              <td colspan="2" class="text-center font-bold">TOTAL</td>
              <td class="col-amount font-bold">{{ formatCurrency(totals.budget) }}</td>
              <td colspan="2"></td>
              <td class="col-amount-sm font-bold">{{ formatCurrency(totals.realized) }}</td>
              <td class="col-percent font-bold">{{ overallPercentage.toFixed(1) }}%</td>
              <td class="col-amount font-bold">{{ formatCurrency(totals.budget - totals.realized) }}</td>
            </tr>
          </tfoot>
        </table>
      </div>
    </NCard>
  </div>
</template>

<style scoped>
.report-table-wrapper {
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  overflow-x: auto;
}

.report-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 13px;
  min-width: 900px;
}

.report-table thead {
  background: #1e3a5f;
  color: white;
}

.report-table th {
  padding: 10px 12px;
  text-align: center;
  font-weight: 600;
  border: 1px solid rgba(255,255,255,0.2);
  white-space: nowrap;
}

.report-table td {
  padding: 8px 12px;
  border: 1px solid #e5e7eb;
  vertical-align: top;
}

.report-table .col-code {
  width: 160px;
  font-family: 'Monaco', 'Menlo', monospace;
  font-size: 12px;
  white-space: nowrap;
  text-align: left;
}

.report-table .col-name {
  min-width: 250px;
  text-align: left;
}

.report-table .col-name-merged {
  text-align: left;
  background: inherit;
}

.report-table .col-amount {
  width: 130px;
  text-align: right;
  font-family: 'Monaco', 'Menlo', monospace;
  font-size: 12px;
}

.report-table .col-amount-sm {
  width: 110px;
  text-align: right;
  font-family: 'Monaco', 'Menlo', monospace;
  font-size: 12px;
}

.report-table .col-percent {
  width: 60px;
  text-align: center;
}

.report-table .col-header-group {
  background: #2d4a6f;
}

.item-name {
  font-weight: 500;
}

.item-meta {
  font-size: 11px;
  color: #6b7280;
  margin-top: 2px;
}

/* Row styling by level */
.row-level-1 {
  background: #1e3a5f;
  color: white;
}

.row-level-1 .item-name {
  font-weight: 700;
}

.row-level-1 td {
  border-color: rgba(255,255,255,0.2);
}

.row-level-2 {
  background: #2d4a6f;
  color: white;
}

.row-level-2 .item-name {
  font-weight: 600;
}

.row-level-2 td {
  border-color: rgba(255,255,255,0.2);
}

.row-level-3 {
  background: #dbeafe;
}

.row-level-3 .item-name {
  font-weight: 600;
  color: #1e40af;
}

.row-level-4 {
  background: #eff6ff;
}

.row-level-4 .item-name {
  font-weight: 500;
  color: #1d4ed8;
}

.row-level-5 {
  background: #f8fafc;
}

.row-level-5 .item-name {
  color: #374151;
}

.row-detail {
  background: #ffffff;
}

.row-detail .item-name {
  color: #166534;
}

.row-detail:hover {
  background: #f0fdf4;
}

.text-success {
  color: #16a34a;
  font-weight: 600;
}

.text-warning {
  color: #d97706;
  font-weight: 600;
}

.report-table tfoot {
  background: #f3f4f6;
}

.report-table tfoot td {
  padding: 12px;
  border-top: 2px solid #1e3a5f;
}
</style>
