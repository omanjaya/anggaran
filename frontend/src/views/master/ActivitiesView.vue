<script setup lang="ts">
import { h, onMounted, ref, computed } from 'vue'
import type { DataTableColumns } from 'naive-ui'
import { CrudPage, StatusBadge, TableActions, PageCard } from '@/components/ui'
import { FormInput, FormSelect, FormInputNumber, FormCheckbox } from '@/components/ui'
import { useCrud, useFormat } from '@/composables'
import { useAuthStore } from '@/stores/auth'
import api from '@/services/api'
import type { Activity, Program } from '@/types/models'

interface ActivityForm {
  id: number
  program_id: number
  code: string
  name: string
  total_budget: number
  is_active: boolean
}

const authStore = useAuthStore()
const { formatCurrency } = useFormat()

// Permissions
const canCreate = computed(() => authStore.hasPermission('master.create'))
const canEdit = computed(() => authStore.hasPermission('master.update'))
const canDelete = computed(() => authStore.hasPermission('master.delete'))

// Programs for filter and form
const programs = ref<Program[]>([])
const selectedProgramId = ref<number | null>(null)

const programOptions = computed(() =>
  programs.value.map((p) => ({ label: `${p.code} - ${p.name}`, value: p.id }))
)

async function fetchPrograms() {
  try {
    const response = await api.get('/programs')
    programs.value = response.data.data
  } catch (err) {
    console.error('Failed to fetch programs:', err)
  }
}

function getProgramName(programId: number) {
  return programs.value.find((p) => p.id === programId)?.name || '-'
}

const initialForm: ActivityForm = {
  id: 0,
  program_id: 0,
  code: '',
  name: '',
  total_budget: 0,
  is_active: true,
}

const {
  loading,
  saving,
  data: activities,
  showModal,
  isEditing,
  formError,
  form,
  fetchData,
  openCreateModal: baseOpenCreateModal,
  openEditModal,
  save,
  deleteItem,
} = useCrud<Activity, ActivityForm>({
  endpoint: '/activities',
  initialForm,
  mapToForm: (activity) => ({
    id: activity.id,
    program_id: activity.program_id,
    code: activity.code,
    name: activity.name,
    total_budget: activity.total_budget,
    is_active: activity.is_active,
  }),
  deleteConfirmMessage: (activity) => `Hapus kegiatan "${activity.name}"?`,
})

// Override openCreateModal to pre-select program
function openCreateModal() {
  baseOpenCreateModal()
  if (selectedProgramId.value) {
    form.value.program_id = selectedProgramId.value
  }
}

// Filtered data based on selected program
const filteredActivities = computed(() => {
  if (!selectedProgramId.value) return activities.value
  return activities.value.filter((a) => a.program_id === selectedProgramId.value)
})

function onProgramFilterChange() {
  fetchData(selectedProgramId.value ? { program_id: selectedProgramId.value } : {})
}

const columns: DataTableColumns<Activity> = [
  {
    title: 'Kode',
    key: 'code',
    width: 120,
    render: (row) => h('span', { class: 'font-medium' }, row.code),
  },
  {
    title: 'Nama Kegiatan',
    key: 'name',
  },
  {
    title: 'Program',
    key: 'program_id',
    width: 200,
    ellipsis: { tooltip: true },
    render: (row) => getProgramName(row.program_id),
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
      }),
  },
]

onMounted(() => {
  fetchPrograms()
  fetchData()
})
</script>

<template>
  <CrudPage
    title="Kegiatan"
    subtitle="Kelola data kegiatan anggaran"
    :loading="loading"
    :data="filteredActivities"
    :columns="columns"
    :show-modal="showModal"
    :modal-title="isEditing ? 'Edit Kegiatan' : 'Tambah Kegiatan'"
    :modal-loading="saving"
    add-button-text="Tambah Kegiatan"
    empty-text="Belum ada data kegiatan"
    :can-create="canCreate"
    :scroll-x="900"
    @create="openCreateModal"
    @update:show-modal="showModal = $event"
    @submit="save"
  >
    <!-- Filter -->
    <template #filters>
      <PageCard class="mb-6">
        <div class="flex items-center gap-4">
          <FormSelect
            v-model="selectedProgramId"
            label="Filter Program"
            placeholder="Semua Program"
            :options="programOptions"
            clearable
            style="min-width: 300px"
            @update:model-value="onProgramFilterChange"
          />
        </div>
      </PageCard>
    </template>

    <!-- Form -->
    <template #form>
      <div v-if="formError" class="mb-4 p-3 bg-red-50 border border-red-200 text-red-700 rounded-md text-sm">
        {{ formError }}
      </div>

      <div class="space-y-4">
        <FormSelect
          v-model="form.program_id"
          label="Program"
          placeholder="Pilih program"
          :options="programOptions"
          required
        />

        <FormInput
          v-model="form.code"
          label="Kode"
          placeholder="KGT-001"
          required
        />

        <FormInput
          v-model="form.name"
          label="Nama Kegiatan"
          placeholder="Nama kegiatan"
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

        <FormCheckbox v-model="form.is_active" label="Aktif" />
      </div>
    </template>
  </CrudPage>
</template>
