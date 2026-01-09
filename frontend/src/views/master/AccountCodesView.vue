<script setup lang="ts">
import { h, ref, onMounted, computed } from 'vue'
import { NButton, NDataTable, NModal, NInput, NSelect, NTag, NSpace, NIcon, NPagination, NTree } from 'naive-ui'
import type { DataTableColumns, TreeOption } from 'naive-ui'
import { Add, ListOutline, GitBranchOutline } from '@vicons/ionicons5'
import { PageHeader, PageCard, LoadingSpinner, TableActions } from '@/components/ui'
import { FormInput, FormSelect, FormCheckbox } from '@/components/ui'
import { useMessage } from '@/composables'
import api from '@/services/api'

interface AccountCode {
  id: number
  code: string
  description: string
  level: number
  parent_code: string | null
  is_active: boolean
  created_at: string
  children?: AccountCode[]
}

const message = useMessage()

const loading = ref(false)
const accountCodes = ref<AccountCode[]>([])
const treeData = ref<AccountCode[]>([])
const viewMode = ref<'list' | 'tree'>('list')
const showModal = ref(false)
const editingCode = ref<AccountCode | null>(null)
const searchQuery = ref('')
const selectedLevel = ref<number | undefined>(undefined)
const levels = ref<{ value: number; label: string }[]>([])
const saving = ref(false)

const form = ref({
  code: '',
  description: '',
  level: 5,
  parent_code: '',
  is_active: true,
})

const pagination = ref({
  page: 1,
  pageSize: 15,
  itemCount: 0,
  pageCount: 1,
})

const levelColors: Record<number, 'default' | 'info' | 'success' | 'warning' | 'error'> = {
  1: 'error',
  2: 'warning',
  3: 'success',
  4: 'info',
  5: 'default',
}

const levelOptions = computed(() => [
  { label: 'Semua Level', value: undefined as number | undefined },
  ...levels.value,
])

async function fetchAccountCodes() {
  loading.value = true
  try {
    const params: Record<string, any> = {
      page: pagination.value.page,
      per_page: pagination.value.pageSize,
    }
    if (searchQuery.value) params.search = searchQuery.value
    if (selectedLevel.value) params.level = selectedLevel.value

    const response = await api.get('/account-codes', { params })
    const data = response.data.data
    accountCodes.value = Array.isArray(data) ? data : (data?.data || [])
    const meta = response.data.meta || (data?.current_page ? data : null)
    if (meta) {
      pagination.value.itemCount = meta.total || 0
      pagination.value.pageCount = meta.last_page || 1
    }
  } catch (error) {
    console.error('Failed to fetch account codes:', error)
  } finally {
    loading.value = false
  }
}

async function fetchTree() {
  loading.value = true
  try {
    const response = await api.get('/account-codes/tree')
    treeData.value = response.data.data
  } catch (error) {
    console.error('Failed to fetch tree:', error)
  } finally {
    loading.value = false
  }
}

async function fetchLevels() {
  try {
    const response = await api.get('/account-codes/levels')
    levels.value = response.data.data
  } catch (error) {
    console.error('Failed to fetch levels:', error)
  }
}

function openModal(code?: AccountCode) {
  if (code) {
    editingCode.value = code
    form.value = {
      code: code.code,
      description: code.description,
      level: code.level,
      parent_code: code.parent_code || '',
      is_active: code.is_active,
    }
  } else {
    editingCode.value = null
    form.value = {
      code: '',
      description: '',
      level: 5,
      parent_code: '',
      is_active: true,
    }
  }
  showModal.value = true
}

async function saveAccountCode() {
  saving.value = true
  try {
    const data = {
      ...form.value,
      parent_code: form.value.parent_code || null,
    }

    if (editingCode.value) {
      await api.put(`/account-codes/${editingCode.value.code}`, data)
    } else {
      await api.post('/account-codes', data)
    }

    showModal.value = false
    message.success('Kode rekening berhasil disimpan')
    await fetchAccountCodes()
    if (viewMode.value === 'tree') await fetchTree()
  } catch (error: any) {
    console.error('Failed to save:', error)
    message.error(error.response?.data?.message || 'Gagal menyimpan kode rekening')
  } finally {
    saving.value = false
  }
}

async function deleteAccountCode(code: string) {
  message.confirmDelete({
    content: 'Apakah Anda yakin ingin menghapus kode rekening ini?',
    onConfirm: async () => {
      try {
        await api.delete(`/account-codes/${code}`)
        message.success('Kode rekening berhasil dihapus')
        await fetchAccountCodes()
        if (viewMode.value === 'tree') await fetchTree()
      } catch (error: any) {
        console.error('Failed to delete:', error)
        message.error(error.response?.data?.message || 'Gagal menghapus kode rekening')
      }
    },
  })
}

function toggleViewMode() {
  viewMode.value = viewMode.value === 'list' ? 'tree' : 'list'
  if (viewMode.value === 'tree') {
    fetchTree()
  }
}

function handlePageChange(page: number) {
  pagination.value.page = page
  fetchAccountCodes()
}

const columns: DataTableColumns<AccountCode> = [
  {
    title: 'Kode',
    key: 'code',
    width: 200,
    render: (row) => h('code', { class: 'text-sm' }, row.code),
  },
  { title: 'Deskripsi', key: 'description' },
  {
    title: 'Level',
    key: 'level',
    width: 100,
    align: 'center',
    render: (row) =>
      h(NTag, { type: levelColors[row.level] || 'default', size: 'small' }, () =>
        levels.value.find((l) => l.value === row.level)?.label || `Level ${row.level}`
      ),
  },
  {
    title: 'Parent',
    key: 'parent_code',
    width: 150,
    render: (row) => h('code', { class: 'text-sm text-gray-500' }, row.parent_code || '-'),
  },
  {
    title: 'Status',
    key: 'is_active',
    width: 80,
    align: 'center',
    render: (row) =>
      h(NTag, { type: row.is_active ? 'success' : 'error', size: 'small' }, () =>
        row.is_active ? 'Aktif' : 'Nonaktif'
      ),
  },
  {
    title: 'Aksi',
    key: 'actions',
    width: 80,
    align: 'center',
    render: (row) =>
      h(TableActions, {
        canEdit: true,
        canDelete: true,
        onEdit: () => openModal(row),
        onDelete: () => deleteAccountCode(row.code),
      }),
  },
]

function convertToTreeOptions(codes: AccountCode[]): TreeOption[] {
  return codes.map((code) => ({
    key: code.code,
    label: `${code.code} - ${code.description}`,
    children: code.children?.length ? convertToTreeOptions(code.children) : undefined,
  }))
}

const treeOptions = computed(() => convertToTreeOptions(treeData.value))

onMounted(async () => {
  await Promise.all([fetchAccountCodes(), fetchLevels()])
})
</script>

<template>
  <div>
    <PageHeader title="Kode Rekening" subtitle="Kelola master kode rekening belanja">
      <template #actions>
        <NSpace>
          <NButton @click="toggleViewMode">
            <template #icon>
              <NIcon>
                <ListOutline v-if="viewMode === 'tree'" />
                <GitBranchOutline v-else />
              </NIcon>
            </template>
            {{ viewMode === 'list' ? 'Tree View' : 'List View' }}
          </NButton>
          <NButton type="primary" @click="openModal()">
            <template #icon>
              <NIcon><Add /></NIcon>
            </template>
            Tambah Kode
          </NButton>
        </NSpace>
      </template>
    </PageHeader>

    <!-- Filters -->
    <PageCard class="mb-6">
      <NSpace :size="16">
        <div class="flex-1">
          <NInput
            v-model:value="searchQuery"
            placeholder="Cari kode atau deskripsi..."
            clearable
            @update:value="fetchAccountCodes"
          />
        </div>
        <div style="width: 200px">
          <NSelect
            v-model:value="selectedLevel"
            :options="levelOptions"
            placeholder="Semua Level"
            clearable
            @update:value="fetchAccountCodes"
          />
        </div>
      </NSpace>
    </PageCard>

    <!-- List View -->
    <PageCard v-if="viewMode === 'list'" :padding="false">
      <LoadingSpinner v-if="loading" />
      <template v-else>
        <NDataTable
          :columns="columns"
          :data="accountCodes"
          :bordered="false"
          striped
        />
        <div class="p-4 border-t flex items-center justify-between">
          <div class="text-sm text-gray-600">
            Menampilkan {{ accountCodes.length }} dari {{ pagination.itemCount }} data
          </div>
          <NPagination
            v-model:page="pagination.page"
            :page-count="pagination.pageCount"
            :page-size="pagination.pageSize"
            @update:page="handlePageChange"
          />
        </div>
      </template>
    </PageCard>

    <!-- Tree View -->
    <PageCard v-else title="Struktur Hierarki Kode Rekening">
      <LoadingSpinner v-if="loading" />
      <NTree
        v-else
        :data="treeOptions"
        block-line
        :default-expand-all="false"
        :selectable="false"
      />
    </PageCard>

    <!-- Modal -->
    <NModal
      v-model:show="showModal"
      preset="card"
      :title="editingCode ? 'Edit Kode Rekening' : 'Tambah Kode Rekening'"
      style="width: 600px"
    >
      <form @submit.prevent="saveAccountCode" class="space-y-4">
        <FormInput
          v-model="form.code"
          label="Kode Rekening"
          placeholder="Contoh: 5.1.02.01.01.0024"
          :disabled="!!editingCode"
          required
        />

        <FormInput
          v-model="form.description"
          label="Deskripsi"
          placeholder="Deskripsi kode rekening"
          type="textarea"
          required
        />

        <div class="grid grid-cols-2 gap-4">
          <FormSelect
            v-model="form.level"
            label="Level"
            :options="levels"
            required
          />
          <FormInput
            v-model="form.parent_code"
            label="Parent Code"
            placeholder="Kode parent (opsional)"
          />
        </div>

        <FormCheckbox v-model="form.is_active" label="Aktif" />

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
