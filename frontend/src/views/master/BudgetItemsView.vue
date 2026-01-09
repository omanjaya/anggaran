<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import {
  NCard, NSelect, NSpin, NEmpty, NButton, NIcon, NTag,
  NTable, NModal
} from 'naive-ui'
import { EyeOutline } from '@vicons/ionicons5'
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
const subActivities = ref<SubActivity[]>([])
const budgetItems = ref<BudgetItem[]>([])
const selectedSubActivityId = ref<number | null>(null)
const selectedSubActivity = ref<SubActivity | null>(null)

// Modal for showing details
const showDetailModal = ref(false)
const selectedBudgetItem = ref<BudgetItem | null>(null)

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
        per_page: 500
      }
    })
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

function openDetailModal(item: BudgetItem) {
  selectedBudgetItem.value = item
  showDetailModal.value = true
}

function getIndentStyle(item: BudgetItem) {
  const level = item.level || 1
  return { paddingLeft: `${(level - 1) * 20}px` }
}

function getRowClass(item: BudgetItem) {
  const level = item.level || 1
  if (item.is_detail_code) return 'row-detail'
  if (level === 1) return 'row-level-1'
  if (level === 2) return 'row-level-2'
  if (level === 3) return 'row-level-3'
  return ''
}

function hasDetails(item: BudgetItem) {
  return item.is_detail_code && item.details && item.details.length > 0
}

onMounted(() => {
  fetchSubActivities()
})
</script>

<template>
  <div class="budget-items-view">
    <!-- Header -->
    <div class="mb-6">
      <h1 class="text-2xl font-bold text-gray-800">Item Anggaran</h1>
      <p class="text-gray-500 mt-1">Kelola data item anggaran berdasarkan Sub Kegiatan</p>
    </div>

    <!-- Filter Card -->
    <NCard class="mb-6">
      <div class="flex items-center gap-4">
        <div class="flex-1">
          <label class="block text-sm font-medium text-gray-700 mb-1">Pilih Sub Kegiatan</label>
          <NSelect
            v-model:value="selectedSubActivityId"
            :options="subActivityOptions"
            placeholder="Pilih Sub Kegiatan untuk melihat item anggaran"
            filterable
            clearable
            size="large"
            @update:value="onSubActivityChange"
          />
        </div>
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
      <NEmpty description="Pilih Sub Kegiatan untuk melihat item anggaran" />
    </NCard>

    <NCard v-else-if="budgetItems.length === 0">
      <NEmpty description="Tidak ada item anggaran untuk Sub Kegiatan ini" />
    </NCard>

    <!-- Budget Items Table -->
    <NCard v-else title="Rincian Belanja" class="budget-table-card">
      <template #header-extra>
        <NTag type="info">{{ budgetItems.length }} item</NTag>
      </template>

      <div class="budget-table-wrapper">
        <table class="budget-table">
          <thead>
            <tr>
              <th class="col-code">Kode Rekening</th>
              <th class="col-name">Uraian</th>
              <th class="col-amount">Jumlah (Rp)</th>
              <th class="col-action">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <template v-for="item in sortedBudgetItems" :key="item.id">
              <!-- Main row -->
              <tr :class="getRowClass(item)">
                <td class="col-code">
                  <div :style="getIndentStyle(item)">
                    {{ item.code }}
                  </div>
                </td>
                <td class="col-name">
                  <div class="item-name">{{ item.name }}</div>
                  <div v-if="item.group_name" class="item-meta">[ # ] {{ item.group_name }}</div>
                  <div v-if="item.sumber_dana" class="item-meta">Sumber Dana: {{ item.sumber_dana }}</div>
                </td>
                <td class="col-amount">{{ formatCurrency(item.total_budget) }}</td>
                <td class="col-action">
                  <NButton
                    v-if="hasDetails(item)"
                    size="tiny"
                    quaternary
                    @click="openDetailModal(item)"
                  >
                    <template #icon>
                      <NIcon><EyeOutline /></NIcon>
                    </template>
                  </NButton>
                </td>
              </tr>
            </template>
          </tbody>
        </table>
      </div>
    </NCard>

    <!-- Detail Modal -->
    <NModal
      v-model:show="showDetailModal"
      preset="card"
      :title="selectedBudgetItem?.code + ' - ' + selectedBudgetItem?.name"
      style="width: 900px; max-width: 95vw;"
    >
      <template v-if="selectedBudgetItem">
        <!-- Item Info -->
        <div class="mb-6 p-4 bg-gray-50 rounded-lg">
          <div class="grid grid-cols-2 gap-4 text-sm">
            <div>
              <span class="text-gray-500">Group:</span>
              <span class="ml-2 font-medium">{{ selectedBudgetItem.group_name || '-' }}</span>
            </div>
            <div>
              <span class="text-gray-500">Sumber Dana:</span>
              <span class="ml-2 font-medium">{{ selectedBudgetItem.sumber_dana || '-' }}</span>
            </div>
            <div class="col-span-2">
              <span class="text-gray-500">Total:</span>
              <span class="ml-2 font-bold text-blue-600 text-lg">{{ formatCurrency(selectedBudgetItem.total_budget) }}</span>
            </div>
          </div>
        </div>

        <!-- Spesifikasi Table -->
        <div v-if="selectedBudgetItem.details && selectedBudgetItem.details.length > 0">
          <h4 class="font-semibold mb-3 text-gray-700">Rincian Spesifikasi</h4>
          <div class="spec-table-wrapper">
            <table class="spec-table">
              <thead>
                <tr>
                  <th class="w-12">No</th>
                  <th>Spesifikasi</th>
                  <th class="w-24 text-right">Volume</th>
                  <th class="w-24 text-center">Satuan</th>
                  <th class="w-36 text-right">Harga Satuan</th>
                  <th class="w-40 text-right">Jumlah</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(detail, index) in selectedBudgetItem.details" :key="detail.id">
                  <td class="text-center">{{ index + 1 }}</td>
                  <td>{{ detail.description }}</td>
                  <td class="text-right">{{ detail.volume }}</td>
                  <td class="text-center">{{ detail.unit }}</td>
                  <td class="text-right">{{ formatCurrency(detail.unit_price) }}</td>
                  <td class="text-right font-medium">{{ formatCurrency(detail.amount) }}</td>
                </tr>
              </tbody>
              <tfoot>
                <tr>
                  <td colspan="5" class="text-right font-semibold">Total</td>
                  <td class="text-right font-bold text-blue-600">{{ formatCurrency(selectedBudgetItem.total_budget) }}</td>
                </tr>
              </tfoot>
            </table>
          </div>
        </div>
        <NEmpty v-else description="Tidak ada rincian spesifikasi" />
      </template>
    </NModal>
  </div>
</template>

<style scoped>
.budget-table-wrapper {
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  overflow: hidden;
}

.budget-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 14px;
}

.budget-table thead {
  background: #1e3a5f;
  color: white;
}

.budget-table th {
  padding: 12px 16px;
  text-align: left;
  font-weight: 600;
  border-right: 1px solid rgba(255,255,255,0.1);
}

.budget-table th:last-child {
  border-right: none;
}

.budget-table td {
  padding: 10px 16px;
  border-bottom: 1px solid #e5e7eb;
  border-right: 1px solid #e5e7eb;
  vertical-align: top;
}

.budget-table td:last-child {
  border-right: none;
}

.budget-table tbody tr:last-child td {
  border-bottom: none;
}

.budget-table .col-code {
  width: 180px;
  font-family: 'Monaco', 'Menlo', monospace;
  font-size: 13px;
  white-space: nowrap;
}

.budget-table .col-name {
  min-width: 300px;
}

.budget-table .col-amount {
  width: 160px;
  text-align: right;
  font-family: 'Monaco', 'Menlo', monospace;
  font-weight: 500;
}

.budget-table .col-action {
  width: 60px;
  text-align: center;
}

.item-name {
  font-weight: 500;
}

.item-meta {
  font-size: 12px;
  color: #6b7280;
  margin-top: 4px;
}

/* Row styling by level */
.row-level-1 {
  background: #dbeafe;
}

.row-level-1 .item-name {
  font-weight: 700;
  color: #1e40af;
}

.row-level-2 {
  background: #eff6ff;
}

.row-level-2 .item-name {
  font-weight: 600;
  color: #1d4ed8;
}

.row-level-3 {
  background: #f8fafc;
}

.row-detail {
  background: #f0fdf4;
}

.row-detail .item-name {
  color: #166534;
}

.budget-table tbody tr:hover {
  background: #f1f5f9 !important;
}

/* Spec table */
.spec-table-wrapper {
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  overflow: hidden;
}

.spec-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 13px;
}

.spec-table thead {
  background: #f3f4f6;
}

.spec-table th {
  padding: 10px 12px;
  text-align: left;
  font-weight: 600;
  border-bottom: 1px solid #e5e7eb;
}

.spec-table td {
  padding: 10px 12px;
  border-bottom: 1px solid #e5e7eb;
}

.spec-table tbody tr:last-child td {
  border-bottom: none;
}

.spec-table tfoot {
  background: #f9fafb;
}

.spec-table tfoot td {
  padding: 12px;
  border-top: 2px solid #e5e7eb;
}
</style>
