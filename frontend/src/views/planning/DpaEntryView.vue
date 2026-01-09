<script setup lang="ts">
import { ref, onMounted, computed, watch } from 'vue'
import { useRouter } from 'vue-router'
import {
  NButton,
  NInput,
  NSteps,
  NStep,
  NSpace,
  NIcon,
  NUpload,
  NUploadDragger,
  NDataTable,
} from 'naive-ui'
import type { DataTableColumns, UploadFileInfo } from 'naive-ui'
import { AddOutline, ChevronBackOutline, ChevronForwardOutline, CheckmarkCircleOutline, CloudUploadOutline, TrashOutline } from '@vicons/ionicons5'
import { PageHeader, PageCard, FormInput, FormSelect, FormInputNumber } from '@/components/ui'
import { useFormat, useMessage } from '@/composables'
import api from '@/services/api'

interface Program {
  id: number
  code: string
  name: string
  year: number
}

interface Activity {
  id: number
  program_id: number
  code: string
  name: string
}

interface SubActivity {
  id: number
  activity_id: number
  category: string
  name: string
  budget_current_year: number
}

interface BudgetItem {
  id?: number
  sub_activity_id?: number
  account_code: string
  description: string
  unit: string
  unit_price: number
  total_volume: number
  total_amount: number
}

interface Indicator {
  name: string
  target: string
  unit: string
}

const router = useRouter()
const { formatCurrency } = useFormat()
const message = useMessage()

const loading = ref(false)
const currentStep = ref(1)

// Step 1: Basic Info
const programs = ref<Program[]>([])
const activities = ref<Activity[]>([])
const subActivities = ref<SubActivity[]>([])
const categories = ref<string[]>([])

const basicInfo = ref({
  program_id: null as number | null,
  activity_id: null as number | null,
  sub_activity_id: undefined as number | undefined,
  category: '',
  name: '',
  budget_current_year: 0,
})

// Step 2: Budget Items
const budgetItems = ref<BudgetItem[]>([])
const accountCodes = ref<Array<{ code: string; description: string }>>([])
const newItem = ref<BudgetItem>({
  account_code: '',
  description: '',
  unit: '',
  unit_price: 0,
  total_volume: 0,
  total_amount: 0,
})

// Step 3: Performance Indicators
const indicators = ref<Indicator[]>([{ name: '', target: '', unit: '' }])

// Step 4: Documents & Review
const fileList = ref<UploadFileInfo[]>([])
const notes = ref('')

// Options
const programOptions = computed(() =>
  programs.value.map((p) => ({ label: `${p.code} - ${p.name}`, value: p.id }))
)

const activityOptions = computed(() =>
  activities.value.map((a) => ({ label: `${a.code} - ${a.name}`, value: a.id }))
)

const subActivityOptions = computed(() => [
  { label: 'Buat Baru', value: undefined as number | undefined },
  ...subActivities.value.map((sa) => ({ label: `${sa.category} - ${sa.name}`, value: sa.id })),
])

const categoryOptions = computed(() =>
  categories.value.map((c) => ({ label: c, value: c }))
)

const accountCodeOptions = computed(() =>
  accountCodes.value.map((ac) => ({
    label: `${ac.code} - ${ac.description.substring(0, 40)}...`,
    value: ac.code,
  }))
)

// Computed
const totalBudget = computed(() => budgetItems.value.reduce((sum, item) => sum + item.total_amount, 0))
const budgetDifference = computed(() => basicInfo.value.budget_current_year - totalBudget.value)

const isStep1Valid = computed(() =>
  basicInfo.value.program_id && basicInfo.value.category && basicInfo.value.name && basicInfo.value.budget_current_year > 0
)
const isStep2Valid = computed(() => budgetItems.value.length > 0 && Math.abs(budgetDifference.value) < 1)
const isStep3Valid = computed(() => indicators.value.every((i) => i.name && i.target && i.unit))

// Fetch data
async function fetchPrograms() {
  try {
    const response = await api.get('/programs', { params: { all: true } })
    programs.value = response.data.data
  } catch (error) {
    console.error('Failed to fetch programs:', error)
  }
}

async function fetchActivities(programId: number) {
  try {
    const response = await api.get('/activities', { params: { program_id: programId, all: true } })
    activities.value = response.data.data
  } catch (error) {
    console.error('Failed to fetch activities:', error)
  }
}

async function fetchSubActivities(activityId: number) {
  try {
    const response = await api.get('/sub-activities', { params: { activity_id: activityId, all: true } })
    subActivities.value = response.data.data
  } catch (error) {
    console.error('Failed to fetch sub-activities:', error)
  }
}

async function fetchCategories() {
  try {
    const response = await api.get('/programs/categories')
    categories.value = response.data.data
  } catch (error) {
    console.error('Failed to fetch categories:', error)
  }
}

async function fetchAccountCodes() {
  try {
    const response = await api.get('/account-codes/leaf-nodes')
    accountCodes.value = response.data.data
  } catch (error) {
    console.error('Failed to fetch account codes:', error)
  }
}

// Watchers
watch(() => basicInfo.value.program_id, async (newVal) => {
  if (newVal) {
    await fetchActivities(newVal)
    basicInfo.value.activity_id = null
  }
})

watch(() => basicInfo.value.activity_id, async (newVal) => {
  if (newVal) {
    await fetchSubActivities(newVal)
  }
})

watch(() => basicInfo.value.sub_activity_id, (newVal) => {
  if (newVal) {
    const sa = subActivities.value.find((s) => s.id === newVal)
    if (sa) {
      basicInfo.value.category = sa.category
      basicInfo.value.name = sa.name
      basicInfo.value.budget_current_year = sa.budget_current_year
    }
  }
})

watch([() => newItem.value.unit_price, () => newItem.value.total_volume], () => {
  newItem.value.total_amount = newItem.value.unit_price * newItem.value.total_volume
})

// Budget item functions
function addBudgetItem() {
  if (!newItem.value.account_code || !newItem.value.description) {
    message.warning('Lengkapi kode rekening dan deskripsi')
    return
  }
  budgetItems.value.push({ ...newItem.value })
  newItem.value = { account_code: '', description: '', unit: '', unit_price: 0, total_volume: 0, total_amount: 0 }
}

// Indicator functions
function addIndicator() {
  indicators.value.push({ name: '', target: '', unit: '' })
}

function removeIndicator(index: number) {
  if (indicators.value.length > 1) {
    indicators.value.splice(index, 1)
  }
}

// Navigation
function nextStep() {
  if (currentStep.value < 4) currentStep.value++
}

function prevStep() {
  if (currentStep.value > 1) currentStep.value--
}

// Save functions
async function saveAsDraft() {
  loading.value = true
  try {
    await saveData('DRAFT')
    message.success('DPA berhasil disimpan sebagai draft')
    router.push('/planning/dpa')
  } catch (error: any) {
    message.error(error.response?.data?.message || 'Gagal menyimpan draft')
  } finally {
    loading.value = false
  }
}

async function submitDpa() {
  const confirmed = await message.confirm('Apakah Anda yakin ingin submit DPA untuk approval?')
  if (!confirmed) return

  loading.value = true
  try {
    await saveData('SUBMITTED')
    message.success('DPA berhasil disubmit untuk approval')
    router.push('/planning/dpa')
  } catch (error: any) {
    message.error(error.response?.data?.message || 'Gagal submit DPA')
  } finally {
    loading.value = false
  }
}

async function saveData(_status: string) {
  let subActivityId = basicInfo.value.sub_activity_id

  const subActivityData = {
    activity_id: basicInfo.value.activity_id,
    category: basicInfo.value.category,
    name: basicInfo.value.name,
    budget_current_year: basicInfo.value.budget_current_year,
  }

  if (!subActivityId) {
    const saResponse = await api.post('/sub-activities', subActivityData)
    subActivityId = saResponse.data.data.id
  } else {
    await api.put(`/sub-activities/${subActivityId}`, subActivityData)
  }

  for (const item of budgetItems.value) {
    item.sub_activity_id = subActivityId
    if (item.id) {
      await api.put(`/budget-items/${item.id}`, item)
    } else {
      await api.post('/budget-items', item)
    }
  }

  if (fileList.value.length > 0) {
    const formData = new FormData()
    fileList.value.forEach((file) => {
      if (file.file) formData.append('documents[]', file.file)
    })
    formData.append('sub_activity_id', String(subActivityId))
  }
}

const budgetItemColumns: DataTableColumns<BudgetItem> = [
  { title: 'Kode', key: 'account_code', width: 120, render: (row) => row.account_code },
  { title: 'Deskripsi', key: 'description', ellipsis: { tooltip: true } },
  { title: 'Volume', key: 'total_volume', width: 100, align: 'center', render: (row) => `${row.total_volume} ${row.unit}` },
  { title: 'Harga Satuan', key: 'unit_price', width: 140, align: 'right', render: (row) => formatCurrency(row.unit_price) },
  { title: 'Jumlah', key: 'total_amount', width: 150, align: 'right', render: (row) => formatCurrency(row.total_amount) },
]

onMounted(async () => {
  await Promise.all([fetchPrograms(), fetchCategories(), fetchAccountCodes()])
})
</script>

<template>
  <div class="max-w-5xl mx-auto">
    <PageHeader title="Entry DPA" subtitle="Input Dokumen Pelaksanaan Anggaran" />

    <!-- Step Indicator -->
    <PageCard class="mb-6">
      <NSteps :current="currentStep" size="small">
        <NStep title="Info Dasar" />
        <NStep title="Item Belanja" />
        <NStep title="Indikator" />
        <NStep title="Review" />
      </NSteps>
    </PageCard>

    <!-- Step Content -->
    <PageCard>
      <!-- Step 1: Basic Info -->
      <div v-show="currentStep === 1" class="space-y-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">Informasi Dasar</h3>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <FormSelect
            v-model="basicInfo.program_id"
            label="Program"
            placeholder="Pilih Program"
            :options="programOptions"
            filterable
          />
          <FormSelect
            v-model="basicInfo.activity_id"
            label="Kegiatan"
            placeholder="Pilih Kegiatan"
            :options="activityOptions"
            :disabled="!basicInfo.program_id"
            filterable
          />
          <FormSelect
            v-model="basicInfo.sub_activity_id"
            label="Sub Kegiatan (Opsional)"
            placeholder="Buat Baru"
            :options="subActivityOptions"
            :disabled="!basicInfo.activity_id"
            filterable
          />
          <FormSelect
            v-model="basicInfo.category"
            label="Kategori"
            placeholder="Pilih Kategori"
            :options="categoryOptions"
          />
          <div class="md:col-span-2">
            <FormInput v-model="basicInfo.name" label="Nama Sub Kegiatan" placeholder="Nama sub kegiatan" />
          </div>
          <div>
            <FormInputNumber
              v-model="basicInfo.budget_current_year"
              label="Pagu Anggaran"
              :min="0"
              :format="(v) => v ? `Rp ${v.toLocaleString('id-ID')}` : ''"
              :parse="(v) => Number(v.replace(/[^0-9]/g, ''))"
            />
          </div>
        </div>
      </div>

      <!-- Step 2: Budget Items -->
      <div v-show="currentStep === 2" class="space-y-6">
        <div class="flex items-center justify-between mb-4">
          <h3 class="text-lg font-semibold text-gray-800">Item Belanja</h3>
          <div class="text-right">
            <div class="text-sm text-gray-600">Pagu: {{ formatCurrency(basicInfo.budget_current_year) }}</div>
            <div
              :class="[
                'text-sm font-medium',
                budgetDifference === 0 ? 'text-green-600' : budgetDifference > 0 ? 'text-yellow-600' : 'text-red-600',
              ]"
            >
              Selisih: {{ formatCurrency(budgetDifference) }}
            </div>
          </div>
        </div>

        <!-- Add New Item Form -->
        <div class="bg-gray-50 rounded-lg p-4">
          <h4 class="font-medium text-gray-700 mb-3">Tambah Item Belanja</h4>
          <div class="grid grid-cols-1 md:grid-cols-6 gap-3">
            <div class="md:col-span-2">
              <FormSelect v-model="newItem.account_code" label="Kode Rekening" placeholder="Pilih Kode" :options="accountCodeOptions" filterable />
            </div>
            <div class="md:col-span-2">
              <FormInput v-model="newItem.description" label="Deskripsi" placeholder="Uraian belanja" />
            </div>
            <div>
              <FormInput v-model="newItem.unit" label="Satuan" placeholder="rim, buah" />
            </div>
            <div>
              <FormInputNumber v-model="newItem.total_volume" label="Volume" :min="0" :step="0.01" />
            </div>
            <div>
              <FormInputNumber v-model="newItem.unit_price" label="Harga Satuan" :min="0" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Jumlah</label>
              <div class="px-3 py-2 bg-gray-100 rounded text-sm font-medium">
                {{ formatCurrency(newItem.total_amount) }}
              </div>
            </div>
          </div>
          <div class="mt-3 flex justify-end">
            <NButton type="primary" @click="addBudgetItem">
              <template #icon><NIcon><AddOutline /></NIcon></template>
              Tambah Item
            </NButton>
          </div>
        </div>

        <!-- Item List -->
        <NDataTable :columns="budgetItemColumns" :data="budgetItems" :bordered="false" striped>
          <template #empty>
            <div class="py-8 text-center text-gray-500">Belum ada item belanja</div>
          </template>
        </NDataTable>

        <div class="bg-gray-50 rounded-lg p-3 flex justify-between items-center">
          <span class="font-medium">Total:</span>
          <span class="font-bold text-lg">{{ formatCurrency(totalBudget) }}</span>
        </div>
      </div>

      <!-- Step 3: Indicators -->
      <div v-show="currentStep === 3" class="space-y-6">
        <div class="flex items-center justify-between mb-4">
          <h3 class="text-lg font-semibold text-gray-800">Indikator Kinerja</h3>
          <NButton @click="addIndicator">
            <template #icon><NIcon><AddOutline /></NIcon></template>
            Tambah Indikator
          </NButton>
        </div>

        <div v-for="(indicator, index) in indicators" :key="index" class="border rounded-lg p-4">
          <div class="flex items-start gap-4">
            <div class="flex-1 grid grid-cols-1 md:grid-cols-3 gap-4">
              <FormInput v-model="indicator.name" label="Nama Indikator" placeholder="Contoh: Jumlah dokumen terverifikasi" />
              <FormInput v-model="indicator.target" label="Target" placeholder="Contoh: 100" />
              <FormInput v-model="indicator.unit" label="Satuan" placeholder="Contoh: dokumen, %" />
            </div>
            <NButton v-if="indicators.length > 1" quaternary type="error" @click="removeIndicator(index)" class="mt-6">
              <template #icon><NIcon><TrashOutline /></NIcon></template>
            </NButton>
          </div>
        </div>
      </div>

      <!-- Step 4: Review -->
      <div v-show="currentStep === 4" class="space-y-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">Review & Submit</h3>

        <!-- Summary -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div class="bg-gray-50 rounded-lg p-4">
            <h4 class="font-medium text-gray-700 mb-3">Informasi Dasar</h4>
            <dl class="space-y-2 text-sm">
              <div class="flex justify-between">
                <dt class="text-gray-600">Kategori:</dt>
                <dd class="font-medium">{{ basicInfo.category }}</dd>
              </div>
              <div class="flex justify-between">
                <dt class="text-gray-600">Nama:</dt>
                <dd class="font-medium">{{ basicInfo.name }}</dd>
              </div>
              <div class="flex justify-between">
                <dt class="text-gray-600">Pagu Anggaran:</dt>
                <dd class="font-medium text-blue-600">{{ formatCurrency(basicInfo.budget_current_year) }}</dd>
              </div>
            </dl>
          </div>
          <div class="bg-gray-50 rounded-lg p-4">
            <h4 class="font-medium text-gray-700 mb-3">Ringkasan Belanja</h4>
            <dl class="space-y-2 text-sm">
              <div class="flex justify-between">
                <dt class="text-gray-600">Jumlah Item:</dt>
                <dd class="font-medium">{{ budgetItems.length }} item</dd>
              </div>
              <div class="flex justify-between">
                <dt class="text-gray-600">Total Belanja:</dt>
                <dd class="font-medium">{{ formatCurrency(totalBudget) }}</dd>
              </div>
              <div class="flex justify-between">
                <dt class="text-gray-600">Selisih:</dt>
                <dd :class="['font-medium', budgetDifference === 0 ? 'text-green-600' : 'text-red-600']">
                  {{ formatCurrency(budgetDifference) }}
                </dd>
              </div>
            </dl>
          </div>
        </div>

        <!-- Documents Upload -->
        <div class="border rounded-lg p-4">
          <h4 class="font-medium text-gray-700 mb-3">Lampiran Dokumen (Opsional)</h4>
          <NUpload v-model:file-list="fileList" multiple accept=".pdf,.doc,.docx,.xls,.xlsx">
            <NUploadDragger>
              <div class="py-4">
                <NIcon size="48" :depth="3"><CloudUploadOutline /></NIcon>
                <p class="text-gray-600 mt-2">Klik untuk upload atau drop file di sini</p>
                <p class="text-xs text-gray-400">PDF, DOC, XLS (max 10MB)</p>
              </div>
            </NUploadDragger>
          </NUpload>
        </div>

        <!-- Notes -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Catatan (Opsional)</label>
          <NInput v-model:value="notes" type="textarea" :rows="3" placeholder="Catatan tambahan untuk DPA ini..." />
        </div>
      </div>

      <!-- Navigation Buttons -->
      <div class="mt-8 pt-6 border-t flex items-center justify-between">
        <NButton v-if="currentStep > 1" @click="prevStep">
          <template #icon><NIcon><ChevronBackOutline /></NIcon></template>
          Sebelumnya
        </NButton>
        <div v-else></div>

        <NSpace>
          <NButton v-if="currentStep === 4" :loading="loading" @click="saveAsDraft">Simpan Draft</NButton>
          <NButton
            v-if="currentStep < 4"
            type="primary"
            :disabled="(currentStep === 1 && !isStep1Valid) || (currentStep === 2 && !isStep2Valid) || (currentStep === 3 && !isStep3Valid)"
            @click="nextStep"
          >
            Selanjutnya
            <template #icon><NIcon><ChevronForwardOutline /></NIcon></template>
          </NButton>
          <NButton v-else type="success" :loading="loading" @click="submitDpa">
            <template #icon><NIcon><CheckmarkCircleOutline /></NIcon></template>
            Submit untuk Approval
          </NButton>
        </NSpace>
      </div>
    </PageCard>
  </div>
</template>
