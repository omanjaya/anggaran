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
const monthOptions: Array<{ value: number | null; label: string }> = [
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

// Sub activity options for dropdown
const subActivityOptions = computed(() =>
  subActivities.value.map(sa => ({
    label: `${sa.code} - ${sa.name}`,
    value: sa.id
  }))
)

// Flatten budget items with their details for display
const flattenedItems = computed(() => {
  const items: any[] = []

  // Sort by code
  const sorted = [...budgetItems.value].sort((a, b) => a.code.localeCompare(b.code))

  for (const item of sorted) {
    // Add the budget item row
    items.push({
      type: 'budget_item',
      ...item
    })

    // If it's a detail code with details, add [ # ], [ - ], and spesifikasi rows
    if (item.is_detail_code && item.details && item.details.length > 0) {
      // Add [ # ] group row
      items.push({
        type: 'group_hash',
        name: item.name,
        group_name: item.group_name,
        sumber_dana: item.sumber_dana,
        total_budget: item.total_budget
      })

      // Add [ - ] sub-group row
      items.push({
        type: 'group_dash',
        name: item.name,
        total_budget: item.total_budget
      })

      // Add spesifikasi rows
      for (const detail of item.details) {
        items.push({
          type: 'spesifikasi',
          ...detail
        })
      }
    }
  }

  return items
})

// Calculate totals from detail codes only
const totals = computed(() => {
  const detailItems = budgetItems.value.filter(item => item.is_detail_code)
  return {
    budget: detailItems.reduce((sum, item) => sum + Number(item.total_budget || 0), 0),
    planned: detailItems.reduce((sum, item) => sum + Number(item.planned || 0), 0),
    realized: detailItems.reduce((sum, item) => sum + Number(item.realized || 0), 0),
  }
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
      year: selectedYear.value,
      with_details: true
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

function getRowClass(item: any) {
  if (item.type === 'group_hash') return 'row-group-hash'
  if (item.type === 'group_dash') return 'row-group-dash'
  if (item.type === 'spesifikasi') return 'row-spesifikasi'

  const level = item.level || 1
  if (item.is_detail_code) return 'row-detail-code'
  if (level === 1) return 'row-level-1'
  if (level === 2) return 'row-level-2'
  if (level === 3) return 'row-level-3'
  if (level === 4) return 'row-level-4'
  if (level === 5) return 'row-level-5'
  return ''
}

function isParentLevel(item: any) {
  return item.type === 'budget_item' && !item.is_detail_code
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
      <p class="text-gray-500 mt-1">Format DPA Rincian Belanja SKPD</p>
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
            :options="monthOptions as any"
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

    <!-- Loading State -->
    <div v-if="loading" class="flex justify-center py-12">
      <NSpin size="large" />
    </div>

    <!-- Empty State -->
    <NCard v-else-if="!selectedSubActivityId">
      <NEmpty description="Pilih Sub Kegiatan untuk melihat laporan" />
    </NCard>

    <NCard v-else-if="budgetItems.length === 0">
      <NEmpty description="Tidak ada data untuk Sub Kegiatan ini" />
    </NCard>

    <!-- DPA Table - PDF Style -->
    <NCard v-else class="report-table-card">
      <template #header>
        <div class="flex items-center justify-between">
          <span class="font-bold">Rincian Belanja</span>
          <NTag type="info">{{ budgetItems.length }} item</NTag>
        </div>
      </template>

      <div class="report-table-wrapper">
        <table class="report-table">
          <thead>
            <tr>
              <th rowspan="2" class="col-code">Kode Rekening</th>
              <th rowspan="2" class="col-uraian">Uraian</th>
              <th colspan="4" class="col-header-group">Rincian Perhitungan</th>
              <th rowspan="2" class="col-jumlah">Jumlah<br>(Rp)</th>
            </tr>
            <tr>
              <th class="col-volume">Koefisien/<br>Volume</th>
              <th class="col-satuan">Satuan</th>
              <th class="col-harga">Harga</th>
              <th class="col-ppn">PPN</th>
            </tr>
          </thead>
          <tbody>
            <template v-for="(item, index) in flattenedItems" :key="index">
              <!-- Parent level rows (1-5): Merged cells -->
              <tr v-if="isParentLevel(item)" :class="getRowClass(item)">
                <td class="col-code">{{ item.code }}</td>
                <td colspan="5" class="col-uraian-merged">{{ item.name }}</td>
                <td class="col-jumlah">{{ formatCurrency(item.total_budget) }}</td>
              </tr>

              <!-- Detail code row (level 6) -->
              <tr v-else-if="item.type === 'budget_item' && item.is_detail_code" :class="getRowClass(item)">
                <td class="col-code">{{ item.code }}</td>
                <td colspan="5" class="col-uraian-merged">{{ item.name }}</td>
                <td class="col-jumlah">{{ formatCurrency(item.total_budget) }}</td>
              </tr>

              <!-- [ # ] Group row -->
              <tr v-else-if="item.type === 'group_hash'" :class="getRowClass(item)">
                <td class="col-code"></td>
                <td colspan="5" class="col-uraian-group">
                  <div>[ # ] {{ item.name }}</div>
                  <div v-if="item.sumber_dana" class="sumber-dana">Sumber Dana: {{ item.sumber_dana }}</div>
                </td>
                <td class="col-jumlah">{{ formatCurrency(item.total_budget) }}</td>
              </tr>

              <!-- [ - ] Sub-group row -->
              <tr v-else-if="item.type === 'group_dash'" :class="getRowClass(item)">
                <td class="col-code"></td>
                <td colspan="5" class="col-uraian-group">[ - ] {{ item.name }}</td>
                <td class="col-jumlah">{{ formatCurrency(item.total_budget) }}</td>
              </tr>

              <!-- Spesifikasi row - full columns -->
              <tr v-else-if="item.type === 'spesifikasi'" :class="getRowClass(item)">
                <td class="col-code"></td>
                <td class="col-uraian-spec">
                  <div>{{ item.description.split('\n')[0] }}</div>
                  <div v-if="item.description.includes('Spesifikasi:')" class="spesifikasi-detail">
                    Spesifikasi: {{ item.description.split('Spesifikasi:')[1]?.trim() || '' }}
                  </div>
                </td>
                <td class="col-volume">{{ item.volume }} {{ item.unit }}</td>
                <td class="col-satuan">{{ item.unit }}</td>
                <td class="col-harga">{{ formatCurrency(item.unit_price) }}</td>
                <td class="col-ppn">0%</td>
                <td class="col-jumlah">{{ formatCurrency(item.amount) }}</td>
              </tr>
            </template>
          </tbody>
          <tfoot>
            <tr>
              <td colspan="6" class="text-right font-bold">Jumlah Anggaran Sub Kegiatan</td>
              <td class="col-jumlah font-bold">{{ formatCurrency(totals.budget) }}</td>
            </tr>
          </tfoot>
        </table>
      </div>
    </NCard>
  </div>
</template>

<style scoped>
.report-table-wrapper {
  border: 1px solid #000;
  overflow-x: auto;
}

.report-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 12px;
  min-width: 900px;
}

.report-table thead {
  background: #f3f4f6;
}

.report-table th {
  padding: 8px 10px;
  text-align: center;
  font-weight: 600;
  border: 1px solid #000;
  vertical-align: middle;
}

.report-table td {
  padding: 6px 10px;
  border: 1px solid #000;
  vertical-align: top;
}

.report-table .col-code {
  width: 140px;
  font-family: 'Courier New', monospace;
  font-size: 11px;
  white-space: nowrap;
  text-align: left;
}

.report-table .col-uraian {
  min-width: 280px;
  text-align: left;
}

.report-table .col-uraian-merged {
  text-align: left;
  font-weight: 500;
}

.report-table .col-uraian-group {
  text-align: left;
  padding-left: 20px;
}

.report-table .col-uraian-spec {
  text-align: left;
  padding-left: 40px;
}

.report-table .col-volume {
  width: 90px;
  text-align: center;
}

.report-table .col-satuan {
  width: 70px;
  text-align: center;
}

.report-table .col-harga {
  width: 100px;
  text-align: right;
  font-family: 'Courier New', monospace;
  font-size: 11px;
}

.report-table .col-ppn {
  width: 50px;
  text-align: center;
}

.report-table .col-jumlah {
  width: 120px;
  text-align: right;
  font-family: 'Courier New', monospace;
  font-size: 11px;
  font-weight: 500;
}

.report-table .col-header-group {
  background: #e5e7eb;
}

.sumber-dana {
  font-size: 11px;
  color: #4b5563;
  margin-top: 2px;
}

.spesifikasi-detail {
  font-size: 11px;
  color: #4b5563;
  margin-top: 2px;
}

/* Row styling by level - matching PDF colors */
.row-level-1 {
  background: #fff;
}

.row-level-1 td {
  font-weight: 700;
}

.row-level-2 {
  background: #fff;
}

.row-level-2 td {
  font-weight: 600;
}

.row-level-3 {
  background: #fff;
}

.row-level-3 td {
  font-weight: 500;
}

.row-level-4 {
  background: #fff;
}

.row-level-5 {
  background: #fff;
}

.row-detail-code {
  background: #fff;
}

.row-group-hash {
  background: #f9fafb;
}

.row-group-dash {
  background: #f9fafb;
}

.row-spesifikasi {
  background: #fff;
}

.report-table tbody tr:hover {
  background: #f5f5f5;
}

.report-table tfoot {
  background: #f3f4f6;
}

.report-table tfoot td {
  padding: 10px;
  border-top: 2px solid #000;
  font-weight: 600;
}
</style>
