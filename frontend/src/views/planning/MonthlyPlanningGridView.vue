<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import {
  NCard, NSelect, NSpin, NEmpty, NButton, NIcon, NInputNumber, NSpace, NTag
} from 'naive-ui'
import { SaveOutline, RefreshOutline } from '@vicons/ionicons5'
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

// Calculate totals per month
const monthlyTotals = computed(() => {
  const totals: Record<number, { volume: number; amount: number }> = {}

  for (const month of months) {
    const monthTotals = { volume: 0, amount: 0 }
    for (const item of editableItems.value) {
      const plan = item.monthly_plans?.[month.value]
      if (plan) {
        monthTotals.volume += Number(plan.planned_volume) || 0
        monthTotals.amount += Number(plan.planned_amount) || 0
      }
    }
    totals[month.value] = monthTotals
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

    // Initialize monthly_plans for each item if not present
    const items = response.data.data.map((item: BudgetItem) => ({
      ...item,
      monthly_plans: item.monthly_plans || {}
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

function onVolumeChange(item: BudgetItem, month: number, volume: number | null) {
  if (!item.monthly_plans) {
    item.monthly_plans = {}
  }

  if (!item.monthly_plans[month]) {
    item.monthly_plans[month] = { planned_volume: 0, planned_amount: 0 }
  }

  const newVolume = volume || 0
  item.monthly_plans[month].planned_volume = newVolume
  item.monthly_plans[month].planned_amount = newVolume * Number(item.unit_price)

  changedItems.value.add(item.id)
}

function onAmountChange(item: BudgetItem, month: number, amount: number | null) {
  if (!item.monthly_plans) {
    item.monthly_plans = {}
  }

  if (!item.monthly_plans[month]) {
    item.monthly_plans[month] = { planned_volume: 0, planned_amount: 0 }
  }

  item.monthly_plans[month].planned_amount = amount || 0

  // Recalculate volume from amount
  if (item.unit_price > 0) {
    item.monthly_plans[month].planned_volume = (amount || 0) / Number(item.unit_price)
  }

  changedItems.value.add(item.id)
}

async function saveAllPlans() {
  if (changedItems.value.size === 0) {
    message.info('Tidak ada perubahan untuk disimpan')
    return
  }

  saving.value = true
  try {
    const plansToSave: any[] = []

    for (const itemId of changedItems.value) {
      const item = budgetItems.value.find(i => i.id === itemId)
      if (!item || !item.monthly_plans) continue

      for (const [monthStr, plan] of Object.entries(item.monthly_plans)) {
        const month = parseInt(monthStr)
        if (plan.planned_volume > 0 || plan.planned_amount > 0) {
          plansToSave.push({
            budget_item_id: item.id,
            month,
            year: selectedYear.value,
            planned_volume: plan.planned_volume,
            planned_amount: plan.planned_amount,
            id: plan.id // For updates
          })
        }
      }
    }

    if (plansToSave.length > 0) {
      await api.post('/monthly-plans/batch', { plans: plansToSave })
      message.success(`${plansToSave.length} rencana berhasil disimpan`)
      changedItems.value.clear()
      // Refresh data
      await fetchBudgetItems()
    }
  } catch (err: any) {
    message.error(err.response?.data?.message || 'Gagal menyimpan rencana')
  } finally {
    saving.value = false
  }
}

function getMonthPlan(item: BudgetItem, month: number): MonthlyPlanData {
  return item.monthly_plans?.[month] || { planned_volume: 0, planned_amount: 0 }
}

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
        <NSpace>
          <NButton
            type="primary"
            :loading="saving"
            :disabled="changedItems.size === 0"
            @click="saveAllPlans"
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
            <tr>
              <th rowspan="2" class="col-code sticky-col">Kode Rekening</th>
              <th rowspan="2" class="col-uraian sticky-col">Uraian</th>
              <th colspan="4" class="col-header-group">Rincian Perhitungan</th>
              <th v-for="month in months" :key="month.value" colspan="2" class="col-month-header">
                {{ month.fullLabel }}
              </th>
            </tr>
            <tr>
              <th class="col-volume">Volume</th>
              <th class="col-satuan">Satuan</th>
              <th class="col-harga">Harga</th>
              <th class="col-jumlah">Jumlah</th>
              <th v-for="month in months" :key="`vol-${month.value}`" class="col-input">Vol</th>
              <th v-for="month in months" :key="`amt-${month.value}`" class="col-input">Jumlah</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in editableItems" :key="item.id" :class="{ 'row-changed': changedItems.has(item.id) }">
              <td class="col-code sticky-col">{{ item.code }}</td>
              <td class="col-uraian sticky-col">
                <div class="item-name">{{ item.name }}</div>
                <div v-if="item.group_name" class="item-meta">{{ item.group_name }}</div>
              </td>
              <td class="col-volume">{{ item.volume }}</td>
              <td class="col-satuan">{{ item.unit }}</td>
              <td class="col-harga">{{ formatCurrency(item.unit_price) }}</td>
              <td class="col-jumlah">{{ formatCurrency(item.total_budget) }}</td>

              <!-- Monthly inputs - Volume -->
              <template v-for="month in months" :key="`input-vol-${item.id}-${month.value}`">
                <td class="col-input">
                  <NInputNumber
                    :value="getMonthPlan(item, month.value).planned_volume || null"
                    :min="0"
                    :precision="2"
                    size="small"
                    placeholder="0"
                    :show-button="false"
                    @update:value="(v) => onVolumeChange(item, month.value, v)"
                  />
                </td>
              </template>

              <!-- Monthly inputs - Amount -->
              <template v-for="month in months" :key="`input-amt-${item.id}-${month.value}`">
                <td class="col-input col-amount-input">
                  <NInputNumber
                    :value="getMonthPlan(item, month.value).planned_amount || null"
                    :min="0"
                    size="small"
                    placeholder="0"
                    :show-button="false"
                    @update:value="(v) => onAmountChange(item, month.value, v)"
                  />
                </td>
              </template>
            </tr>
          </tbody>
          <tfoot>
            <tr class="total-row">
              <td colspan="2" class="sticky-col text-right font-bold">TOTAL</td>
              <td colspan="4" class="col-jumlah font-bold">{{ formatCurrency(grandTotal.budget) }}</td>
              <template v-for="month in months" :key="`total-vol-${month.value}`">
                <td class="col-input font-medium">{{ (monthlyTotals[month.value]?.volume || 0).toFixed(2) }}</td>
              </template>
              <template v-for="month in months" :key="`total-amt-${month.value}`">
                <td class="col-input col-amount-input font-medium">{{ formatCurrency(monthlyTotals[month.value]?.amount || 0) }}</td>
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
  border: 1px solid #e5e7eb;
  border-radius: 8px;
}

.planning-grid {
  width: max-content;
  min-width: 100%;
  border-collapse: collapse;
  font-size: 12px;
}

.planning-grid thead {
  background: #1e3a5f;
  color: white;
  position: sticky;
  top: 0;
  z-index: 20;
}

.planning-grid th {
  padding: 8px 10px;
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

.planning-grid thead .sticky-col {
  background: #1e3a5f;
  z-index: 25;
}

.planning-grid .col-code.sticky-col {
  left: 0;
  min-width: 140px;
}

.planning-grid .col-uraian.sticky-col {
  left: 140px;
  min-width: 200px;
  max-width: 250px;
}

.planning-grid .col-code {
  font-family: 'Courier New', monospace;
  font-size: 11px;
  white-space: nowrap;
}

.planning-grid .col-uraian {
  text-align: left;
}

.item-name {
  font-weight: 500;
  font-size: 12px;
}

.item-meta {
  font-size: 10px;
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
  width: 100px;
  text-align: right;
  font-family: 'Courier New', monospace;
  font-size: 11px;
}

.planning-grid .col-header-group {
  background: #2d4a6f;
}

.planning-grid .col-month-header {
  background: #3b5998;
  min-width: 140px;
}

.planning-grid .col-input {
  width: 70px;
  padding: 2px 4px;
}

.planning-grid .col-amount-input {
  width: 90px;
}

.planning-grid .col-input :deep(.n-input-number) {
  width: 100%;
}

.planning-grid .col-input :deep(.n-input__input-el) {
  text-align: right;
  font-size: 11px;
  padding: 2px 4px;
}

/* Row states */
.row-changed {
  background: #fef3c7 !important;
}

.planning-grid tbody tr:hover {
  background: #f3f4f6;
}

.planning-grid tbody tr:hover.row-changed {
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
  padding: 10px 8px;
  border-top: 2px solid #1e3a5f;
  font-weight: 600;
}

/* Alternating month columns */
.planning-grid tbody td:nth-child(n+7):nth-child(-n+18) {
  background: #f8fafc;
}

.planning-grid tbody td:nth-child(n+19) {
  background: #fff;
}
</style>
