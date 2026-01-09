<script setup lang="ts">
import { ref, onMounted, computed, watch } from 'vue'
import { NButton, NModal, NSpace, NIcon, NTag, NEmpty } from 'naive-ui'
import { EyeOutline, BuildOutline } from '@vicons/ionicons5'
import { PageHeader, PageCard, StatCard, LoadingSpinner, FormSelect } from '@/components/ui'
import { useFormat, useMessage } from '@/composables'
import api from '@/services/api'

interface SubActivity {
  id: number
  code: string
  name: string
  total_budget: number
  nomor_dpa?: string
}

interface BudgetItem {
  id: number
  code: string
  name: string
  group_name?: string
  unit: string
  unit_price: number
  volume: number
  total_budget: number
  is_detail_code?: boolean
  monthly_plans?: MonthlyPlan[]
}

interface MonthlyPlan {
  id?: number
  month: number
  planned_volume: number
  planned_amount: number
}

interface PlgkData {
  sub_activity: SubActivity
  year: number
  budget_items: BudgetItem[]
  summary: {
    total_budget: number
    total_planned: number
  }
}

interface PreviewData {
  sub_activity: SubActivity
  year: number
  method: string
  items: Array<{
    budget_item: BudgetItem
    monthly_plans: MonthlyPlan[]
  }>
  summary: {
    total_items: number
    total_budget: number
  }
}

const { formatCurrency } = useFormat()
const message = useMessage()

const loading = ref(false)
const subActivities = ref<SubActivity[]>([])
const selectedSubActivityId = ref<number | null>(null)
const selectedYear = ref(new Date().getFullYear())
const selectedMethod = ref('equal')
const availableYears = ref<number[]>([])
const methods = ref<Array<{ value: string; label: string; description: string }>>([])

const plgkData = ref<PlgkData | null>(null)
const previewData = ref<PreviewData | null>(null)
const showPreview = ref(false)
const isGenerated = ref(false)

const monthNames = [
  'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
  'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
]

const subActivityOptions = computed(() =>
  subActivities.value.map((sa) => ({ label: `${sa.code} - ${sa.name}`, value: sa.id }))
)

const yearOptions = computed(() =>
  availableYears.value.map((y) => ({ label: String(y), value: y }))
)

const methodOptions = computed(() =>
  methods.value.map((m) => ({ label: m.label, value: m.value }))
)

const selectedMethodDescription = computed(() =>
  methods.value.find((m) => m.value === selectedMethod.value)?.description || ''
)

// Fetch sub-activities
async function fetchSubActivities() {
  try {
    const response = await api.get('/sub-activities', { params: { all: true } })
    const data = response.data.data
    subActivities.value = Array.isArray(data) ? data : (data?.data || [])
  } catch (error) {
    console.error('Failed to fetch sub-activities:', error)
  }
}

// Fetch methods and years
async function fetchOptions() {
  try {
    const [methodsRes, yearsRes] = await Promise.all([
      api.get('/plgk/methods'),
      api.get('/plgk/years'),
    ])
    methods.value = methodsRes.data.data
    availableYears.value = yearsRes.data.data
  } catch (error) {
    console.error('Failed to fetch options:', error)
  }
}

// Fetch current PLGK data
async function fetchPlgkData() {
  if (!selectedSubActivityId.value) return

  loading.value = true
  try {
    const response = await api.get(`/plgk/${selectedSubActivityId.value}`, {
      params: { year: selectedYear.value },
    })
    plgkData.value = response.data.data
    isGenerated.value = plgkData.value?.budget_items?.some(
      (item) => item.monthly_plans && item.monthly_plans.length > 0
    ) || false
  } catch (error) {
    console.error('Failed to fetch PLGK data:', error)
    plgkData.value = null
  } finally {
    loading.value = false
  }
}

// Preview PLGK generation
async function previewGeneration() {
  if (!selectedSubActivityId.value) return

  loading.value = true
  try {
    const response = await api.post(`/plgk/${selectedSubActivityId.value}/preview`, {
      method: selectedMethod.value,
      year: selectedYear.value,
    })
    previewData.value = response.data.data
    showPreview.value = true
  } catch (error: any) {
    message.error(error.response?.data?.message || 'Failed to preview PLGK generation')
  } finally {
    loading.value = false
  }
}

// Generate PLGK
async function generatePlgk() {
  if (!selectedSubActivityId.value) return

  const confirmed = await message.confirm('Apakah Anda yakin ingin generate PLGK? Data yang sudah ada akan ditimpa.')
  if (!confirmed) return

  loading.value = true
  try {
    await api.post(`/plgk/${selectedSubActivityId.value}/generate`, {
      method: selectedMethod.value,
      year: selectedYear.value,
    })

    showPreview.value = false
    await fetchPlgkData()
    message.success('PLGK berhasil di-generate!')
  } catch (error: any) {
    message.error(error.response?.data?.message || 'Failed to generate PLGK')
  } finally {
    loading.value = false
  }
}

// Watch for changes
watch([selectedSubActivityId, selectedYear], () => {
  if (selectedSubActivityId.value) {
    fetchPlgkData()
  }
})

// Calculate totals for an item
function calculateItemTotal(plans: MonthlyPlan[] | undefined) {
  if (!plans) return { volume: 0, amount: 0 }
  return {
    volume: plans.reduce((sum, p) => sum + p.planned_volume, 0),
    amount: plans.reduce((sum, p) => sum + p.planned_amount, 0),
  }
}

onMounted(async () => {
  await Promise.all([fetchSubActivities(), fetchOptions()])
})
</script>

<template>
  <div>
    <PageHeader title="PLGK Generator" subtitle="Generate Rencana Penarikan Keuangan per Bulan dari DPA" />

    <!-- Selection Form -->
    <PageCard class="mb-6">
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <FormSelect
          v-model="selectedSubActivityId"
          label="Sub Kegiatan"
          placeholder="Pilih Sub Kegiatan"
          :options="subActivityOptions"
          filterable
        />
        <FormSelect
          v-model="selectedYear"
          label="Tahun"
          :options="yearOptions"
        />
        <div>
          <FormSelect
            v-model="selectedMethod"
            label="Metode Alokasi"
            :options="methodOptions"
          />
          <p class="text-xs text-gray-500 mt-1">{{ selectedMethodDescription }}</p>
        </div>
      </div>
      <div class="mt-4 flex justify-end gap-3">
        <NButton :disabled="!selectedSubActivityId || loading" @click="previewGeneration">
          <template #icon><NIcon><EyeOutline /></NIcon></template>
          Preview
        </NButton>
        <NButton type="primary" :disabled="!selectedSubActivityId || loading" @click="generatePlgk">
          <template #icon><NIcon><BuildOutline /></NIcon></template>
          Generate PLGK
        </NButton>
      </div>
    </PageCard>

    <LoadingSpinner v-if="loading" />

    <!-- Current PLGK Data -->
    <template v-else-if="plgkData">
      <!-- Summary Cards -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <StatCard label="Total Anggaran" :value="formatCurrency(plgkData.sub_activity.total_budget)" />
        <StatCard label="Total Terencana" :value="formatCurrency(plgkData.summary.total_planned)" variant="success" />
        <PageCard>
          <p class="text-sm font-medium text-gray-600">Status</p>
          <NTag :type="isGenerated ? 'success' : 'warning'" class="mt-1">
            {{ isGenerated ? 'Sudah Generate' : 'Belum Generate' }}
          </NTag>
        </PageCard>
      </div>

      <!-- PLGK Table -->
      <PageCard :title="`PLGK ${plgkData.sub_activity.code} - Tahun ${plgkData.year}`" :padding="false">
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-3 py-2 text-left font-medium text-gray-700 sticky left-0 bg-gray-50 z-10">Item Belanja</th>
                <th
                  v-for="(month, idx) in monthNames"
                  :key="idx"
                  class="px-2 py-2 text-center font-medium text-gray-700 min-w-[80px]"
                >
                  {{ month.substring(0, 3) }}
                </th>
                <th class="px-3 py-2 text-right font-medium text-gray-700 sticky right-0 bg-gray-50 z-10">Total</th>
              </tr>
            </thead>
            <tbody class="divide-y">
              <template v-for="item in plgkData.budget_items" :key="item.id">
                <tr class="hover:bg-gray-50">
                  <td class="px-3 py-2 sticky left-0 bg-white z-10">
                    <div class="font-mono text-xs text-gray-500">{{ item.code }}</div>
                    <div class="text-sm">{{ item.name }}</div>
                    <div class="text-xs text-gray-500">
                      {{ item.volume }} {{ item.unit }} x {{ formatCurrency(item.unit_price) }}
                    </div>
                  </td>
                  <td
                    v-for="monthNum in 12"
                    :key="monthNum"
                    class="px-2 py-2 text-center text-xs"
                  >
                    <template v-if="item.monthly_plans?.find((p) => p.month === monthNum)">
                      <div class="font-medium">
                        {{ formatCurrency(item.monthly_plans.find((p) => p.month === monthNum)?.planned_amount || 0) }}
                      </div>
                      <div class="text-gray-400">
                        {{ item.monthly_plans.find((p) => p.month === monthNum)?.planned_volume || 0 }} {{ item.unit }}
                      </div>
                    </template>
                    <span v-else class="text-gray-300">-</span>
                  </td>
                  <td class="px-3 py-2 text-right sticky right-0 bg-white z-10">
                    <div class="font-medium text-sm">
                      {{ formatCurrency(calculateItemTotal(item.monthly_plans).amount) }}
                    </div>
                    <div class="text-xs text-gray-500">
                      {{ calculateItemTotal(item.monthly_plans).volume }} {{ item.unit }}
                    </div>
                  </td>
                </tr>
              </template>
              <tr v-if="plgkData.budget_items.length === 0">
                <td :colspan="14" class="px-4 py-8 text-center text-gray-500">
                  Belum ada item belanja untuk sub kegiatan ini
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </PageCard>
    </template>

    <!-- Empty State -->
    <PageCard v-else-if="!loading && !selectedSubActivityId">
      <NEmpty description="Pilih sub kegiatan untuk melihat atau generate PLGK" />
    </PageCard>

    <!-- Preview Modal -->
    <NModal v-model:show="showPreview" preset="card" :title="`Preview PLGK - ${previewData?.sub_activity.code}`" style="width: 90vw; max-width: 1200px">
      <!-- Preview Summary -->
      <div class="mb-4 p-4 bg-blue-50 rounded-lg">
        <div class="grid grid-cols-3 gap-4 text-sm">
          <div>
            <span class="text-gray-600">Metode:</span>
            <span class="font-medium ml-2">{{ methods.find((m) => m.value === previewData?.method)?.label }}</span>
          </div>
          <div>
            <span class="text-gray-600">Tahun:</span>
            <span class="font-medium ml-2">{{ previewData?.year }}</span>
          </div>
          <div>
            <span class="text-gray-600">Total Item:</span>
            <span class="font-medium ml-2">{{ previewData?.summary.total_items }}</span>
          </div>
        </div>
      </div>

      <!-- Preview Table -->
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-3 py-2 text-left font-medium text-gray-700">Item Belanja</th>
              <th
                v-for="(month, idx) in monthNames"
                :key="idx"
                class="px-2 py-2 text-center font-medium text-gray-700 min-w-[70px]"
              >
                {{ month.substring(0, 3) }}
              </th>
            </tr>
          </thead>
          <tbody class="divide-y">
            <tr v-for="item in previewData?.items" :key="item.budget_item.id" class="hover:bg-gray-50">
              <td class="px-3 py-2">
                <div class="font-mono text-xs text-gray-500">{{ item.budget_item.code }}</div>
                <div class="text-sm">{{ item.budget_item.name }}</div>
              </td>
              <td
                v-for="plan in item.monthly_plans"
                :key="plan.month"
                class="px-2 py-2 text-center text-xs"
              >
                <div class="font-medium">{{ formatCurrency(plan.planned_amount) }}</div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <template #footer>
        <NSpace justify="end">
          <NButton @click="showPreview = false">Tutup</NButton>
          <NButton type="primary" :loading="loading" @click="generatePlgk">
            Konfirmasi & Generate
          </NButton>
        </NSpace>
      </template>
    </NModal>
  </div>
</template>
