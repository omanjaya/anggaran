<script setup lang="ts">
import { h, onMounted, ref, computed } from 'vue'
import { NSpace } from 'naive-ui'
import type { DataTableColumns } from 'naive-ui'
import { CrudPage, StatusBadge, TableActions, PageCard } from '@/components/ui'
import { FormInput, FormSelect, FormInputNumber, FormCheckbox } from '@/components/ui'
import { useCrud, useFormat } from '@/composables'
import { useAuthStore } from '@/stores/auth'
import api from '@/services/api'
import type { BudgetItem, SubActivity, Activity, Program } from '@/types/models'

interface BudgetItemForm {
  id: number
  sub_activity_id: number
  code: string
  name: string
  unit: string
  volume: number
  unit_price: number
  total_budget: number
  is_active: boolean
}

const authStore = useAuthStore()
const { formatCurrency, formatNumber } = useFormat()

// Permissions
const canCreate = computed(() => authStore.hasPermission('master.create'))
const canEdit = computed(() => authStore.hasPermission('master.update'))
const canDelete = computed(() => authStore.hasPermission('master.delete'))

// Master data
const programs = ref<Program[]>([])
const activities = ref<Activity[]>([])
const subActivities = ref<SubActivity[]>([])

// Filters
const selectedProgramId = ref<number | null>(null)
const selectedActivityId = ref<number | null>(null)
const selectedSubActivityId = ref<number | null>(null)

// Options
const programOptions = computed(() =>
  programs.value.map((p) => ({ label: `${p.code} - ${p.name}`, value: p.id }))
)

const filteredActivityOptions = computed(() => {
  const list = selectedProgramId.value
    ? activities.value.filter((a) => a.program_id === selectedProgramId.value)
    : activities.value
  return list.map((a) => ({ label: `${a.code} - ${a.name}`, value: a.id }))
})

const filteredSubActivityOptions = computed(() => {
  const list = selectedActivityId.value
    ? subActivities.value.filter((sa) => sa.activity_id === selectedActivityId.value)
    : subActivities.value
  return list.map((sa) => ({ label: `${sa.code} - ${sa.name}`, value: sa.id }))
})

const allSubActivityOptions = computed(() =>
  subActivities.value.map((sa) => ({ label: `${sa.code} - ${sa.name}`, value: sa.id }))
)

// Fetch master data
async function fetchPrograms() {
  try {
    const response = await api.get('/programs')
    programs.value = response.data.data
  } catch (err) {
    console.error('Failed to fetch programs:', err)
  }
}

async function fetchActivities() {
  try {
    const response = await api.get('/activities')
    activities.value = response.data.data
  } catch (err) {
    console.error('Failed to fetch activities:', err)
  }
}

async function fetchSubActivities() {
  try {
    const response = await api.get('/sub-activities')
    subActivities.value = response.data.data
  } catch (err) {
    console.error('Failed to fetch sub-activities:', err)
  }
}

function getSubActivityName(subActivityId: number) {
  return subActivities.value.find((sa) => sa.id === subActivityId)?.name || '-'
}

// Calculated total budget
const calculatedTotalBudget = computed(() => form.value.volume * form.value.unit_price)

const initialForm: BudgetItemForm = {
  id: 0,
  sub_activity_id: 0,
  code: '',
  name: '',
  unit: '',
  volume: 0,
  unit_price: 0,
  total_budget: 0,
  is_active: true,
}

const {
  loading,
  saving,
  data: budgetItems,
  showModal,
  isEditing,
  formError,
  form,
  fetchData,
  openCreateModal: baseOpenCreateModal,
  openEditModal,
  closeModal,
  deleteItem,
} = useCrud<BudgetItem, BudgetItemForm>({
  endpoint: '/budget-items',
  initialForm,
  mapToForm: (item) => ({
    id: item.id,
    sub_activity_id: item.sub_activity_id,
    code: item.code,
    name: item.name,
    unit: item.unit,
    volume: item.volume,
    unit_price: item.unit_price,
    total_budget: item.total_budget,
    is_active: item.is_active,
  }),
  deleteConfirmMessage: (item) => `Hapus item anggaran "${item.name}"?`,
})

// Custom save to calculate total_budget
async function saveBudgetItem() {
  saving.value = true
  formError.value = ''
  form.value.total_budget = calculatedTotalBudget.value

  try {
    if (isEditing.value) {
      await api.put(`/budget-items/${form.value.id}`, form.value)
    } else {
      await api.post('/budget-items', form.value)
    }
    closeModal()
    await fetchDataWithFilters()
  } catch (err: any) {
    formError.value = err.response?.data?.message || 'Gagal menyimpan data'
  } finally {
    saving.value = false
  }
}

function openCreateModal() {
  baseOpenCreateModal()
  if (selectedSubActivityId.value) {
    form.value.sub_activity_id = selectedSubActivityId.value
  }
}

// Filter handlers
function onProgramFilterChange() {
  selectedActivityId.value = null
  selectedSubActivityId.value = null
  fetchDataWithFilters()
}

function onActivityFilterChange() {
  selectedSubActivityId.value = null
  fetchDataWithFilters()
}

function onSubActivityFilterChange() {
  fetchDataWithFilters()
}

function fetchDataWithFilters() {
  const params: Record<string, any> = {}
  if (selectedSubActivityId.value) params.sub_activity_id = selectedSubActivityId.value
  fetchData(params)
}

const columns: DataTableColumns<BudgetItem> = [
  {
    title: 'Kode',
    key: 'code',
    width: 100,
    render: (row) => h('span', { class: 'font-medium' }, row.code),
  },
  {
    title: 'Nama Item',
    key: 'name',
    ellipsis: { tooltip: true },
  },
  {
    title: 'Sub Kegiatan',
    key: 'sub_activity_id',
    width: 180,
    ellipsis: { tooltip: true },
    render: (row) => getSubActivityName(row.sub_activity_id),
  },
  {
    title: 'Satuan',
    key: 'unit',
    width: 80,
    align: 'center',
  },
  {
    title: 'Volume',
    key: 'volume',
    width: 100,
    align: 'right',
    render: (row) => formatNumber(row.volume),
  },
  {
    title: 'Harga Satuan',
    key: 'unit_price',
    width: 140,
    align: 'right',
    render: (row) => formatCurrency(row.unit_price),
  },
  {
    title: 'Total',
    key: 'total_budget',
    width: 150,
    align: 'right',
    render: (row) => h('span', { class: 'font-medium' }, formatCurrency(row.total_budget)),
  },
  {
    title: 'Status',
    key: 'is_active',
    width: 80,
    align: 'center',
    render: (row) => h(StatusBadge, { status: row.is_active }),
  },
  {
    title: 'Aksi',
    key: 'actions',
    width: 80,
    align: 'center',
    render: (row) =>
      h(TableActions, {
        canEdit: canEdit.value,
        canDelete: canDelete.value,
        onEdit: () => openEditModal(row),
        onDelete: () => deleteItem(row),
      }),
  },
]

onMounted(() => {
  fetchPrograms()
  fetchActivities()
  fetchSubActivities()
  fetchData()
})
</script>

<template>
  <CrudPage
    title="Item Anggaran"
    subtitle="Kelola data item anggaran"
    :loading="loading"
    :data="budgetItems"
    :columns="columns"
    :show-modal="showModal"
    :modal-title="isEditing ? 'Edit Item Anggaran' : 'Tambah Item Anggaran'"
    :modal-loading="saving"
    add-button-text="Tambah Item"
    empty-text="Belum ada data item anggaran"
    :can-create="canCreate"
    :scroll-x="1100"
    @create="openCreateModal"
    @update:show-modal="showModal = $event"
    @submit="saveBudgetItem"
  >
    <!-- Filters -->
    <template #filters>
      <PageCard class="mb-6">
        <NSpace :size="16" wrap>
          <FormSelect
            v-model="selectedProgramId"
            label="Program"
            placeholder="Semua"
            :options="programOptions"
            clearable
            style="min-width: 220px"
            @update:model-value="onProgramFilterChange"
          />
          <FormSelect
            v-model="selectedActivityId"
            label="Kegiatan"
            placeholder="Semua"
            :options="filteredActivityOptions"
            clearable
            style="min-width: 220px"
            @update:model-value="onActivityFilterChange"
          />
          <FormSelect
            v-model="selectedSubActivityId"
            label="Sub Kegiatan"
            placeholder="Semua"
            :options="filteredSubActivityOptions"
            clearable
            style="min-width: 220px"
            @update:model-value="onSubActivityFilterChange"
          />
        </NSpace>
      </PageCard>
    </template>

    <!-- Form -->
    <template #form>
      <div v-if="formError" class="mb-4 p-3 bg-red-50 border border-red-200 text-red-700 rounded-md text-sm">
        {{ formError }}
      </div>

      <div class="space-y-4">
        <FormSelect
          v-model="form.sub_activity_id"
          label="Sub Kegiatan"
          placeholder="Pilih sub kegiatan"
          :options="allSubActivityOptions"
          filterable
          required
        />

        <div class="grid grid-cols-2 gap-4">
          <FormInput
            v-model="form.code"
            label="Kode"
            placeholder="ITEM-001"
            required
          />
          <FormInput
            v-model="form.unit"
            label="Satuan"
            placeholder="Unit/Bulan/Paket"
            required
          />
        </div>

        <FormInput
          v-model="form.name"
          label="Nama Item"
          placeholder="Nama item anggaran"
          required
        />

        <div class="grid grid-cols-2 gap-4">
          <FormInputNumber
            v-model="form.volume"
            label="Volume"
            :min="0"
            :step="0.01"
            required
          />
          <FormInputNumber
            v-model="form.unit_price"
            label="Harga Satuan"
            :min="0"
            :format="(v) => v ? `Rp ${v.toLocaleString('id-ID')}` : ''"
            :parse="(v) => Number(v.replace(/[^0-9]/g, ''))"
            required
          />
        </div>

        <div class="p-3 bg-blue-50 rounded-md border border-blue-200">
          <div class="flex justify-between items-center">
            <span class="text-sm font-medium text-blue-700">Total Anggaran:</span>
            <span class="text-lg font-bold text-blue-600">{{ formatCurrency(calculatedTotalBudget) }}</span>
          </div>
        </div>

        <FormCheckbox v-model="form.is_active" label="Aktif" />
      </div>
    </template>
  </CrudPage>
</template>
