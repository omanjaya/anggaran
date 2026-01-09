<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import {
  NCard, NSelect, NSpin, NEmpty, NButton, NIcon, NInputNumber, NSpace, NTag, NTooltip, NProgress
} from 'naive-ui'
import { SaveOutline, RefreshOutline, CloseCircle } from '@vicons/ionicons5'
import { useFormat, useMessage } from '@/composables'
import api from '@/services/api'

interface BudgetItemDetail {
  id: number
  description: string
  volume: number
  unit: string
  unit_price: number
  amount: number
}

interface MonthlyPlanData {
  id?: number
  planned_volume: number
  planned_amount: number
}

interface RealizationData {
  id?: number
  realized_volume: number
  realized_amount: number
  status?: string
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
  // Monthly plans indexed by month (1-12)
  monthly_plans: Record<number, MonthlyPlanData>
  // Monthly realizations indexed by month (1-12)
  monthly_realizations: Record<number, RealizationData>
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
const message = useMessage()

const loading = ref(false)
const saving = ref(false)
const subActivities = ref<SubActivity[]>([])
const budgetItems = ref<BudgetItem[]>([])
const selectedSubActivityId = ref<number | null>(null)
const selectedSubActivity = ref<SubActivity | null>(null)
const selectedYear = ref(new Date().getFullYear())

// Track changes for saving
const changedItems = ref<Set<number>>(new Set())

// Months
const months = [
  { value: 1, label: 'Jan', fullLabel: 'Januari' },
  { value: 2, label: 'Feb', fullLabel: 'Februari' },
  { value: 3, label: 'Mar', fullLabel: 'Maret' },
  { value: 4, label: 'Apr', fullLabel: 'April' },
  { value: 5, label: 'Mei', fullLabel: 'Mei' },
  { value: 6, label: 'Jun', fullLabel: 'Juni' },
  { value: 7, label: 'Jul', fullLabel: 'Juli' },
  { value: 8, label: 'Ags', fullLabel: 'Agustus' },
  { value: 9, label: 'Sep', fullLabel: 'September' },
  { value: 10, label: 'Okt', fullLabel: 'Oktober' },
  { value: 11, label: 'Nov', fullLabel: 'November' },
  { value: 12, label: 'Des', fullLabel: 'Desember' },
]

// Year options
const yearOptions = computed(() => {
  const currentYear = new Date().getFullYear()
  return Array.from({ length: 5 }, (_, i) => ({
    label: String(currentYear - 2 + i),
    value: currentYear - 2 + i,
  }))
})

// Sub activity options for dropdown
const subActivityOptions = computed(() =>
  subActivities.value.map(sa => ({
    label: `${sa.code} - ${sa.name}`,
    value: sa.id
  }))
)

// Filter only detail code items for input (items with is_detail_code=true)
const editableItems = computed(() => {
  return budgetItems.value
    .filter(item => item.is_detail_code)
    .sort((a, b) => a.code.localeCompare(b.code))
})

// ============ PLANNING FUNCTIONS (READ-ONLY) ============

// Get total planned amount for an item (all months)
function getTotalPlannedAmount(item: BudgetItem): number {
  let total = 0
  for (let m = 1; m <= 12; m++) {
    const plan = item.monthly_plans?.[m]
    if (plan) {
      total += Number(plan.planned_amount) || 0
    }
  }
  return total
}

// Get YTD planned values up to a given month
function getYtdPlannedValues(item: BudgetItem, upToMonth: number): { volume: number; amount: number } {
  let totalVolume = 0
  let totalAmount = 0

  for (let m = 1; m <= upToMonth; m++) {
    const plan = item.monthly_plans?.[m]
    if (plan) {
      totalVolume += Number(plan.planned_volume) || 0
      totalAmount += Number(plan.planned_amount) || 0
    }
  }

  return { volume: totalVolume, amount: totalAmount }
}

// ============ REALIZATION FUNCTIONS ============

// Get total realized volume for an item (all months)
function getTotalRealizedVolume(item: BudgetItem): number {
  let total = 0
  for (let m = 1; m <= 12; m++) {
    const real = item.monthly_realizations?.[m]
    if (real) {
      total += Number(real.realized_volume) || 0
    }
  }
  return total
}

// Get total realized amount for an item (all months)
function getTotalRealizedAmount(item: BudgetItem): number {
  let total = 0
  for (let m = 1; m <= 12; m++) {
    const real = item.monthly_realizations?.[m]
    if (real) {
      total += Number(real.realized_amount) || 0
    }
  }
  return total
}

// Get realized volume up to (but not including) a specific month
function getRealizedVolumeBeforeMonth(item: BudgetItem, month: number): number {
  let total = 0
  for (let m = 1; m < month; m++) {
    const real = item.monthly_realizations?.[m]
    if (real) {
      total += Number(real.realized_volume) || 0
    }
  }
  return total
}

// Get realized amount up to (but not including) a specific month
function getRealizedAmountBeforeMonth(item: BudgetItem, month: number): number {
  let total = 0
  for (let m = 1; m < month; m++) {
    const real = item.monthly_realizations?.[m]
    if (real) {
      total += Number(real.realized_amount) || 0
    }
  }
  return total
}

// Get YTD realized values up to a given month
function getYtdRealizedValues(item: BudgetItem, upToMonth: number): { volume: number; amount: number } {
  let totalVolume = 0
  let totalAmount = 0

  for (let m = 1; m <= upToMonth; m++) {
    const real = item.monthly_realizations?.[m]
    if (real) {
      totalVolume += Number(real.realized_volume) || 0
      totalAmount += Number(real.realized_amount) || 0
    }
  }

  return { volume: totalVolume, amount: totalAmount }
}

// Get remaining volume (DPA - realized)
function getRemainingVolume(item: BudgetItem): number {
  const dpaVolume = Number(item.volume) || 0
  const realizedVolume = getTotalRealizedVolume(item)
  return Math.max(0, dpaVolume - realizedVolume)
}

// Get remaining budget (DPA - realized)
function getRemainingBudget(item: BudgetItem): number {
  const dpaBudget = Number(item.total_budget) || 0
  const realizedAmount = getTotalRealizedAmount(item)
  return Math.max(0, dpaBudget - realizedAmount)
}

// Check if realization input should be disabled for a month (volume exhausted)
function isVolumeExhausted(item: BudgetItem, month: number): boolean {
  const dpaVolume = Number(item.volume) || 0
  const realizedBefore = getRealizedVolumeBeforeMonth(item, month)
  const currentValue = item.monthly_realizations?.[month]?.realized_volume || 0

  return realizedBefore >= dpaVolume && currentValue === 0
}

// Check if realization input should be disabled for a month (budget exhausted)
function isBudgetExhausted(item: BudgetItem, month: number): boolean {
  const dpaBudget = Number(item.total_budget) || 0
  const realizedBefore = getRealizedAmountBeforeMonth(item, month)
  const currentValue = item.monthly_realizations?.[month]?.realized_amount || 0

  return realizedBefore >= dpaBudget && currentValue === 0
}

// Check if cell should be disabled
function isCellDisabled(item: BudgetItem, month: number): boolean {
  // Also check if there's no plan for this month
  const plan = item.monthly_plans?.[month]
  if (!plan || (plan.planned_volume === 0 && plan.planned_amount === 0)) {
    return true // Can't realize if no plan
  }
  return isVolumeExhausted(item, month) || isBudgetExhausted(item, month)
}

// Get disabled reason
function getDisabledReason(item: BudgetItem, month: number): string {
  const plan = item.monthly_plans?.[month]
  if (!plan || (plan.planned_volume === 0 && plan.planned_amount === 0)) {
    return 'Tidak ada rencana untuk bulan ini'
  }
  if (isVolumeExhausted(item, month) && isBudgetExhausted(item, month)) {
    return 'Volume dan anggaran sudah habis di bulan sebelumnya'
  }
  if (isVolumeExhausted(item, month)) {
    return 'Volume sudah habis di bulan sebelumnya'
  }
  if (isBudgetExhausted(item, month)) {
    return 'Anggaran sudah habis di bulan sebelumnya'
  }
  return ''
}

// Get max volume that can be realized for a specific month
function getMaxVolumeForMonth(item: BudgetItem, month: number): number {
  const dpaVolume = Number(item.volume) || 0
  const realizedBefore = getRealizedVolumeBeforeMonth(item, month)
  return Math.max(0, dpaVolume - realizedBefore)
}

// Get max amount that can be realized for a specific month
function getMaxAmountForMonth(item: BudgetItem, month: number): number {
  const dpaBudget = Number(item.total_budget) || 0
  const realizedBefore = getRealizedAmountBeforeMonth(item, month)
  return Math.max(0, dpaBudget - realizedBefore)
}

// Get volume usage percentage (realized/DPA)
function getVolumeUsagePercent(item: BudgetItem): number {
  const dpaVolume = Number(item.volume) || 0
  if (dpaVolume === 0) return 0
  const realized = getTotalRealizedVolume(item)
  return Math.min(100, (realized / dpaVolume) * 100)
}

// Get budget usage percentage (realized/DPA)
function getBudgetUsagePercent(item: BudgetItem): number {
  const dpaBudget = Number(item.total_budget) || 0
  if (dpaBudget === 0) return 0
  const realized = getTotalRealizedAmount(item)
  return Math.min(100, (realized / dpaBudget) * 100)
}

// Get status color based on usage
function getUsageStatus(percent: number): 'success' | 'warning' | 'error' {
  if (percent >= 100) return 'error'
  if (percent >= 80) return 'warning'
  return 'success'
}

// ============ DATA FETCHING ============

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
    const response = await api.get('/budget-items', {
      params: {
        sub_activity_id: selectedSubActivityId.value,
        per_page: 500,
        with_monthly_plans: true,
        with_realizations: true,
        year: selectedYear.value
      }
    })

    // Initialize monthly_plans and monthly_realizations for each item if not present
    const items = response.data.data.map((item: BudgetItem) => ({
      ...item,
      monthly_plans: item.monthly_plans || {},
      monthly_realizations: item.monthly_realizations || {}
    }))

    budgetItems.value = items
    changedItems.value.clear()
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

// ============ INPUT HANDLERS ============

function onVolumeChange(item: BudgetItem, month: number, volume: number | null) {
  if (!item.monthly_realizations) {
    item.monthly_realizations = {}
  }

  if (!item.monthly_realizations[month]) {
    item.monthly_realizations[month] = { realized_volume: 0, realized_amount: 0 }
  }

  // Validate against max allowed volume
  const maxVolume = getMaxVolumeForMonth(item, month)
  let newVolume = volume || 0

  if (newVolume > maxVolume) {
    newVolume = maxVolume
    message.warning(`Volume maksimal untuk bulan ini adalah ${maxVolume.toFixed(2)}`)
  }

  // Calculate the resulting amount
  let newAmount = newVolume * Number(item.unit_price)

  // Also validate against max allowed budget
  const maxBudget = getMaxAmountForMonth(item, month)
  if (newAmount > maxBudget) {
    newAmount = maxBudget
    newVolume = item.unit_price > 0 ? newAmount / Number(item.unit_price) : 0
    message.warning(`Anggaran maksimal untuk bulan ini adalah ${formatCurrency(maxBudget)}`)
  }

  item.monthly_realizations[month].realized_volume = newVolume
  item.monthly_realizations[month].realized_amount = newAmount

  changedItems.value.add(item.id)
}

function onAmountChange(item: BudgetItem, month: number, amount: number | null) {
  if (!item.monthly_realizations) {
    item.monthly_realizations = {}
  }

  if (!item.monthly_realizations[month]) {
    item.monthly_realizations[month] = { realized_volume: 0, realized_amount: 0 }
  }

  let newAmount = amount || 0

  // Validate against max allowed budget first
  const maxBudget = getMaxAmountForMonth(item, month)
  if (newAmount > maxBudget) {
    newAmount = maxBudget
    message.warning(`Anggaran maksimal untuk bulan ini adalah ${formatCurrency(maxBudget)}`)
  }

  // Calculate volume from amount
  let newVolume = 0
  if (item.unit_price > 0) {
    newVolume = newAmount / Number(item.unit_price)
  }

  // Also validate against max allowed volume
  const maxVolume = getMaxVolumeForMonth(item, month)
  if (newVolume > maxVolume) {
    newVolume = maxVolume
    newAmount = maxVolume * Number(item.unit_price)
    message.warning(`Volume maksimal untuk bulan ini adalah ${maxVolume.toFixed(2)}`)
  }

  item.monthly_realizations[month].realized_amount = newAmount
  item.monthly_realizations[month].realized_volume = newVolume

  changedItems.value.add(item.id)
}

// ============ SAVE FUNCTIONS ============

async function saveAllRealizations() {
  if (changedItems.value.size === 0) {
    message.info('Tidak ada perubahan untuk disimpan')
    return
  }

  saving.value = true
  try {
    const realizationsToSave: any[] = []

    for (const itemId of changedItems.value) {
      const item = budgetItems.value.find(i => i.id === itemId)
      if (!item || !item.monthly_realizations) continue

      for (const [monthStr, real] of Object.entries(item.monthly_realizations)) {
        const month = parseInt(monthStr)
        // Find the corresponding monthly plan
        const plan = item.monthly_plans?.[month]
        if (!plan?.id) continue // Skip if no plan exists

        if (real.realized_volume > 0 || real.realized_amount > 0) {
          realizationsToSave.push({
            monthly_plan_id: plan.id,
            realized_volume: real.realized_volume,
            realized_amount: real.realized_amount,
            id: real.id // For updates
          })
        }
      }
    }

    if (realizationsToSave.length > 0) {
      await api.post('/realizations/batch', { realizations: realizationsToSave })
      message.success(`${realizationsToSave.length} realisasi berhasil disimpan`)
      changedItems.value.clear()
      // Refresh data
      await fetchBudgetItems()
    }
  } catch (err: any) {
    message.error(err.response?.data?.message || 'Gagal menyimpan realisasi')
  } finally {
    saving.value = false
  }
}

function getMonthPlan(item: BudgetItem, month: number): MonthlyPlanData {
  return item.monthly_plans?.[month] || { planned_volume: 0, planned_amount: 0 }
}

function getMonthRealization(item: BudgetItem, month: number): RealizationData {
  return item.monthly_realizations?.[month] || { realized_volume: 0, realized_amount: 0 }
}

// ============ TOTALS ============

// Calculate monthly totals
const monthlyTotals = computed(() => {
  const totals: Record<number, {
    plannedVolume: number
    plannedAmount: number
    ytdPlannedVolume: number
    ytdPlannedAmount: number
    realizedVolume: number
    realizedAmount: number
    ytdRealizedVolume: number
    ytdRealizedAmount: number
  }> = {}

  let cumPlannedVolume = 0
  let cumPlannedAmount = 0
  let cumRealizedVolume = 0
  let cumRealizedAmount = 0

  for (const month of months) {
    let monthPlannedVolume = 0
    let monthPlannedAmount = 0
    let monthRealizedVolume = 0
    let monthRealizedAmount = 0

    for (const item of editableItems.value) {
      const plan = item.monthly_plans?.[month.value]
      const real = item.monthly_realizations?.[month.value]
      if (plan) {
        monthPlannedVolume += Number(plan.planned_volume) || 0
        monthPlannedAmount += Number(plan.planned_amount) || 0
      }
      if (real) {
        monthRealizedVolume += Number(real.realized_volume) || 0
        monthRealizedAmount += Number(real.realized_amount) || 0
      }
    }

    cumPlannedVolume += monthPlannedVolume
    cumPlannedAmount += monthPlannedAmount
    cumRealizedVolume += monthRealizedVolume
    cumRealizedAmount += monthRealizedAmount

    totals[month.value] = {
      plannedVolume: monthPlannedVolume,
      plannedAmount: monthPlannedAmount,
      ytdPlannedVolume: cumPlannedVolume,
      ytdPlannedAmount: cumPlannedAmount,
      realizedVolume: monthRealizedVolume,
      realizedAmount: monthRealizedAmount,
      ytdRealizedVolume: cumRealizedVolume,
      ytdRealizedAmount: cumRealizedAmount
    }
  }

  return totals
})

// Grand totals
const grandTotal = computed(() => {
  let totalBudget = 0
  let totalPlanned = 0
  let totalRealized = 0

  for (const item of editableItems.value) {
    totalBudget += Number(item.total_budget) || 0
    totalPlanned += getTotalPlannedAmount(item)
    totalRealized += getTotalRealizedAmount(item)
  }

  return {
    budget: totalBudget,
    planned: totalPlanned,
    realized: totalRealized,
    remaining: totalBudget - totalRealized
  }
})

watch([selectedYear], () => {
  if (selectedSubActivityId.value) {
    fetchBudgetItems()
  }
})

onMounted(() => {
  fetchSubActivities()
})
</script>

<template>
  <div class="monthly-realization-grid">
    <!-- Header -->
    <div class="mb-6">
      <h1 class="text-2xl font-bold text-gray-800">Realisasi Bulanan</h1>
      <p class="text-gray-500 mt-1">Input realisasi anggaran per bulan (Format PLGK)</p>
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
        <NSpace>
          <NButton
            type="primary"
            :loading="saving"
            :disabled="changedItems.size === 0"
            @click="saveAllRealizations"
          >
            <template #icon>
              <NIcon><SaveOutline /></NIcon>
            </template>
            Simpan ({{ changedItems.size }})
          </NButton>
          <NButton @click="fetchBudgetItems" :disabled="!selectedSubActivityId">
            <template #icon>
              <NIcon><RefreshOutline /></NIcon>
            </template>
            Refresh
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
        </div>
      </div>
    </NCard>

    <!-- Loading State -->
    <div v-if="loading" class="flex justify-center py-12">
      <NSpin size="large" />
    </div>

    <!-- Empty State -->
    <NCard v-else-if="!selectedSubActivityId">
      <NEmpty description="Pilih Sub Kegiatan untuk mulai input realisasi" />
    </NCard>

    <NCard v-else-if="editableItems.length === 0">
      <NEmpty description="Tidak ada item anggaran detail untuk Sub Kegiatan ini" />
    </NCard>

    <!-- Realization Grid -->
    <NCard v-else class="realization-grid-card">
      <template #header>
        <div class="flex items-center justify-between">
          <span class="font-bold">Realisasi Fisik dan Keuangan</span>
          <div class="flex gap-4">
            <NTag type="info">{{ editableItems.length }} item</NTag>
            <NTag type="success">
              Realisasi: {{ formatCurrency(grandTotal.realized) }} / {{ formatCurrency(grandTotal.budget) }}
            </NTag>
            <NTag :type="grandTotal.remaining >= 0 ? 'warning' : 'error'">
              Sisa: {{ formatCurrency(grandTotal.remaining) }}
            </NTag>
          </div>
        </div>
      </template>

      <div class="realization-grid-wrapper">
        <table class="realization-grid">
          <thead>
            <!-- Row 1: Main headers -->
            <tr>
              <th rowspan="3" class="col-code sticky-col sticky-left-0">Kode Rekening</th>
              <th rowspan="3" class="col-uraian sticky-col sticky-left-1">Uraian</th>
              <th colspan="6" rowspan="2" class="col-header-group">Rincian DPA</th>
              <!-- Per month headers -->
              <template v-for="month in months" :key="`header-${month.value}`">
                <th :colspan="month.value === 1 ? 4 : 8" class="col-month-header">{{ month.fullLabel }}</th>
              </template>
              <th colspan="2" rowspan="2" class="col-sisa-header">SISA</th>
            </tr>
            <!-- Row 2: Rencana / Realisasi sub-headers -->
            <tr>
              <template v-for="month in months" :key="`subheader-${month.value}`">
                <th :colspan="month.value === 1 ? 2 : 4" class="col-subheader col-rencana">Rencana</th>
                <th :colspan="month.value === 1 ? 2 : 4" class="col-subheader col-realisasi">Realisasi</th>
              </template>
            </tr>
            <!-- Row 3: Column labels -->
            <tr>
              <th class="col-volume">Vol</th>
              <th class="col-satuan">Satuan</th>
              <th class="col-harga">Harga</th>
              <th class="col-jumlah">Jumlah</th>
              <th class="col-sisa-vol">Sisa Vol</th>
              <th class="col-sisa-budget">Sisa Anggaran</th>
              <!-- Per month columns -->
              <template v-for="month in months" :key="`cols-${month.value}`">
                <!-- Rencana columns -->
                <th class="col-input col-rencana">Vol</th>
                <th class="col-input col-rencana">Jml</th>
                <template v-if="month.value > 1">
                  <th class="col-input col-rencana col-ytd">s/d Vol</th>
                  <th class="col-input col-rencana col-ytd">s/d Jml</th>
                </template>
                <!-- Realisasi columns -->
                <th class="col-input col-realisasi">Vol</th>
                <th class="col-input col-realisasi">Jml</th>
                <template v-if="month.value > 1">
                  <th class="col-input col-realisasi col-ytd">s/d Vol</th>
                  <th class="col-input col-realisasi col-ytd">s/d Jml</th>
                </template>
              </template>
              <!-- SISA columns -->
              <th class="col-sisa-final">Vol</th>
              <th class="col-sisa-final">Jumlah</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in editableItems" :key="item.id" :class="{ 'row-changed': changedItems.has(item.id) }">
              <td class="col-code sticky-col sticky-left-0">{{ item.code }}</td>
              <td class="col-uraian sticky-col sticky-left-1">
                <div class="item-name">{{ item.name }}</div>
                <div v-if="item.group_name" class="item-meta">{{ item.group_name }}</div>
              </td>
              <td class="col-volume">{{ item.volume }}</td>
              <td class="col-satuan">{{ item.unit }}</td>
              <td class="col-harga">{{ formatCurrency(item.unit_price) }}</td>
              <td class="col-jumlah">{{ formatCurrency(item.total_budget) }}</td>
              <!-- Sisa Volume Column with Progress -->
              <td class="col-sisa-vol">
                <NTooltip>
                  <template #trigger>
                    <div class="sisa-volume">
                      <div class="sisa-value" :class="getUsageStatus(getVolumeUsagePercent(item))">
                        {{ getRemainingVolume(item).toFixed(2) }}
                      </div>
                      <NProgress
                        type="line"
                        :percentage="getVolumeUsagePercent(item)"
                        :status="getUsageStatus(getVolumeUsagePercent(item))"
                        :show-indicator="false"
                        :height="4"
                      />
                    </div>
                  </template>
                  <div class="text-xs">
                    <div>Volume DPA: {{ item.volume }} {{ item.unit }}</div>
                    <div>Terealisasi: {{ getTotalRealizedVolume(item).toFixed(2) }} {{ item.unit }}</div>
                    <div>Sisa: {{ getRemainingVolume(item).toFixed(2) }} {{ item.unit }}</div>
                    <div class="mt-1 font-medium">
                      {{ getVolumeUsagePercent(item).toFixed(1) }}% terealisasi
                    </div>
                  </div>
                </NTooltip>
              </td>
              <!-- Sisa Anggaran Column with Progress -->
              <td class="col-sisa-budget">
                <NTooltip>
                  <template #trigger>
                    <div class="sisa-budget">
                      <div class="sisa-value" :class="getUsageStatus(getBudgetUsagePercent(item))">
                        {{ formatCurrency(getRemainingBudget(item)) }}
                      </div>
                      <NProgress
                        type="line"
                        :percentage="getBudgetUsagePercent(item)"
                        :status="getUsageStatus(getBudgetUsagePercent(item))"
                        :show-indicator="false"
                        :height="4"
                      />
                    </div>
                  </template>
                  <div class="text-xs">
                    <div>Anggaran DPA: {{ formatCurrency(item.total_budget) }}</div>
                    <div>Terealisasi: {{ formatCurrency(getTotalRealizedAmount(item)) }}</div>
                    <div>Sisa: {{ formatCurrency(getRemainingBudget(item)) }}</div>
                    <div class="mt-1 font-medium">
                      {{ getBudgetUsagePercent(item).toFixed(1) }}% terealisasi
                    </div>
                  </div>
                </NTooltip>
              </td>

              <!-- Per month data -->
              <template v-for="month in months" :key="`data-${item.id}-${month.value}`">
                <!-- Rencana columns (read-only) -->
                <td class="col-input col-rencana col-readonly">
                  {{ getMonthPlan(item, month.value).planned_volume.toFixed(2) }}
                </td>
                <td class="col-input col-rencana col-readonly">
                  {{ formatCurrency(getMonthPlan(item, month.value).planned_amount) }}
                </td>
                <template v-if="month.value > 1">
                  <td class="col-input col-rencana col-ytd col-readonly">
                    {{ getYtdPlannedValues(item, month.value).volume.toFixed(2) }}
                  </td>
                  <td class="col-input col-rencana col-ytd col-readonly">
                    {{ formatCurrency(getYtdPlannedValues(item, month.value).amount) }}
                  </td>
                </template>

                <!-- Realisasi columns (input) -->
                <td class="col-input col-realisasi" :class="{ 'col-disabled': isCellDisabled(item, month.value) }">
                  <NTooltip v-if="isCellDisabled(item, month.value)" placement="top">
                    <template #trigger>
                      <div class="disabled-cell">
                        <NIcon color="#dc2626"><CloseCircle /></NIcon>
                      </div>
                    </template>
                    <span>{{ getDisabledReason(item, month.value) }}</span>
                  </NTooltip>
                  <NInputNumber
                    v-else
                    :value="getMonthRealization(item, month.value).realized_volume || null"
                    :min="0"
                    :max="getMaxVolumeForMonth(item, month.value)"
                    :precision="2"
                    size="small"
                    placeholder="0"
                    :show-button="false"
                    @update:value="(v) => onVolumeChange(item, month.value, v)"
                  />
                </td>
                <td class="col-input col-realisasi" :class="{ 'col-disabled': isCellDisabled(item, month.value) }">
                  <NTooltip v-if="isCellDisabled(item, month.value)" placement="top">
                    <template #trigger>
                      <div class="disabled-cell">-</div>
                    </template>
                    <span>{{ getDisabledReason(item, month.value) }}</span>
                  </NTooltip>
                  <NInputNumber
                    v-else
                    :value="getMonthRealization(item, month.value).realized_amount || null"
                    :min="0"
                    :max="getMaxAmountForMonth(item, month.value)"
                    size="small"
                    placeholder="0"
                    :show-button="false"
                    @update:value="(v) => onAmountChange(item, month.value, v)"
                  />
                </td>
                <template v-if="month.value > 1">
                  <td class="col-input col-realisasi col-ytd col-readonly">
                    {{ getYtdRealizedValues(item, month.value).volume.toFixed(2) }}
                  </td>
                  <td class="col-input col-realisasi col-ytd col-readonly">
                    {{ formatCurrency(getYtdRealizedValues(item, month.value).amount) }}
                  </td>
                </template>
              </template>

              <!-- SISA columns -->
              <td class="col-sisa-final" :class="{ 'col-negative': getRemainingVolume(item) < 0 }">
                {{ getRemainingVolume(item).toFixed(2) }}
              </td>
              <td class="col-sisa-final" :class="{ 'col-negative': getRemainingBudget(item) < 0 }">
                {{ formatCurrency(getRemainingBudget(item)) }}
              </td>
            </tr>
          </tbody>
          <tfoot>
            <tr class="total-row">
              <td colspan="2" class="sticky-col sticky-left-0 text-right font-bold">TOTAL</td>
              <td colspan="6" class="col-jumlah font-bold">{{ formatCurrency(grandTotal.budget) }}</td>
              <!-- Per month totals -->
              <template v-for="month in months" :key="`total-${month.value}`">
                <!-- Rencana totals -->
                <td class="col-input col-rencana font-medium">
                  {{ (monthlyTotals[month.value]?.plannedVolume || 0).toFixed(2) }}
                </td>
                <td class="col-input col-rencana font-medium">
                  {{ formatCurrency(monthlyTotals[month.value]?.plannedAmount || 0) }}
                </td>
                <template v-if="month.value > 1">
                  <td class="col-input col-rencana col-ytd font-medium">
                    {{ (monthlyTotals[month.value]?.ytdPlannedVolume || 0).toFixed(2) }}
                  </td>
                  <td class="col-input col-rencana col-ytd font-medium">
                    {{ formatCurrency(monthlyTotals[month.value]?.ytdPlannedAmount || 0) }}
                  </td>
                </template>
                <!-- Realisasi totals -->
                <td class="col-input col-realisasi font-medium">
                  {{ (monthlyTotals[month.value]?.realizedVolume || 0).toFixed(2) }}
                </td>
                <td class="col-input col-realisasi font-medium">
                  {{ formatCurrency(monthlyTotals[month.value]?.realizedAmount || 0) }}
                </td>
                <template v-if="month.value > 1">
                  <td class="col-input col-realisasi col-ytd font-medium">
                    {{ (monthlyTotals[month.value]?.ytdRealizedVolume || 0).toFixed(2) }}
                  </td>
                  <td class="col-input col-realisasi col-ytd font-medium">
                    {{ formatCurrency(monthlyTotals[month.value]?.ytdRealizedAmount || 0) }}
                  </td>
                </template>
              </template>
              <!-- SISA totals -->
              <td class="col-sisa-final font-bold">-</td>
              <td class="col-sisa-final font-bold">{{ formatCurrency(grandTotal.remaining) }}</td>
            </tr>
          </tfoot>
        </table>
      </div>
    </NCard>
  </div>
</template>

<style scoped>
.realization-grid-wrapper {
  overflow-x: auto;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  max-height: 70vh;
  overflow-y: auto;
}

.realization-grid {
  width: max-content;
  min-width: 100%;
  border-collapse: collapse;
  font-size: 10px;
}

.realization-grid thead {
  background: #1e3a5f;
  color: white;
  position: sticky;
  top: 0;
  z-index: 20;
}

.realization-grid th {
  padding: 4px 6px;
  text-align: center;
  font-weight: 600;
  border: 1px solid rgba(255,255,255,0.2);
  white-space: nowrap;
}

.realization-grid td {
  padding: 3px 4px;
  border: 1px solid #e5e7eb;
  vertical-align: middle;
}

/* Sticky columns */
.sticky-col {
  position: sticky;
  background: white;
  z-index: 10;
}

.sticky-left-0 {
  left: 0;
  min-width: 110px;
}

.sticky-left-1 {
  left: 110px;
  min-width: 150px;
  max-width: 170px;
}

.realization-grid thead .sticky-col {
  background: #1e3a5f;
  z-index: 25;
}

.realization-grid .col-code {
  font-family: 'Courier New', monospace;
  font-size: 9px;
  white-space: nowrap;
}

.realization-grid .col-uraian {
  text-align: left;
}

.item-name {
  font-weight: 500;
  font-size: 10px;
  line-height: 1.2;
}

.item-meta {
  font-size: 8px;
  color: #6b7280;
  margin-top: 1px;
}

.realization-grid .col-volume,
.realization-grid .col-satuan {
  width: 40px;
  text-align: center;
}

.realization-grid .col-harga,
.realization-grid .col-jumlah {
  width: 75px;
  text-align: right;
  font-family: 'Courier New', monospace;
  font-size: 9px;
}

.realization-grid .col-sisa-vol {
  width: 60px;
  text-align: center;
}

.realization-grid .col-sisa-budget {
  width: 85px;
  text-align: center;
}

.sisa-volume,
.sisa-budget {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.sisa-value {
  font-weight: 600;
  font-size: 9px;
}

.sisa-budget .sisa-value {
  font-size: 8px;
}

.sisa-value.success {
  color: #16a34a;
}

.sisa-value.warning {
  color: #d97706;
}

.sisa-value.error {
  color: #dc2626;
}

.realization-grid .col-header-group {
  background: #2d4a6f;
}

.realization-grid .col-month-header {
  background: #3b5998;
}

.realization-grid .col-subheader {
  background: #4a6fa5;
  font-size: 9px;
}

.realization-grid .col-rencana {
  background: #e0f2fe !important;
  color: #0369a1;
}

.realization-grid thead .col-rencana {
  background: #0369a1 !important;
  color: white;
}

.realization-grid .col-realisasi {
  background: #fef3c7 !important;
  color: #92400e;
}

.realization-grid thead .col-realisasi {
  background: #d97706 !important;
  color: white;
}

.realization-grid .col-ytd {
  background: #f0fdf4 !important;
  color: #166534;
}

.realization-grid thead .col-ytd.col-rencana {
  background: #14532d !important;
  color: white;
}

.realization-grid thead .col-ytd.col-realisasi {
  background: #78350f !important;
  color: white;
}

.realization-grid .col-sisa-header {
  background: #dc2626 !important;
  color: white;
}

.realization-grid .col-sisa-final {
  background: #fef2f2;
  color: #991b1b;
  font-weight: 600;
  text-align: right;
  width: 70px;
  font-family: 'Courier New', monospace;
  font-size: 9px;
}

.realization-grid .col-negative {
  background: #fecaca !important;
  color: #b91c1c !important;
}

.realization-grid .col-input {
  width: 55px;
  padding: 2px 3px;
  text-align: right;
  font-size: 9px;
}

.realization-grid .col-readonly {
  font-family: 'Courier New', monospace;
}

.realization-grid .col-disabled {
  background: #fee2e2 !important;
}

.disabled-cell {
  display: flex;
  align-items: center;
  justify-content: center;
  color: #dc2626;
  font-size: 10px;
}

.realization-grid .col-input :deep(.n-input-number) {
  width: 100%;
}

.realization-grid .col-input :deep(.n-input__input-el) {
  text-align: right;
  font-size: 9px;
  padding: 1px 3px;
}

/* Row states */
.row-changed {
  background: #fef3c7 !important;
}

.row-changed .sticky-col {
  background: #fef3c7 !important;
}

.realization-grid tbody tr:hover {
  background: #f3f4f6;
}

.realization-grid tbody tr:hover .sticky-col {
  background: #f3f4f6;
}

.realization-grid tbody tr:hover.row-changed {
  background: #fde68a;
}

.realization-grid tbody tr:hover.row-changed .sticky-col {
  background: #fde68a;
}

/* Footer */
.realization-grid tfoot {
  background: #f3f4f6;
  position: sticky;
  bottom: 0;
  z-index: 15;
}

.realization-grid tfoot .sticky-col {
  background: #f3f4f6;
}

.total-row td {
  padding: 6px 4px;
  border-top: 2px solid #1e3a5f;
  font-weight: 600;
}

/* Zebra striping */
.realization-grid tbody tr:nth-child(even) {
  background: #fafafa;
}

.realization-grid tbody tr:nth-child(even) .sticky-col {
  background: #fafafa;
}
</style>
