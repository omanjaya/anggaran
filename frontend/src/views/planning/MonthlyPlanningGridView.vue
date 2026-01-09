<script setup lang="ts">
import { ref, computed, onMounted, watch, onUnmounted } from 'vue'
import {
  NCard, NSelect, NSpin, NEmpty, NButton, NIcon, NInputNumber, NSpace, NTag, NTooltip, NProgress
} from 'naive-ui'
import { SaveOutline, RefreshOutline, CloseCircle, CloudDoneOutline, CloudOfflineOutline, SyncOutline } from '@vicons/ionicons5'
import { useFormat, useMessage } from '@/composables'
import api from '@/services/api'

// Auto-save status type
type SaveStatus = 'idle' | 'pending' | 'saving' | 'saved' | 'error'

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

// Auto-save state
const saveStatus = ref<SaveStatus>('idle')
const saveError = ref<string>('')
const autoSaveDelay = 2000 // 2 seconds debounce
let autoSaveTimer: ReturnType<typeof setTimeout> | null = null
const lastSavedAt = ref<Date | null>(null)

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

// Calculate totals per month (including cumulative)
const monthlyTotals = computed(() => {
  const totals: Record<number, { volume: number; amount: number; ytdVolume: number; ytdAmount: number }> = {}

  let cumulativeVolume = 0
  let cumulativeAmount = 0

  for (const month of months) {
    let monthVolume = 0
    let monthAmount = 0

    for (const item of editableItems.value) {
      const plan = item.monthly_plans?.[month.value]
      if (plan) {
        monthVolume += Number(plan.planned_volume) || 0
        monthAmount += Number(plan.planned_amount) || 0
      }
    }

    cumulativeVolume += monthVolume
    cumulativeAmount += monthAmount

    totals[month.value] = {
      volume: monthVolume,
      amount: monthAmount,
      ytdVolume: cumulativeVolume,
      ytdAmount: cumulativeAmount
    }
  }

  return totals
})

// Grand total
const grandTotal = computed(() => {
  let totalBudget = 0
  let totalPlanned = 0

  for (const item of editableItems.value) {
    totalBudget += Number(item.total_budget) || 0
    for (const month of months) {
      const plan = item.monthly_plans?.[month.value]
      if (plan) {
        totalPlanned += Number(plan.planned_amount) || 0
      }
    }
  }

  return { budget: totalBudget, planned: totalPlanned }
})

// Get total planned volume for an item (all months)
function getTotalPlannedVolume(item: BudgetItem): number {
  let total = 0
  for (let m = 1; m <= 12; m++) {
    const plan = item.monthly_plans?.[m]
    if (plan) {
      total += Number(plan.planned_volume) || 0
    }
  }
  return total
}

// Get planned volume up to (but not including) a specific month
function getPlannedVolumeBeforeMonth(item: BudgetItem, month: number): number {
  let total = 0
  for (let m = 1; m < month; m++) {
    const plan = item.monthly_plans?.[m]
    if (plan) {
      total += Number(plan.planned_volume) || 0
    }
  }
  return total
}

// Get remaining volume for an item
function getRemainingVolume(item: BudgetItem): number {
  const dpaVolume = Number(item.volume) || 0
  const plannedVolume = getTotalPlannedVolume(item)
  return Math.max(0, dpaVolume - plannedVolume)
}

// Check if volume input should be disabled for a month
function isVolumeExhausted(item: BudgetItem, month: number): boolean {
  const dpaVolume = Number(item.volume) || 0
  const plannedBefore = getPlannedVolumeBeforeMonth(item, month)
  const currentValue = item.monthly_plans?.[month]?.planned_volume || 0

  // Disabled if all volume is used up in previous months (and current month has no value)
  return plannedBefore >= dpaVolume && currentValue === 0
}

// Get max volume that can be input for a specific month
function getMaxVolumeForMonth(item: BudgetItem, month: number): number {
  const dpaVolume = Number(item.volume) || 0
  const plannedBefore = getPlannedVolumeBeforeMonth(item, month)
  return Math.max(0, dpaVolume - plannedBefore)
}

// Get volume usage percentage
function getVolumeUsagePercent(item: BudgetItem): number {
  const dpaVolume = Number(item.volume) || 0
  if (dpaVolume === 0) return 0
  const planned = getTotalPlannedVolume(item)
  return Math.min(100, (planned / dpaVolume) * 100)
}

// Get status color based on volume usage
function getUsageStatus(item: BudgetItem): 'success' | 'warning' | 'error' {
  const percent = getVolumeUsagePercent(item)
  if (percent >= 100) return 'error'
  if (percent >= 80) return 'warning'
  return 'success'
}

// ============ BUDGET VALIDATION FUNCTIONS ============

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

// Get planned amount up to (but not including) a specific month
function getPlannedAmountBeforeMonth(item: BudgetItem, month: number): number {
  let total = 0
  for (let m = 1; m < month; m++) {
    const plan = item.monthly_plans?.[m]
    if (plan) {
      total += Number(plan.planned_amount) || 0
    }
  }
  return total
}

// Get remaining budget for an item
function getRemainingBudget(item: BudgetItem): number {
  const dpaBudget = Number(item.total_budget) || 0
  const plannedAmount = getTotalPlannedAmount(item)
  return Math.max(0, dpaBudget - plannedAmount)
}

// Check if budget input should be disabled for a month
function isBudgetExhausted(item: BudgetItem, month: number): boolean {
  const dpaBudget = Number(item.total_budget) || 0
  const plannedBefore = getPlannedAmountBeforeMonth(item, month)
  const currentValue = item.monthly_plans?.[month]?.planned_amount || 0

  // Disabled if all budget is used up in previous months (and current month has no value)
  return plannedBefore >= dpaBudget && currentValue === 0
}

// Get max amount that can be input for a specific month
function getMaxAmountForMonth(item: BudgetItem, month: number): number {
  const dpaBudget = Number(item.total_budget) || 0
  const plannedBefore = getPlannedAmountBeforeMonth(item, month)
  return Math.max(0, dpaBudget - plannedBefore)
}

// Get budget usage percentage
function getBudgetUsagePercent(item: BudgetItem): number {
  const dpaBudget = Number(item.total_budget) || 0
  if (dpaBudget === 0) return 0
  const planned = getTotalPlannedAmount(item)
  return Math.min(100, (planned / dpaBudget) * 100)
}

// Get status color based on budget usage
function getBudgetUsageStatus(item: BudgetItem): 'success' | 'warning' | 'error' {
  const percent = getBudgetUsagePercent(item)
  if (percent >= 100) return 'error'
  if (percent >= 80) return 'warning'
  return 'success'
}

// Check if cell should be disabled (either volume OR budget exhausted)
function isCellDisabled(item: BudgetItem, month: number): boolean {
  return isVolumeExhausted(item, month) || isBudgetExhausted(item, month)
}

// Get disabled reason
function getDisabledReason(item: BudgetItem, month: number): string {
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
        year: selectedYear.value
      }
    })

    // Initialize monthly_plans for each item and convert string values to numbers
    const items = response.data.data.map((item: BudgetItem) => {
      const monthlyPlans: Record<number, MonthlyPlanData> = {}

      // Convert string values to numbers (API returns decimal as strings)
      if (item.monthly_plans) {
        for (const [monthStr, plan] of Object.entries(item.monthly_plans)) {
          const month = parseInt(monthStr)
          monthlyPlans[month] = {
            id: plan.id,
            planned_volume: Number(plan.planned_volume) || 0,
            planned_amount: Number(plan.planned_amount) || 0,
          }
        }
      }

      return {
        ...item,
        monthly_plans: monthlyPlans
      }
    })

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

function onVolumeChange(item: BudgetItem, month: number, volume: number | null) {
  if (!item.monthly_plans) {
    item.monthly_plans = {}
  }

  if (!item.monthly_plans[month]) {
    item.monthly_plans[month] = { planned_volume: 0, planned_amount: 0 }
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

  item.monthly_plans[month].planned_volume = newVolume
  item.monthly_plans[month].planned_amount = newAmount

  changedItems.value.add(item.id)

  // Trigger auto-save
  triggerAutoSave()
}

function onAmountChange(item: BudgetItem, month: number, amount: number | null) {
  if (!item.monthly_plans) {
    item.monthly_plans = {}
  }

  if (!item.monthly_plans[month]) {
    item.monthly_plans[month] = { planned_volume: 0, planned_amount: 0 }
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

  item.monthly_plans[month].planned_amount = newAmount
  item.monthly_plans[month].planned_volume = newVolume

  changedItems.value.add(item.id)

  // Trigger auto-save
  triggerAutoSave()
}

// Trigger auto-save with debounce
function triggerAutoSave() {
  // Clear existing timer
  if (autoSaveTimer) {
    clearTimeout(autoSaveTimer)
  }

  // Set status to pending
  saveStatus.value = 'pending'
  saveError.value = ''

  // Set new timer
  autoSaveTimer = setTimeout(() => {
    saveAllPlans(true) // true = auto-save mode (silent)
  }, autoSaveDelay)
}

// Cancel pending auto-save
function cancelAutoSave() {
  if (autoSaveTimer) {
    clearTimeout(autoSaveTimer)
    autoSaveTimer = null
  }
}

async function saveAllPlans(isAutoSave = false) {
  if (changedItems.value.size === 0) {
    if (!isAutoSave) {
      message.info('Tidak ada perubahan untuk disimpan')
    }
    saveStatus.value = 'idle'
    return
  }

  saving.value = true
  saveStatus.value = 'saving'
  saveError.value = ''

  try {
    const plansToSave: any[] = []

    for (const itemId of changedItems.value) {
      const item = budgetItems.value.find(i => i.id === itemId)
      if (!item || !item.monthly_plans) continue

      for (const [monthStr, plan] of Object.entries(item.monthly_plans)) {
        const month = parseInt(monthStr)
        // Include all plans with changes, even if values are 0
        plansToSave.push({
          budget_item_id: item.id,
          month,
          year: selectedYear.value,
          planned_volume: Number(plan.planned_volume) || 0,
          planned_amount: Number(plan.planned_amount) || 0,
          id: plan.id // For updates
        })
      }
    }

    if (plansToSave.length === 0) {
      if (!isAutoSave) {
        message.warning('Tidak ada data yang valid untuk disimpan')
      }
      saveStatus.value = 'idle'
      return
    }

    console.log('Saving plans:', plansToSave)
    const response = await api.post('/monthly-plans/batch', { plans: plansToSave })
    console.log('Save response:', response.data)

    if (response.data.success) {
      saveStatus.value = 'saved'
      lastSavedAt.value = new Date()

      // Update IDs from response if provided (for newly created plans)
      if (response.data.data && Array.isArray(response.data.data)) {
        for (const savedPlan of response.data.data) {
          const item = budgetItems.value.find(i => i.id === savedPlan.budget_item_id)
          if (item?.monthly_plans?.[savedPlan.month]) {
            item.monthly_plans[savedPlan.month]!.id = savedPlan.id
          }
        }
      }

      // Clear changed items WITHOUT refreshing data - keep local values
      changedItems.value.clear()

      if (!isAutoSave) {
        message.success(`${response.data.saved_count} rencana berhasil disimpan`)
      }

      // Reset status after 3 seconds
      setTimeout(() => {
        if (saveStatus.value === 'saved') {
          saveStatus.value = 'idle'
        }
      }, 3000)
    } else {
      saveStatus.value = 'error'
      saveError.value = response.data.message || 'Gagal menyimpan'
      message.error(response.data.message || 'Gagal menyimpan sebagian data')
      if (response.data.errors?.length > 0) {
        response.data.errors.forEach((err: string) => message.warning(err))
      }
    }
  } catch (err: any) {
    console.error('Save error:', err)
    saveStatus.value = 'error'
    const errorMsg = err.response?.data?.message || err.message || 'Gagal menyimpan rencana'
    saveError.value = errorMsg

    if (!isAutoSave) {
      message.error(errorMsg)
    }

    // If authentication error, suggest re-login
    if (err.response?.status === 401) {
      message.warning('Sesi Anda telah berakhir. Silakan login ulang.')
    }
  } finally {
    saving.value = false
  }
}

function getMonthPlan(item: BudgetItem, month: number): MonthlyPlanData {
  return item.monthly_plans?.[month] || { planned_volume: 0, planned_amount: 0 }
}

// Calculate cumulative (YTD) values for an item up to a given month
function getYtdValues(item: BudgetItem, upToMonth: number): { volume: number; amount: number } {
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

watch([selectedYear], () => {
  if (selectedSubActivityId.value) {
    fetchBudgetItems()
  }
})

onMounted(() => {
  fetchSubActivities()
})

onUnmounted(() => {
  // Cancel any pending auto-save
  cancelAutoSave()
})
</script>

<template>
  <div class="monthly-planning-grid">
    <!-- Header -->
    <div class="mb-6">
      <h1 class="text-2xl font-bold text-gray-800">Perencanaan Bulanan</h1>
      <p class="text-gray-500 mt-1">Input rencana anggaran per bulan (Format PLGK)</p>
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
        <NSpace align="center">
          <!-- Auto-save Status Indicator -->
          <div class="save-status-indicator">
            <template v-if="saveStatus === 'pending'">
              <NTag type="warning" size="small" round>
                <template #icon>
                  <NIcon><SyncOutline /></NIcon>
                </template>
                Menunggu...
              </NTag>
            </template>
            <template v-else-if="saveStatus === 'saving'">
              <NTag type="info" size="small" round>
                <template #icon>
                  <NIcon class="spinning"><SyncOutline /></NIcon>
                </template>
                Menyimpan...
              </NTag>
            </template>
            <template v-else-if="saveStatus === 'saved'">
              <NTag type="success" size="small" round>
                <template #icon>
                  <NIcon><CloudDoneOutline /></NIcon>
                </template>
                Tersimpan
              </NTag>
            </template>
            <template v-else-if="saveStatus === 'error'">
              <NTooltip>
                <template #trigger>
                  <NTag type="error" size="small" round>
                    <template #icon>
                      <NIcon><CloudOfflineOutline /></NIcon>
                    </template>
                    Gagal
                  </NTag>
                </template>
                <span>{{ saveError }}</span>
              </NTooltip>
            </template>
          </div>

          <NButton
            type="primary"
            :loading="saving"
            :disabled="changedItems.size === 0"
            @click="saveAllPlans(false)"
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
      <NEmpty description="Pilih Sub Kegiatan untuk mulai input perencanaan" />
    </NCard>

    <NCard v-else-if="editableItems.length === 0">
      <NEmpty description="Tidak ada item anggaran detail untuk Sub Kegiatan ini" />
    </NCard>

    <!-- Planning Grid -->
    <NCard v-else class="planning-grid-card">
      <template #header>
        <div class="flex items-center justify-between">
          <span class="font-bold">Rencana Fisik dan Keuangan</span>
          <div class="flex gap-4">
            <NTag type="info">{{ editableItems.length }} item</NTag>
            <NTag :type="grandTotal.planned <= grandTotal.budget ? 'success' : 'error'">
              Rencana: {{ formatCurrency(grandTotal.planned) }} / {{ formatCurrency(grandTotal.budget) }}
            </NTag>
          </div>
        </div>
      </template>

      <div class="planning-grid-wrapper">
        <table class="planning-grid">
          <thead>
            <!-- Row 1: Main headers -->
            <tr>
              <th rowspan="3" class="col-code sticky-col sticky-left-0">Kode Rekening</th>
              <th rowspan="3" class="col-uraian sticky-col sticky-left-1">Uraian</th>
              <th colspan="6" rowspan="2" class="col-header-group">Rincian Perhitungan</th>
              <!-- January - only 2 columns (no YTD) -->
              <th colspan="2" class="col-month-header month-start">Januari</th>
              <!-- February onwards - 4 columns each (with YTD) -->
              <template v-for="month in months.slice(1)" :key="`header-${month.value}`">
                <th colspan="4" class="col-month-header month-start">{{ month.fullLabel }}</th>
              </template>
            </tr>
            <!-- Row 2: Sub-headers for months -->
            <tr>
              <!-- January sub-headers -->
              <th colspan="2" class="col-subheader month-start">Bulan Ini</th>
              <!-- February onwards sub-headers -->
              <template v-for="month in months.slice(1)" :key="`subheader-${month.value}`">
                <th colspan="2" class="col-subheader month-start">Bulan Ini</th>
                <th colspan="2" class="col-subheader col-ytd">s/d Bulan Ini</th>
              </template>
            </tr>
            <!-- Row 3: Column labels -->
            <tr>
              <th class="col-volume">Vol</th>
              <th class="col-satuan">Satuan</th>
              <th class="col-harga">Harga</th>
              <th class="col-jumlah">Jumlah</th>
              <th class="col-sisa">Sisa Vol</th>
              <th class="col-sisa-budget">Sisa Anggaran</th>
              <!-- January columns -->
              <th class="col-input month-start">Vol</th>
              <th class="col-input">Jumlah</th>
              <!-- February onwards columns -->
              <template v-for="month in months.slice(1)" :key="`cols-${month.value}`">
                <th class="col-input month-start">Vol</th>
                <th class="col-input">Jumlah</th>
                <th class="col-input col-ytd">Vol</th>
                <th class="col-input col-ytd">Jumlah</th>
              </template>
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
              <!-- Remaining Volume Column with Progress -->
              <td class="col-sisa">
                <NTooltip>
                  <template #trigger>
                    <div class="sisa-volume">
                      <div class="sisa-value" :class="getUsageStatus(item)">
                        {{ getRemainingVolume(item).toFixed(2) }}
                      </div>
                      <NProgress
                        type="line"
                        :percentage="getVolumeUsagePercent(item)"
                        :status="getUsageStatus(item)"
                        :show-indicator="false"
                        :height="4"
                      />
                    </div>
                  </template>
                  <div class="text-xs">
                    <div>Volume DPA: {{ item.volume }} {{ item.unit }}</div>
                    <div>Terpakai: {{ getTotalPlannedVolume(item).toFixed(2) }} {{ item.unit }}</div>
                    <div>Sisa: {{ getRemainingVolume(item).toFixed(2) }} {{ item.unit }}</div>
                    <div class="mt-1 font-medium">
                      {{ getVolumeUsagePercent(item).toFixed(1) }}% terpakai
                    </div>
                  </div>
                </NTooltip>
              </td>
              <!-- Remaining Budget Column with Progress -->
              <td class="col-sisa-budget">
                <NTooltip>
                  <template #trigger>
                    <div class="sisa-budget">
                      <div class="sisa-value" :class="getBudgetUsageStatus(item)">
                        {{ formatCurrency(getRemainingBudget(item)) }}
                      </div>
                      <NProgress
                        type="line"
                        :percentage="getBudgetUsagePercent(item)"
                        :status="getBudgetUsageStatus(item)"
                        :show-indicator="false"
                        :height="4"
                      />
                    </div>
                  </template>
                  <div class="text-xs">
                    <div>Anggaran DPA: {{ formatCurrency(item.total_budget) }}</div>
                    <div>Terpakai: {{ formatCurrency(getTotalPlannedAmount(item)) }}</div>
                    <div>Sisa: {{ formatCurrency(getRemainingBudget(item)) }}</div>
                    <div class="mt-1 font-medium">
                      {{ getBudgetUsagePercent(item).toFixed(1) }}% terpakai
                    </div>
                  </div>
                </NTooltip>
              </td>

              <!-- January - Input only (no YTD) -->
              <td class="col-input month-start" :class="{ 'col-disabled': isCellDisabled(item, 1) }">
                <NTooltip v-if="isCellDisabled(item, 1)" placement="top">
                  <template #trigger>
                    <div class="disabled-cell">
                      <NIcon color="#dc2626"><CloseCircle /></NIcon>
                    </div>
                  </template>
                  <span>{{ getDisabledReason(item, 1) }}</span>
                </NTooltip>
                <NInputNumber
                  v-else
                  :value="getMonthPlan(item, 1).planned_volume || null"
                  :min="0"
                  :max="getMaxVolumeForMonth(item, 1)"
                  :precision="2"
                  size="small"
                  placeholder="0"
                  :show-button="false"
                  @update:value="(v) => onVolumeChange(item, 1, v)"
                />
              </td>
              <td class="col-input " :class="{ 'col-disabled': isCellDisabled(item, 1) }">
                <NTooltip v-if="isCellDisabled(item, 1)" placement="top">
                  <template #trigger>
                    <div class="disabled-cell">-</div>
                  </template>
                  <span>{{ getDisabledReason(item, 1) }}</span>
                </NTooltip>
                <NInputNumber
                  v-else
                  :value="getMonthPlan(item, 1).planned_amount || null"
                  :min="0"
                  :max="getMaxAmountForMonth(item, 1)"
                  size="small"
                  placeholder="0"
                  :show-button="false"
                  @update:value="(v) => onAmountChange(item, 1, v)"
                />
              </td>

              <!-- February onwards - Input + YTD -->
              <template v-for="month in months.slice(1)" :key="`data-${item.id}-${month.value}`">
                <!-- Current month input -->
                <td class="col-input month-start" :class="{ 'col-disabled': isCellDisabled(item, month.value) }">
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
                    :value="getMonthPlan(item, month.value).planned_volume || null"
                    :min="0"
                    :max="getMaxVolumeForMonth(item, month.value)"
                    :precision="2"
                    size="small"
                    placeholder="0"
                    :show-button="false"
                    @update:value="(v) => onVolumeChange(item, month.value, v)"
                  />
                </td>
                <td class="col-input" :class="{ 'col-disabled': isCellDisabled(item, month.value) }">
                  <NTooltip v-if="isCellDisabled(item, month.value)" placement="top">
                    <template #trigger>
                      <div class="disabled-cell">-</div>
                    </template>
                    <span>{{ getDisabledReason(item, month.value) }}</span>
                  </NTooltip>
                  <NInputNumber
                    v-else
                    :value="getMonthPlan(item, month.value).planned_amount || null"
                    :min="0"
                    :max="getMaxAmountForMonth(item, month.value)"
                    size="small"
                    placeholder="0"
                    :show-button="false"
                    @update:value="(v) => onAmountChange(item, month.value, v)"
                  />
                </td>
                <!-- YTD (cumulative) - read only -->
                <td class="col-input col-ytd col-readonly">
                  {{ getYtdValues(item, month.value).volume.toFixed(2) }}
                </td>
                <td class="col-input col-ytd col-readonly">
                  {{ formatCurrency(getYtdValues(item, month.value).amount) }}
                </td>
              </template>
            </tr>
          </tbody>
          <tfoot>
            <tr class="total-row">
              <td colspan="2" class="sticky-col sticky-left-0 text-right font-bold">TOTAL</td>
              <td colspan="6" class="col-jumlah font-bold">{{ formatCurrency(grandTotal.budget) }}</td>
              <!-- January totals -->
              <td class="col-input month-start font-medium">{{ (monthlyTotals[1]?.volume || 0).toFixed(2) }}</td>
              <td class="col-input font-medium">{{ formatCurrency(monthlyTotals[1]?.amount || 0) }}</td>
              <!-- February onwards totals -->
              <template v-for="month in months.slice(1)" :key="`total-${month.value}`">
                <td class="col-input month-start font-medium">{{ (monthlyTotals[month.value]?.volume || 0).toFixed(2) }}</td>
                <td class="col-input font-medium">{{ formatCurrency(monthlyTotals[month.value]?.amount || 0) }}</td>
                <td class="col-input col-ytd font-medium">{{ (monthlyTotals[month.value]?.ytdVolume || 0).toFixed(2) }}</td>
                <td class="col-input col-ytd font-medium">{{ formatCurrency(monthlyTotals[month.value]?.ytdAmount || 0) }}</td>
              </template>
            </tr>
          </tfoot>
        </table>
      </div>
    </NCard>
  </div>
</template>

<style scoped>
.planning-grid-wrapper {
  overflow-x: auto;
  overflow-y: auto;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  max-height: 70vh;
  scroll-behavior: smooth;
  /* Shadow indicators for scroll */
  background:
    linear-gradient(to right, white 30%, rgba(255,255,255,0)),
    linear-gradient(to right, rgba(255,255,255,0), white 70%) 100% 0,
    linear-gradient(to right, rgba(0,0,0,0.1), rgba(0,0,0,0)),
    linear-gradient(to right, rgba(0,0,0,0), rgba(0,0,0,0.1)) 100% 0;
  background-repeat: no-repeat;
  background-size: 40px 100%, 40px 100%, 14px 100%, 14px 100%;
  background-attachment: local, local, scroll, scroll;
}

/* Custom scrollbar */
.planning-grid-wrapper::-webkit-scrollbar {
  height: 12px;
  width: 12px;
}

.planning-grid-wrapper::-webkit-scrollbar-track {
  background: #f1f5f9;
  border-radius: 6px;
}

.planning-grid-wrapper::-webkit-scrollbar-thumb {
  background: #94a3b8;
  border-radius: 6px;
}

.planning-grid-wrapper::-webkit-scrollbar-thumb:hover {
  background: #64748b;
}

.planning-grid {
  width: max-content;
  min-width: 100%;
  border-collapse: collapse;
  font-size: 13px;
}

.planning-grid thead {
  background: #1e3a5f;
  color: white;
  position: sticky;
  top: 0;
  z-index: 20;
}

.planning-grid th {
  padding: 10px 12px;
  text-align: center;
  font-weight: 600;
  border: 1px solid rgba(255,255,255,0.2);
  white-space: nowrap;
}

.planning-grid td {
  padding: 6px 8px;
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
  min-width: 130px;
}

.sticky-left-1 {
  left: 130px;
  min-width: 180px;
  max-width: 200px;
}

.planning-grid thead .sticky-col {
  background: #1e3a5f;
  z-index: 25;
}

.planning-grid .col-code {
  font-family: 'Courier New', monospace;
  font-size: 12px;
  white-space: nowrap;
}

.planning-grid .col-uraian {
  text-align: left;
}

.item-name {
  font-weight: 500;
  font-size: 13px;
  line-height: 1.4;
}

.item-meta {
  font-size: 11px;
  color: #6b7280;
  margin-top: 2px;
}

.planning-grid .col-volume,
.planning-grid .col-satuan {
  width: 60px;
  text-align: center;
}

.planning-grid .col-harga,
.planning-grid .col-jumlah {
  width: 110px;
  text-align: right;
  font-family: 'Courier New', monospace;
  font-size: 12px;
}

.planning-grid .col-sisa {
  width: 80px;
  text-align: center;
}

.planning-grid .col-sisa-budget {
  width: 120px;
  text-align: center;
}

.sisa-volume,
.sisa-budget {
  display: flex;
  flex-direction: column;
  gap: 3px;
}

.sisa-value {
  font-weight: 600;
  font-size: 12px;
}

.sisa-budget .sisa-value {
  font-size: 11px;
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

.planning-grid .col-header-group {
  background: #1e3a5f;
}

.planning-grid .col-month-header {
  background: #1e3a5f;
}

.planning-grid .col-subheader {
  background: #2d4a6f;
  font-size: 12px;
}

/* YTD columns - subtle gray difference */
.planning-grid .col-ytd {
  background: #f8fafc !important;
  color: #475569;
}

.planning-grid thead .col-ytd {
  background: #334155 !important;
  color: white;
}

.planning-grid .col-input {
  width: 85px;
  padding: 4px 6px;
  text-align: right;
  font-size: 13px;
}

.planning-grid .col-readonly {
  background: #f8fafc;
  font-family: 'Courier New', monospace;
}

.planning-grid .col-disabled {
  background: #fee2e2 !important;
}

.disabled-cell {
  display: flex;
  align-items: center;
  justify-content: center;
  color: #dc2626;
  font-size: 12px;
}

.planning-grid .col-input :deep(.n-input-number) {
  width: 100%;
}

.planning-grid .col-input :deep(.n-input__input-el) {
  text-align: right;
  font-size: 13px;
  padding: 4px 6px;
}

/* Row states */
.row-changed {
  background: #fef3c7 !important;
}

.row-changed .sticky-col {
  background: #fef3c7 !important;
}

.planning-grid tbody tr:hover {
  background: #f3f4f6;
}

.planning-grid tbody tr:hover .sticky-col {
  background: #f3f4f6;
}

.planning-grid tbody tr:hover.row-changed {
  background: #fde68a;
}

.planning-grid tbody tr:hover.row-changed .sticky-col {
  background: #fde68a;
}

/* Footer */
.planning-grid tfoot {
  background: #f3f4f6;
  position: sticky;
  bottom: 0;
  z-index: 15;
}

.planning-grid tfoot .sticky-col {
  background: #f3f4f6;
}

.total-row td {
  padding: 8px 6px;
  border-top: 2px solid #1e3a5f;
  font-weight: 600;
}

/* Zebra striping for better readability */
.planning-grid tbody tr:nth-child(even) {
  background: #fafafa;
}

.planning-grid tbody tr:nth-child(even) .sticky-col {
  background: #fafafa;
}

/* Auto-save status indicator */
.save-status-indicator {
  min-width: 120px;
  display: flex;
  align-items: center;
  justify-content: flex-end;
}

/* Spinning animation */
.spinning {
  animation: spin 1s linear infinite;
}

@keyframes spin {
  from {
    transform: rotate(0deg);
  }
  to {
    transform: rotate(360deg);
  }
}

/* Month separator lines */
.month-start {
  border-left: 2px solid #1e3a5f !important;
}

.planning-grid thead .month-start {
  border-left: 2px solid #fff !important;
}
</style>
