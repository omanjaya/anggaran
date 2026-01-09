<script setup lang="ts">
import { h, onMounted, ref, computed } from 'vue'
import type { DataTableColumns } from 'naive-ui'
import { CrudPage, StatusBadge, TableActions } from '@/components/ui'
import { FormInput, FormSelect, FormInputNumber, FormCheckbox } from '@/components/ui'
import { useCrud, useFormat } from '@/composables'
import { useAuthStore } from '@/stores/auth'
import api from '@/services/api'
import type { Program, BudgetCategory } from '@/types/models'

interface ProgramForm {
  id: number
  code: string
  name: string
  category: BudgetCategory | ''
  fiscal_year: number
  total_budget: number
  is_active: boolean
}

const authStore = useAuthStore()
const { formatCurrency } = useFormat()

// Permissions
const canCreate = computed(() => authStore.hasPermission('master.create'))
const canEdit = computed(() => authStore.hasPermission('master.update'))
const canDelete = computed(() => authStore.hasPermission('master.delete'))

// Categories
const categories = ref<{ value: string; label: string }[]>([])
const categoryOptions = computed(() =>
  categories.value.map((c) => ({ label: c.label, value: c.value }))
)

async function fetchCategories() {
  try {
    const response = await api.get('/programs/categories')
    categories.value = response.data.data
  } catch (err) {
    console.error('Failed to fetch categories:', err)
  }
}

function getCategoryLabel(code: string) {
  return categories.value.find((c) => c.value === code)?.label || code
}

const initialForm: ProgramForm = {
  id: 0,
  code: '',
  name: '',
  category: '',
  fiscal_year: new Date().getFullYear(),
  total_budget: 0,
  is_active: true,
}

const {
  loading,
  saving,
  data: programs,
  showModal,
  isEditing,
  formError,
  form,
  fetchData,
  openCreateModal,
  openEditModal,
  save,
  deleteItem,
} = useCrud<Program, ProgramForm>({
  endpoint: '/programs',
  initialForm,
  mapToForm: (program) => ({
    id: program.id,
    code: program.code,
    name: program.name,
    category: program.category,
    fiscal_year: program.fiscal_year,
    total_budget: program.total_budget,
    is_active: program.is_active,
  }),
  deleteConfirmMessage: (program) => `Hapus program "${program.name}"?`,
})

const columns: DataTableColumns<Program> = [
  {
    title: 'Kode',
    key: 'code',
    width: 120,
    render: (row) => h('span', { class: 'font-medium' }, row.code),
  },
  {
    title: 'Nama Program',
    key: 'name',
  },
  {
    title: 'Kategori',
    key: 'category',
    width: 150,
    render: (row) => getCategoryLabel(row.category),
  },
  {
    title: 'Tahun',
    key: 'fiscal_year',
    width: 80,
    align: 'center',
  },
  {
    title: 'Total Anggaran',
    key: 'total_budget',
    width: 180,
    align: 'right',
    render: (row) => formatCurrency(row.total_budget),
  },
  {
    title: 'Status',
    key: 'is_active',
    width: 100,
    align: 'center',
    render: (row) => h(StatusBadge, { status: row.is_active }),
  },
  {
    title: 'Aksi',
    key: 'actions',
    width: 100,
    align: 'center',
    render: (row) =>
      h(TableActions, {
        canEdit: canEdit.value,
        canDelete: canDelete.value,
        onEdit: () => openEditModal(row),
        onDelete: () => deleteItem(row),
        deleteConfirmText: `Hapus program "${row.name}"?`,
      }),
  },
]

onMounted(() => {
  fetchCategories()
  fetchData()
})
</script>

<template>
  <CrudPage
    title="Program"
    subtitle="Kelola data program anggaran"
    :loading="loading"
    :data="programs"
    :columns="columns"
    :show-modal="showModal"
    :modal-title="isEditing ? 'Edit Program' : 'Tambah Program'"
    :modal-loading="saving"
    add-button-text="Tambah Program"
    empty-text="Belum ada data program"
    :can-create="canCreate"
    :scroll-x="900"
    @create="openCreateModal"
    @update:show-modal="showModal = $event"
    @submit="save"
  >
    <template #form>
      <div v-if="formError" class="mb-4 p-3 bg-red-50 border border-red-200 text-red-700 rounded-md text-sm">
        {{ formError }}
      </div>

      <div class="space-y-4">
        <FormInput
          v-model="form.code"
          label="Kode"
          placeholder="PRG-001"
          required
        />

        <FormInput
          v-model="form.name"
          label="Nama Program"
          placeholder="Nama program"
          required
        />

        <FormSelect
          v-model="form.category"
          label="Kategori"
          placeholder="Pilih kategori"
          :options="categoryOptions"
          required
        />

        <div class="grid grid-cols-2 gap-4">
          <FormInputNumber
            v-model="form.fiscal_year"
            label="Tahun Anggaran"
            :min="2020"
            :max="2100"
            required
          />
          <FormInputNumber
            v-model="form.total_budget"
            label="Total Anggaran"
            :min="0"
            :format="(v) => v ? `Rp ${v.toLocaleString('id-ID')}` : ''"
            :parse="(v) => Number(v.replace(/[^0-9]/g, ''))"
            required
          />
        </div>

        <FormCheckbox v-model="form.is_active" label="Aktif" />
      </div>
    </template>
  </CrudPage>
</template>
