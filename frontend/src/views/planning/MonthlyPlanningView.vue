<script setup lang="ts">
import { h, ref, onMounted, computed } from 'vue'
import { NButton, NDataTable, NModal, NSpace, NIcon } from 'naive-ui'
import type { DataTableColumns } from 'naive-ui'
import { Add } from '@vicons/ionicons5'
import { PageHeader, PageCard, LoadingSpinner, TableActions } from '@/components/ui'
import { FormSelect, FormInputNumber, FormInput } from '@/components/ui'
import { useFormat, useMessage } from '@/composables'
import { useAuthStore } from '@/stores/auth'
import api from '@/services/api'
import type { MonthlyPlan, BudgetItem, Program } from '@/types/models'

const authStore = useAuthStore()
const { formatCurrency, formatNumber } = useFormat()
const message = useMessage()

const loading = ref(true)
const plans = ref<MonthlyPlan[]>([])
const budgetItems = ref<BudgetItem[]>([])
const programs = ref<Program[]>([])
const showModal = ref(false)
const isEditing = ref(false)
const saving = ref(false)
const formError = ref('')

// Filters
const selectedYear = ref(new Date().getFullYear())
const selectedMonth = ref<number | undefined>(undefined)
const selectedProgramId = ref<number | undefined>(undefined)

const form = ref({
  id: 0,
  budget_item_id: 0,
  month: 1,
  year: new Date().getFullYear(),
  planned_volume: 0,
  planned_amount: 0,
  notes: '',
})

const canCreate = computed(() => authStore.hasPermission('planning.create'))
const canEdit = computed(() => authStore.hasPermission('planning.update'))
const canDelete = computed(() => authStore.hasPermission('planning.delete'))

const months = [
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

const monthOptions = [{ value: undefined as number | undefined, label: 'Semua' }, ...months]

const years = computed(() => {
  const currentYear = new Date().getFullYear()
  return Array.from({ length: 5 }, (_, i) => ({
    label: String(currentYear - 2 + i),
    value: currentYear - 2 + i,
  }))
})

const programOptions = computed(() => [
  { label: 'Semua', value: undefined as number | undefined },
  ...programs.value.map((p) => ({ label: `${p.code} - ${p.name}`, value: p.id })),
])

const budgetItemOptions = computed(() =>
  budgetItems.value.map((bi) => ({ label: `${bi.code} - ${bi.name}`, value: bi.id }))
)

async function fetchPlans() {
  loading.value = true
  try {
    const params: any = { year: selectedYear.value }
    if (selectedMonth.value) params.month = selectedMonth.value
    if (selectedProgramId.value) params.program_id = selectedProgramId.value
    const response = await api.get('/monthly-plans', { params })
    const data = response.data.data
    plans.value = Array.isArray(data) ? data : (data?.data || [])
  } catch (err) {
    console.error('Failed to fetch plans:', err)
  } finally {
    loading.value = false
  }
}

async function fetchBudgetItems() {
  try {
    const response = await api.get('/budget-items')
    const data = response.data.data
    budgetItems.value = Array.isArray(data) ? data : (data?.data || [])
  } catch (err) {
    console.error('Failed to fetch budget items:', err)
  }
}

async function fetchPrograms() {
  try {
    const response = await api.get('/programs')
    const data = response.data.data
    programs.value = Array.isArray(data) ? data : (data?.data || [])
  } catch (err) {
    console.error('Failed to fetch programs:', err)
  }
}

function openCreateModal() {
  isEditing.value = false
  form.value = {
    id: 0,
    budget_item_id: 0,
    month: selectedMonth.value || new Date().getMonth() + 1,
    year: selectedYear.value,
    planned_volume: 0,
    planned_amount: 0,
    notes: '',
  }
  formError.value = ''
  showModal.value = true
}

function openEditModal(plan: MonthlyPlan) {
  isEditing.value = true
  form.value = {
    id: plan.id,
    budget_item_id: plan.budget_item_id,
    month: plan.month,
    year: plan.year,
    planned_volume: plan.planned_volume,
    planned_amount: plan.planned_amount,
    notes: plan.notes || '',
  }
  formError.value = ''
  showModal.value = true
}

async function savePlan() {
  saving.value = true
  formError.value = ''
  try {
    if (isEditing.value) {
      await api.put(`/monthly-plans/${form.value.id}`, form.value)
    } else {
      await api.post('/monthly-plans', form.value)
    }
    showModal.value = false
    message.success('Rencana berhasil disimpan')
    await fetchPlans()
  } catch (err: any) {
    if (err.response?.data?.errors) {
      const errors = err.response.data.errors
      formError.value = Object.values(errors).flat().join(', ')
    } else {
      formError.value = err.response?.data?.message || 'Gagal menyimpan data'
    }
  } finally {
    saving.value = false
  }
}

async function deletePlan(plan: MonthlyPlan) {
  message.confirmDelete({
    content: `rencana bulan ${months.find((m) => m.value === plan.month)?.label}`,
    onConfirm: async () => {
      try {
        await api.delete(`/monthly-plans/${plan.id}`)
        message.success('Rencana berhasil dihapus')
        await fetchPlans()
      } catch (err: any) {
        message.error(err.response?.data?.message || 'Gagal menghapus data')
      }
    },
  })
}

function getBudgetItemName(budgetItemId: number) {
  return budgetItems.value.find((bi) => bi.id === budgetItemId)?.name || '-'
}

function getMonthName(month: number) {
  return months.find((m) => m.value === month)?.label || '-'
}

function updatePlannedAmount() {
  const item = budgetItems.value.find((bi) => bi.id === form.value.budget_item_id)
  if (item) {
    form.value.planned_amount = form.value.planned_volume * item.unit_price
  }
}

const columns: DataTableColumns<MonthlyPlan> = [
  {
    title: 'Item Anggaran',
    key: 'budget_item_id',
    render: (row) => getBudgetItemName(row.budget_item_id),
  },
  {
    title: 'Periode',
    key: 'period',
    width: 150,
    align: 'center',
    render: (row) => `${getMonthName(row.month)} ${row.year}`,
  },
  {
    title: 'Volume Rencana',
    key: 'planned_volume',
    width: 130,
    align: 'right',
    render: (row) => formatNumber(row.planned_volume),
  },
  {
    title: 'Jumlah Rencana',
    key: 'planned_amount',
    width: 160,
    align: 'right',
    render: (row) => formatCurrency(row.planned_amount),
  },
  {
    title: 'Catatan',
    key: 'notes',
    width: 200,
    ellipsis: { tooltip: true },
    render: (row) => row.notes || '-',
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
        onDelete: () => deletePlan(row),
      }),
  },
]

onMounted(() => {
  fetchPrograms()
  fetchBudgetItems()
  fetchPlans()
})
</script>

<template>
  <div>
    <PageHeader title="Perencanaan Bulanan" subtitle="Kelola rencana anggaran bulanan">
      <template #actions>
        <NButton v-if="canCreate" type="primary" @click="openCreateModal">
          <template #icon>
            <NIcon><Add /></NIcon>
          </template>
          Tambah Rencana
        </NButton>
      </template>
    </PageHeader>

    <!-- Filter -->
    <PageCard class="mb-6">
      <NSpace :size="16" wrap>
        <FormSelect
          v-model="selectedYear"
          label="Tahun"
          :options="years"
          style="min-width: 120px"
          @update:model-value="fetchPlans"
        />
        <FormSelect
          v-model="selectedMonth"
          label="Bulan"
          :options="monthOptions"
          style="min-width: 150px"
          @update:model-value="fetchPlans"
        />
        <FormSelect
          v-model="selectedProgramId"
          label="Program"
          :options="programOptions"
          style="min-width: 250px"
          @update:model-value="fetchPlans"
        />
      </NSpace>
    </PageCard>

    <PageCard :padding="false">
      <LoadingSpinner v-if="loading" />
      <template v-else>
        <div v-if="plans.length === 0" class="text-center py-12 text-gray-500">
          Belum ada rencana bulanan untuk periode ini
        </div>
        <NDataTable
          v-else
          :columns="columns"
          :data="plans"
          :bordered="false"
          striped
        />
      </template>
    </PageCard>

    <!-- Modal -->
    <NModal
      v-model:show="showModal"
      preset="card"
      :title="isEditing ? 'Edit Rencana Bulanan' : 'Tambah Rencana Bulanan'"
      style="width: 600px"
    >
      <form @submit.prevent="savePlan" class="space-y-4">
        <div v-if="formError" class="p-3 bg-red-50 border border-red-200 text-red-700 rounded-md text-sm">
          {{ formError }}
        </div>

        <FormSelect
          v-model="form.budget_item_id"
          label="Item Anggaran"
          placeholder="Pilih item anggaran"
          :options="budgetItemOptions"
          filterable
          required
          @update:model-value="updatePlannedAmount"
        />

        <div class="grid grid-cols-2 gap-4">
          <FormSelect
            v-model="form.month"
            label="Bulan"
            :options="months"
            required
          />
          <FormSelect
            v-model="form.year"
            label="Tahun"
            :options="years"
            required
          />
        </div>

        <div class="grid grid-cols-2 gap-4">
          <FormInputNumber
            v-model="form.planned_volume"
            label="Volume Rencana"
            :min="0"
            :step="0.01"
            required
            @update:model-value="updatePlannedAmount"
          />
          <FormInputNumber
            v-model="form.planned_amount"
            label="Jumlah Rencana"
            :min="0"
            :format="(v) => v ? `Rp ${v.toLocaleString('id-ID')}` : ''"
            :parse="(v) => Number(v.replace(/[^0-9]/g, ''))"
            required
          />
        </div>

        <FormInput
          v-model="form.notes"
          label="Catatan"
          placeholder="Catatan tambahan (opsional)"
          type="textarea"
        />

        <div class="flex justify-end gap-3 pt-4">
          <NButton @click="showModal = false">Batal</NButton>
          <NButton type="primary" attr-type="submit" :loading="saving">
            {{ saving ? 'Menyimpan...' : 'Simpan' }}
          </NButton>
        </div>
      </form>
    </NModal>
  </div>
</template>
