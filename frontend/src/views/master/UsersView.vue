<script setup lang="ts">
import { h, onMounted, ref, computed } from 'vue'
import { NAvatar } from 'naive-ui'
import type { DataTableColumns } from 'naive-ui'
import { CrudPage, StatusBadge, TableActions } from '@/components/ui'
import { FormInput, FormSelect, FormCheckbox } from '@/components/ui'
import { useCrud } from '@/composables'
import { useAuthStore } from '@/stores/auth'
import api from '@/services/api'
import type { User, UserRole } from '@/types/models'

interface UserForm {
  id: number
  name: string
  email: string
  password: string
  password_confirmation: string
  role: UserRole | ''
  is_active: boolean
}

const authStore = useAuthStore()

// Permissions
const canCreate = computed(() => authStore.hasPermission('users.create'))
const canEdit = computed(() => authStore.hasPermission('users.update'))
const canDelete = computed(() => authStore.hasPermission('users.delete'))

// Roles
const roles = ref<{ value: string; label: string }[]>([])
const roleOptions = computed(() =>
  roles.value.map((r) => ({ label: r.label, value: r.value }))
)

const roleLabels: Record<string, string> = {
  ADMIN: 'Administrator',
  KADIS: 'Kepala Dinas',
  TIM_PERENCANAAN: 'Tim Perencanaan',
  TIM_PELAKSANA: 'Tim Pelaksana',
  BENDAHARA: 'Bendahara',
  MONEV: 'Monitoring & Evaluasi',
  VIEWER: 'Viewer',
}

async function fetchRoles() {
  try {
    const response = await api.get('/users/roles')
    roles.value = response.data.data
  } catch (err) {
    console.error('Failed to fetch roles:', err)
    roles.value = Object.entries(roleLabels).map(([value, label]) => ({ value, label }))
  }
}

function getRoleLabel(role: string) {
  return roleLabels[role] || role
}

const initialForm: UserForm = {
  id: 0,
  name: '',
  email: '',
  password: '',
  password_confirmation: '',
  role: '',
  is_active: true,
}

const {
  loading,
  saving,
  data: users,
  showModal,
  isEditing,
  formError,
  form,
  fetchData,
  openCreateModal,
  openEditModal,
  closeModal,
  deleteItem,
} = useCrud<User, UserForm>({
  endpoint: '/users',
  initialForm,
  mapToForm: (user) => ({
    id: user.id,
    name: user.name,
    email: user.email,
    password: '',
    password_confirmation: '',
    role: user.role,
    is_active: user.is_active,
  }),
  deleteConfirmMessage: (user) => `Hapus pengguna "${user.name}"?`,
})

// Custom save to handle password logic
async function saveUser() {
  saving.value = true
  formError.value = ''

  try {
    const payload: Record<string, any> = {
      name: form.value.name,
      email: form.value.email,
      role: form.value.role,
      is_active: form.value.is_active,
    }

    // Only include password if provided
    if (form.value.password) {
      payload.password = form.value.password
      payload.password_confirmation = form.value.password_confirmation
    }

    if (isEditing.value) {
      await api.put(`/users/${form.value.id}`, payload)
    } else {
      await api.post('/users', payload)
    }

    closeModal()
    await fetchData()
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

async function toggleUserStatus(user: User) {
  try {
    await api.patch(`/users/${user.id}/toggle-status`)
    await fetchData()
  } catch (err: any) {
    console.error('Failed to toggle status:', err)
  }
}

const columns: DataTableColumns<User> = [
  {
    title: 'Nama',
    key: 'name',
    render: (row) =>
      h('div', { class: 'flex items-center gap-3' }, [
        h(NAvatar, {
          round: true,
          size: 'small',
          style: { backgroundColor: '#3b82f6' },
        }, () => row.name.charAt(0).toUpperCase()),
        h('span', { class: 'font-medium' }, row.name),
      ]),
  },
  {
    title: 'Email',
    key: 'email',
  },
  {
    title: 'Role',
    key: 'role',
    width: 180,
    render: (row) =>
      h(StatusBadge, {
        status: row.role,
        label: getRoleLabel(row.role),
        type: 'info',
      }),
  },
  {
    title: 'Status',
    key: 'is_active',
    width: 100,
    align: 'center',
    render: (row) =>
      h(StatusBadge, {
        status: row.is_active,
        style: canEdit.value ? 'cursor: pointer' : '',
        onClick: canEdit.value ? () => toggleUserStatus(row) : undefined,
      }),
  },
  {
    title: 'Aksi',
    key: 'actions',
    width: 100,
    align: 'center',
    render: (row) =>
      h(TableActions, {
        canEdit: canEdit.value,
        canDelete: canDelete.value && row.id !== authStore.user?.id,
        onEdit: () => openEditModal(row),
        onDelete: () => deleteItem(row),
        deleteConfirmText: `Hapus pengguna "${row.name}"?`,
      }),
  },
]

onMounted(() => {
  fetchRoles()
  fetchData()
})
</script>

<template>
  <CrudPage
    title="Pengguna"
    subtitle="Kelola data pengguna sistem"
    :loading="loading"
    :data="users"
    :columns="columns"
    :show-modal="showModal"
    :modal-title="isEditing ? 'Edit Pengguna' : 'Tambah Pengguna'"
    :modal-loading="saving"
    add-button-text="Tambah Pengguna"
    empty-text="Belum ada data pengguna"
    :can-create="canCreate"
    :scroll-x="800"
    @create="openCreateModal"
    @update:show-modal="showModal = $event"
    @submit="saveUser"
  >
    <template #form>
      <div v-if="formError" class="mb-4 p-3 bg-red-50 border border-red-200 text-red-700 rounded-md text-sm">
        {{ formError }}
      </div>

      <div class="space-y-4">
        <FormInput
          v-model="form.name"
          label="Nama Lengkap"
          placeholder="Nama lengkap"
          required
        />

        <FormInput
          v-model="form.email"
          label="Email"
          placeholder="email@example.com"
          required
        />

        <FormSelect
          v-model="form.role"
          label="Role"
          placeholder="Pilih role"
          :options="roleOptions"
          required
        />

        <FormInput
          v-model="form.password"
          :label="isEditing ? 'Password (kosongkan jika tidak diubah)' : 'Password'"
          type="password"
          placeholder="Minimal 8 karakter"
          :required="!isEditing"
        />

        <FormInput
          v-model="form.password_confirmation"
          label="Konfirmasi Password"
          type="password"
          placeholder="Ulangi password"
          :required="!!form.password"
        />

        <FormCheckbox v-model="form.is_active" label="Aktif" />
      </div>
    </template>
  </CrudPage>
</template>
