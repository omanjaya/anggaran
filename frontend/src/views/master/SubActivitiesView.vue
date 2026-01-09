<script setup lang="ts">
import { h, onMounted, ref, computed } from 'vue'
import { NSpace } from 'naive-ui'
import type { DataTableColumns } from 'naive-ui'
import { CrudPage, StatusBadge, TableActions, PageCard } from '@/components/ui'
import { FormInput, FormSelect, FormInputNumber, FormCheckbox } from '@/components/ui'
import { useCrud, useFormat } from '@/composables'
import { useAuthStore } from '@/stores/auth'
import api from '@/services/api'
import type { SubActivity, Activity, Program } from '@/types/models'

interface SubActivityForm {
  id: number
  activity_id: number
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

// Programs and Activities for filter and form
const programs = ref<Program[]>([])
const activities = ref<Activity[]>([])
const selectedProgramId = ref<number | null>(null)
const selectedActivityId = ref<number | null>(null)

const programOptions = computed(() =>
  programs.value.map((p) => ({ label: `${p.code} - ${p.name}`, value: p.id }))
)

const filteredActivityOptions = computed(() => {
  const list = selectedProgramId.value
    ? activities.value.filter((a) => a.program_id === selectedProgramId.value)
    : activities.value
  return list.map((a) => ({ label: `${a.code} - ${a.name}`, value: a.id }))
})

const allActivityOptions = computed(() =>
  activities.value.map((a) => ({ label: `${a.code} - ${a.name}`, value: a.id }))
)

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

function getActivityName(activityId: number) {
  return activities.value.find((a) => a.id === activityId)?.name || '-'
}

const initialForm: SubActivityForm = {
  id: 0,
  activity_id: 0,
  code: '',
  name: '',
  total_budget: 0,
  is_active: true,
}

const {
  loading,
  saving,
  data: subActivities,
  showModal,
  isEditing,
  formError,
  form,
  fetchData,
  openCreateModal: baseOpenCreateModal,
  openEditModal,
  save,
  deleteItem,
} = useCrud<SubActivity, SubActivityForm>({
  endpoint: '/sub-activities',
  initialForm,
  mapToForm: (subActivity) => ({
    id: subActivity.id,
    activity_id: subActivity.activity_id,
    code: subActivity.code,
    name: subActivity.name,
    total_budget: subActivity.total_budget,
    is_active: subActivity.is_active,
  }),
  deleteConfirmMessage: (subActivity) => `Hapus sub kegiatan "${subActivity.name}"?`,
})

// Override openCreateModal to pre-select activity
function openCreateModal() {
  baseOpenCreateModal()
  if (selectedActivityId.value) {
    form.value.activity_id = selectedActivityId.value
  }
}

function onProgramFilterChange() {
  selectedActivityId.value = null
  fetchDataWithFilters()
}

function onActivityFilterChange() {
  fetchDataWithFilters()
}

function fetchDataWithFilters() {
  const params: Record<string, any> = {}
  if (selectedActivityId.value) params.activity_id = selectedActivityId.value
  fetchData(params)
}

const columns: DataTableColumns<SubActivity> = [
  {
    title: 'Kode',
    key: 'code',
    width: 120,
    render: (row) => h('span', { class: 'font-medium' }, row.code),
  },
  {
    title: 'Nama Sub Kegiatan',
    key: 'name',
  },
  {
    title: 'Kegiatan',
    key: 'activity_id',
    width: 200,
    ellipsis: { tooltip: true },
    render: (row) => getActivityName(row.activity_id),
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
  fetchActivities()
  fetchData()
})
</script>

<template>
  <CrudPage
    title="Sub Kegiatan"
    subtitle="Kelola data sub kegiatan anggaran"
    :loading="loading"
    :data="subActivities"
    :columns="columns"
    :show-modal="showModal"
    :modal-title="isEditing ? 'Edit Sub Kegiatan' : 'Tambah Sub Kegiatan'"
    :modal-loading="saving"
    add-button-text="Tambah Sub Kegiatan"
    empty-text="Belum ada data sub kegiatan"
    :can-create="canCreate"
    :scroll-x="900"
    @create="openCreateModal"
    @update:show-modal="showModal = $event"
    @submit="save"
  >
    <!-- Filter -->
    <template #filters>
      <PageCard class="mb-6">
        <NSpace :size="16">
          <FormSelect
            v-model="selectedProgramId"
            label="Program"
            placeholder="Semua"
            :options="programOptions"
            clearable
            style="min-width: 250px"
            @update:model-value="onProgramFilterChange"
          />
          <FormSelect
            v-model="selectedActivityId"
            label="Kegiatan"
            placeholder="Semua"
            :options="filteredActivityOptions"
            clearable
            style="min-width: 250px"
            @update:model-value="onActivityFilterChange"
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
          v-model="form.activity_id"
          label="Kegiatan"
          placeholder="Pilih kegiatan"
          :options="allActivityOptions"
          filterable
          required
        />

        <FormInput
          v-model="form.code"
          label="Kode"
          placeholder="SUBKGT-001"
          required
        />

        <FormInput
          v-model="form.name"
          label="Nama Sub Kegiatan"
          placeholder="Nama sub kegiatan"
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
