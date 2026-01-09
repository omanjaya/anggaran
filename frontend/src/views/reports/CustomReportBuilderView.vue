<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import {
  NButton,
  NModal,
  NInput,
  NCheckbox,
  NCheckboxGroup,
  NSpace,
  NIcon,
  NTag,
  NGrid,
  NGridItem,
  NEmpty,
} from 'naive-ui'
import { AddOutline, DocumentOutline, DocumentTextOutline, CreateOutline, TrashOutline } from '@vicons/ionicons5'
import { PageHeader, PageCard, LoadingSpinner, FormInput, FormSelect } from '@/components/ui'
import { useFormat, useMessage } from '@/composables'
import api from '@/services/api'

interface ReportTemplate {
  id: number
  name: string
  description: string | null
  report_type: string
  config: Record<string, any>
  is_public: boolean
  created_at: string
}

const { formatCurrency } = useFormat()
const message = useMessage()

const loading = ref(true)
const templates = ref<ReportTemplate[]>([])
const showCreateModal = ref(false)
const showGenerateModal = ref(false)
const selectedTemplate = ref<ReportTemplate | null>(null)
const submitting = ref(false)
const generating = ref(false)
const reportData = ref<any>(null)

const templateForm = ref({
  name: '',
  description: '',
  report_type: 'summary',
  is_public: false,
  config: {
    columns: [] as string[],
    filters: {} as Record<string, any>,
    groupBy: '',
    sortBy: '',
    showTotals: true,
  },
})

const generateForm = ref({
  year: new Date().getFullYear(),
  month: new Date().getMonth() + 1,
  program_id: null as number | null,
  activity_id: null as number | null,
})

const reportTypes = [
  { value: 'summary', label: 'Ringkasan Anggaran' },
  { value: 'realization', label: 'Laporan Realisasi' },
  { value: 'deviation', label: 'Analisis Deviasi' },
  { value: 'comparison', label: 'Perbandingan Periodik' },
  { value: 'custom', label: 'Custom Query' },
]

const availableColumns = [
  { value: 'program_name', label: 'Program' },
  { value: 'activity_name', label: 'Kegiatan' },
  { value: 'sub_activity_name', label: 'Sub Kegiatan' },
  { value: 'budget_item_name', label: 'Item Belanja' },
  { value: 'budget_amount', label: 'Anggaran' },
  { value: 'planned_amount', label: 'Rencana' },
  { value: 'realized_amount', label: 'Realisasi' },
  { value: 'deviation_amount', label: 'Deviasi' },
  { value: 'deviation_percentage', label: 'Persentase Deviasi' },
  { value: 'realization_percentage', label: 'Persentase Realisasi' },
]

const years = computed(() => {
  const currentYear = new Date().getFullYear()
  return Array.from({ length: 5 }, (_, i) => ({
    label: String(currentYear - 2 + i),
    value: currentYear - 2 + i,
  }))
})

const months = [
  { value: 0, label: 'Semua Bulan' },
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

async function fetchTemplates() {
  loading.value = true
  try {
    const response = await api.get('/custom-reports/templates')
    templates.value = response.data.data
  } catch (err) {
    console.error('Failed to fetch templates:', err)
  } finally {
    loading.value = false
  }
}

function openCreateModal() {
  selectedTemplate.value = null
  templateForm.value = {
    name: '',
    description: '',
    report_type: 'summary',
    is_public: false,
    config: {
      columns: ['program_name', 'budget_amount', 'realized_amount', 'realization_percentage'],
      filters: {},
      groupBy: '',
      sortBy: '',
      showTotals: true,
    },
  }
  showCreateModal.value = true
}

function openEditModal(template: ReportTemplate) {
  selectedTemplate.value = template
  templateForm.value = {
    name: template.name,
    description: template.description || '',
    report_type: template.report_type,
    is_public: template.is_public,
    config: {
      columns: template.config.columns || [],
      filters: template.config.filters || {},
      groupBy: template.config.groupBy || '',
      sortBy: template.config.sortBy || '',
      showTotals: template.config.showTotals ?? true,
    },
  }
  showCreateModal.value = true
}

function openGenerateModal(template: ReportTemplate) {
  selectedTemplate.value = template
  generateForm.value = {
    year: new Date().getFullYear(),
    month: new Date().getMonth() + 1,
    program_id: null,
    activity_id: null,
  }
  reportData.value = null
  showGenerateModal.value = true
}

async function saveTemplate() {
  submitting.value = true
  try {
    if (selectedTemplate.value) {
      await api.put(`/custom-reports/templates/${selectedTemplate.value.id}`, templateForm.value)
      message.success('Template berhasil diupdate')
    } else {
      await api.post('/custom-reports/templates', templateForm.value)
      message.success('Template berhasil dibuat')
    }
    showCreateModal.value = false
    fetchTemplates()
  } catch (err) {
    console.error('Failed to save template:', err)
    message.error('Gagal menyimpan template')
  } finally {
    submitting.value = false
  }
}

async function deleteTemplate(id: number) {
  const confirmed = await message.confirm('Apakah Anda yakin ingin menghapus template ini?')
  if (!confirmed) return
  try {
    await api.delete(`/custom-reports/templates/${id}`)
    message.success('Template berhasil dihapus')
    fetchTemplates()
  } catch (err) {
    console.error('Failed to delete template:', err)
    message.error('Gagal menghapus template')
  }
}

async function generateReport() {
  if (!selectedTemplate.value) return
  generating.value = true
  try {
    const response = await api.post('/custom-reports/generate', {
      template_id: selectedTemplate.value.id,
      ...generateForm.value,
    })
    reportData.value = response.data.data
  } catch (err) {
    console.error('Failed to generate report:', err)
    message.error('Gagal generate laporan')
  } finally {
    generating.value = false
  }
}

async function exportPdf() {
  if (!selectedTemplate.value) return
  try {
    const response = await api.post(
      '/custom-reports/export-pdf',
      {
        template_id: selectedTemplate.value.id,
        ...generateForm.value,
      },
      { responseType: 'blob' }
    )

    const url = window.URL.createObjectURL(new Blob([response.data]))
    const link = document.createElement('a')
    link.href = url
    link.setAttribute('download', `laporan-${selectedTemplate.value.name.toLowerCase().replace(/\s+/g, '-')}.pdf`)
    document.body.appendChild(link)
    link.click()
    link.remove()
  } catch (err) {
    console.error('Failed to export PDF:', err)
    message.error('Gagal export PDF')
  }
}

async function exportExcel() {
  if (!selectedTemplate.value) return
  try {
    const response = await api.post(
      '/custom-reports/export-excel',
      {
        template_id: selectedTemplate.value.id,
        ...generateForm.value,
      },
      { responseType: 'blob' }
    )

    const url = window.URL.createObjectURL(new Blob([response.data]))
    const link = document.createElement('a')
    link.href = url
    link.setAttribute('download', `laporan-${selectedTemplate.value.name.toLowerCase().replace(/\s+/g, '-')}.xlsx`)
    document.body.appendChild(link)
    link.click()
    link.remove()
  } catch (err) {
    console.error('Failed to export Excel:', err)
    message.error('Gagal export Excel')
  }
}

onMounted(fetchTemplates)
</script>

<template>
  <div>
    <PageHeader title="Custom Report Builder" subtitle="Buat dan kelola template laporan kustom">
      <template #actions>
        <NButton type="primary" @click="openCreateModal">
          <template #icon>
            <NIcon><AddOutline /></NIcon>
          </template>
          Buat Template Baru
        </NButton>
      </template>
    </PageHeader>

    <LoadingSpinner v-if="loading" />

    <template v-else>
      <!-- Templates Grid -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <PageCard
          v-for="template in templates"
          :key="template.id"
          class="hover:shadow-lg transition-shadow"
        >
          <div class="flex items-center justify-between mb-3">
            <h3 class="text-lg font-semibold text-gray-900">{{ template.name }}</h3>
            <NTag :type="template.is_public ? 'success' : 'default'" size="small">
              {{ template.is_public ? 'Publik' : 'Privat' }}
            </NTag>
          </div>
          <p class="text-sm text-gray-600 mb-4">
            {{ template.description || 'Tidak ada deskripsi' }}
          </p>
          <div class="flex items-center gap-2 text-xs text-gray-500 mb-4">
            <NTag type="info" size="small">
              {{ reportTypes.find((t) => t.value === template.report_type)?.label || template.report_type }}
            </NTag>
          </div>
          <div class="flex justify-between items-center pt-4 border-t">
            <NSpace>
              <NButton text type="primary" @click="openEditModal(template)">
                <template #icon>
                  <NIcon><CreateOutline /></NIcon>
                </template>
                Edit
              </NButton>
              <NButton text type="error" @click="deleteTemplate(template.id)">
                <template #icon>
                  <NIcon><TrashOutline /></NIcon>
                </template>
                Hapus
              </NButton>
            </NSpace>
            <NButton type="primary" size="small" @click="openGenerateModal(template)">
              Generate
            </NButton>
          </div>
        </PageCard>

        <div v-if="templates.length === 0" class="col-span-full">
          <NEmpty description="Belum ada template laporan. Klik 'Buat Template Baru' untuk memulai." />
        </div>
      </div>
    </template>

    <!-- Create/Edit Template Modal -->
    <NModal v-model:show="showCreateModal" preset="card" :title="selectedTemplate ? 'Edit Template' : 'Buat Template Baru'" style="width: 700px">
      <form @submit.prevent="saveTemplate" class="space-y-4">
        <FormInput v-model="templateForm.name" label="Nama Template" placeholder="Nama template" required />

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Deskripsi</label>
          <NInput v-model:value="templateForm.description" type="textarea" :rows="2" placeholder="Deskripsi template" />
        </div>

        <FormSelect
          v-model="templateForm.report_type"
          label="Tipe Laporan"
          :options="reportTypes"
        />

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">Kolom yang Ditampilkan</label>
          <NCheckboxGroup v-model:value="templateForm.config.columns">
            <NGrid :cols="2" :x-gap="12" :y-gap="8">
              <NGridItem v-for="col in availableColumns" :key="col.value">
                <NCheckbox :value="col.value" :label="col.label" />
              </NGridItem>
            </NGrid>
          </NCheckboxGroup>
        </div>

        <NCheckbox v-model:checked="templateForm.config.showTotals">Tampilkan Total</NCheckbox>

        <NCheckbox v-model:checked="templateForm.is_public">Template Publik (dapat diakses semua user)</NCheckbox>

        <div class="flex justify-end gap-3 pt-4">
          <NButton @click="showCreateModal = false">Batal</NButton>
          <NButton type="primary" attr-type="submit" :loading="submitting">
            Simpan Template
          </NButton>
        </div>
      </form>
    </NModal>

    <!-- Generate Report Modal -->
    <NModal v-model:show="showGenerateModal" preset="card" :title="`Generate: ${selectedTemplate?.name}`" style="width: 1000px">
      <!-- Filters -->
      <div class="grid grid-cols-4 gap-4 mb-6">
        <FormSelect v-model="generateForm.year" label="Tahun" :options="years" />
        <FormSelect v-model="generateForm.month" label="Bulan" :options="months" />
        <div class="col-span-2 flex items-end gap-2">
          <NButton type="primary" :loading="generating" @click="generateReport">
            Generate
          </NButton>
          <NButton v-if="reportData" type="error" @click="exportPdf">
            <template #icon>
              <NIcon><DocumentOutline /></NIcon>
            </template>
            PDF
          </NButton>
          <NButton v-if="reportData" type="success" @click="exportExcel">
            <template #icon>
              <NIcon><DocumentTextOutline /></NIcon>
            </template>
            Excel
          </NButton>
        </div>
      </div>

      <!-- Report Preview -->
      <LoadingSpinner v-if="generating" />

      <div v-else-if="reportData" class="border rounded-lg overflow-hidden">
        <div class="bg-gray-50 px-4 py-3 border-b">
          <h4 class="font-semibold">{{ reportData.title }}</h4>
          <p class="text-sm text-gray-600">{{ reportData.subtitle }}</p>
        </div>
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">No</th>
                <th
                  v-for="col in reportData.columns"
                  :key="col.key"
                  class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase"
                >
                  {{ col.label }}
                </th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              <tr v-for="(row, idx) in (reportData.data as any[])" :key="idx" class="hover:bg-gray-50">
                <td class="px-4 py-3 text-sm text-gray-600">{{ Number(idx) + 1 }}</td>
                <td
                  v-for="col in reportData.columns"
                  :key="col.key"
                  class="px-4 py-3 text-sm text-gray-900"
                  :class="col.type === 'currency' || col.type === 'percentage' ? 'text-right' : ''"
                >
                  <template v-if="col.type === 'currency'">
                    {{ formatCurrency(row[col.key] || 0) }}
                  </template>
                  <template v-else-if="col.type === 'percentage'">
                    {{ (row[col.key] || 0).toFixed(1) }}%
                  </template>
                  <template v-else>
                    {{ row[col.key] || '-' }}
                  </template>
                </td>
              </tr>
            </tbody>
            <tfoot v-if="reportData.totals" class="bg-gray-100 font-semibold">
              <tr>
                <td class="px-4 py-3 text-sm" colspan="2">Total</td>
                <td
                  v-for="(total, idx) in reportData.totals"
                  :key="idx"
                  class="px-4 py-3 text-sm text-right"
                >
                  <template v-if="total.type === 'currency'">
                    {{ formatCurrency(total.value || 0) }}
                  </template>
                  <template v-else-if="total.type === 'percentage'">
                    {{ (total.value || 0).toFixed(1) }}%
                  </template>
                  <template v-else>
                    {{ total.value || '-' }}
                  </template>
                </td>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>

      <NEmpty v-else description="Klik 'Generate' untuk menghasilkan laporan" />
    </NModal>
  </div>
</template>
