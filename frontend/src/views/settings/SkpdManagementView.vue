<script setup lang="ts">
import { h, onMounted } from 'vue'
import { NTag } from 'naive-ui'
import type { DataTableColumns } from 'naive-ui'
import { CrudPage, StatusBadge, TableActions } from '@/components/ui'
import { FormInput, FormCheckbox } from '@/components/ui'
import { useCrud } from '@/composables'

interface Skpd {
  id: number
  code: string
  name: string
  short_name: string | null
  address: string | null
  phone: string | null
  email: string | null
  head_name: string | null
  head_nip: string | null
  is_active: boolean
  users_count?: number
  programs_count?: number
}

interface SkpdForm {
  code: string
  name: string
  short_name: string
  address: string
  phone: string
  email: string
  head_name: string
  head_nip: string
  is_active: boolean
}

const initialForm: SkpdForm = {
  code: '',
  name: '',
  short_name: '',
  address: '',
  phone: '',
  email: '',
  head_name: '',
  head_nip: '',
  is_active: true,
}

const {
  loading,
  saving,
  data: skpdList,
  showModal,
  isEditing,
  formError,
  form,
  fetchData,
  openCreateModal,
  openEditModal,
  save,
  deleteItem,
  toggleActive,
} = useCrud<Skpd, SkpdForm>({
  endpoint: '/skpd',
  initialForm,
  mapToForm: (skpd) => ({
    code: skpd.code,
    name: skpd.name,
    short_name: skpd.short_name || '',
    address: skpd.address || '',
    phone: skpd.phone || '',
    email: skpd.email || '',
    head_name: skpd.head_name || '',
    head_nip: skpd.head_nip || '',
    is_active: skpd.is_active,
  }),
  deleteConfirmMessage: (skpd) => `Hapus SKPD "${skpd.name}"?`,
})

const columns: DataTableColumns<Skpd> = [
  {
    title: 'Kode',
    key: 'code',
    width: 100,
    render: (row) => h('span', { class: 'font-medium' }, row.code),
  },
  {
    title: 'Nama SKPD',
    key: 'name',
    render: (row) =>
      h('div', [
        h('div', { class: 'font-medium' }, row.name),
        row.short_name ? h('div', { class: 'text-xs text-gray-500' }, row.short_name) : null,
      ]),
  },
  {
    title: 'Kepala',
    key: 'head_name',
    render: (row) =>
      row.head_name
        ? h('div', [
            h('div', row.head_name),
            row.head_nip ? h('div', { class: 'text-xs text-gray-500' }, `NIP: ${row.head_nip}`) : null,
          ])
        : h('span', { class: 'text-gray-400' }, '-'),
  },
  {
    title: 'Kontak',
    key: 'contact',
    render: (row) =>
      row.email || row.phone
        ? h('div', { class: 'text-sm' }, [
            row.email ? h('div', row.email) : null,
            row.phone ? h('div', { class: 'text-gray-500' }, row.phone) : null,
          ])
        : h('span', { class: 'text-gray-400' }, '-'),
  },
  {
    title: 'Users',
    key: 'users_count',
    width: 80,
    align: 'center',
    render: (row) => h(NTag, { size: 'small', round: true }, () => row.users_count || 0),
  },
  {
    title: 'Program',
    key: 'programs_count',
    width: 80,
    align: 'center',
    render: (row) => h(NTag, { size: 'small', round: true }, () => row.programs_count || 0),
  },
  {
    title: 'Status',
    key: 'is_active',
    width: 100,
    align: 'center',
    render: (row) =>
      h(StatusBadge, {
        status: row.is_active,
        style: 'cursor: pointer',
        onClick: () => toggleActive(row),
      }),
  },
  {
    title: 'Aksi',
    key: 'actions',
    width: 100,
    align: 'center',
    render: (row) =>
      h(TableActions, {
        onEdit: () => openEditModal(row),
        onDelete: () => deleteItem(row),
        deleteConfirmText: `Hapus SKPD "${row.name}"?`,
      }),
  },
]

onMounted(fetchData)
</script>

<template>
  <CrudPage
    title="Manajemen SKPD"
    subtitle="Kelola Satuan Kerja Perangkat Daerah"
    :loading="loading"
    :data="skpdList"
    :columns="columns"
    :show-modal="showModal"
    :modal-title="isEditing ? 'Edit SKPD' : 'Tambah SKPD Baru'"
    :modal-loading="saving"
    add-button-text="Tambah SKPD"
    empty-text="Belum ada data SKPD"
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
        <div class="grid grid-cols-2 gap-4">
          <FormInput
            v-model="form.code"
            label="Kode SKPD"
            placeholder="1.01.01"
            required
          />
          <FormInput
            v-model="form.short_name"
            label="Nama Singkat"
            placeholder="Dinas Pendidikan"
          />
        </div>

        <FormInput
          v-model="form.name"
          label="Nama Lengkap SKPD"
          placeholder="Dinas Pendidikan, Pemuda dan Olahraga"
          required
        />

        <FormInput
          v-model="form.address"
          label="Alamat"
          type="textarea"
          :rows="2"
        />

        <div class="grid grid-cols-2 gap-4">
          <FormInput
            v-model="form.phone"
            label="Telepon"
            placeholder="(0361) 123456"
          />
          <FormInput
            v-model="form.email"
            label="Email"
            placeholder="dinas@example.go.id"
          />
        </div>

        <div class="grid grid-cols-2 gap-4">
          <FormInput
            v-model="form.head_name"
            label="Nama Kepala SKPD"
          />
          <FormInput
            v-model="form.head_nip"
            label="NIP Kepala"
            placeholder="198001012005011001"
          />
        </div>

        <FormCheckbox v-model="form.is_active" label="SKPD Aktif" />
      </div>
    </template>
  </CrudPage>
</template>
