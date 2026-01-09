<script setup lang="ts">
import { h, ref, onMounted, computed } from 'vue'
import { NButton, NModal, NDataTable, NSpace, NIcon, NTag, NInput, NCheckbox } from 'naive-ui'
import type { DataTableColumns } from 'naive-ui'
import { CheckmarkCircleOutline, CloseCircleOutline, DownloadOutline, RefreshOutline } from '@vicons/ionicons5'
import { PageHeader, PageCard, LoadingSpinner, FormSelect } from '@/components/ui'
import { useFormat, useMessage } from '@/composables'
import api from '@/services/api'

interface Realization {
  id: number
  budget_item_id: number
  month: number
  year: number
  realization_volume: number
  realization_unit_price: number
  realization_amount: number
  status: string
  notes: string | null
  input_by: number | null
  input_date: string
  budget_item?: {
    id: number
    account_code: string
    description: string
    unit: string
    unit_price: number
    total_volume: number
    total_amount: number
    sub_activity?: {
      category: string
      name: string
    }
  }
  monthly_plan?: {
    planned_volume: number
    planned_amount: number
  }
  documents?: Array<{
    id: number
    document_type: string
    file_name: string
    file_size: number
  }>
  user?: {
    id: number
    name: string
    email: string
  }
}

interface VerificationChecklist {
  documentsComplete: boolean
  priceReasonable: boolean
  volumeMatches: boolean
  calculationCorrect: boolean
}

const { formatCurrency, formatDate } = useFormat()
const message = useMessage()

const loading = ref(false)
const pendingItems = ref<Realization[]>([])
const selectedItem = ref<Realization | null>(null)
const showDetailModal = ref(false)
const verificationNotes = ref('')
const checklist = ref<VerificationChecklist>({
  documentsComplete: false,
  priceReasonable: false,
  volumeMatches: false,
  calculationCorrect: false,
})
const selectedIds = ref<number[]>([])
const processing = ref(false)

const filters = ref({
  month: undefined as number | undefined,
  year: new Date().getFullYear(),
  category: '',
})

const monthNames = [
  'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
  'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
]

const months = [
  { value: undefined as number | undefined, label: 'Semua Bulan' },
  ...monthNames.map((m, i) => ({ value: i + 1, label: m })),
]

const years = computed(() => {
  const currentYear = new Date().getFullYear()
  return Array.from({ length: 5 }, (_, i) => ({ label: String(currentYear - 2 + i), value: currentYear - 2 + i }))
})

const categories = [
  { value: '', label: 'Semua Kategori' },
  { value: 'ANALISIS', label: 'ANALISIS' },
  { value: 'TATA_KELOLA', label: 'TATA KELOLA' },
  { value: 'OPERASIONALISASI', label: 'OPERASIONALISASI' },
  { value: 'LAYANAN', label: 'LAYANAN' },
  { value: 'ELEK_NON_ELEK', label: 'ELEK & NON ELEK' },
]

const isChecklistComplete = computed(() =>
  checklist.value.documentsComplete &&
  checklist.value.priceReasonable &&
  checklist.value.volumeMatches &&
  checklist.value.calculationCorrect
)

const priceDeviation = computed(() => {
  if (!selectedItem.value?.budget_item) return 0
  const planned = selectedItem.value.budget_item.unit_price
  const actual = selectedItem.value.realization_unit_price
  if (planned === 0) return 0
  return ((actual - planned) / planned) * 100
})

const volumeDeviation = computed(() => {
  if (!selectedItem.value?.monthly_plan) return 0
  const planned = selectedItem.value.monthly_plan.planned_volume
  const actual = selectedItem.value.realization_volume
  if (planned === 0) return 0
  return ((actual - planned) / planned) * 100
})

const selectedCount = computed(() => selectedIds.value.length)

async function fetchPendingItems() {
  loading.value = true
  selectedIds.value = []
  try {
    const params: Record<string, any> = {}
    if (filters.value.month) params.month = filters.value.month
    if (filters.value.year) params.year = filters.value.year
    if (filters.value.category) params.category = filters.value.category

    const response = await api.get('/realizations/pending-verification', { params })
    const data = response.data.data
    pendingItems.value = Array.isArray(data) ? data : (data?.data || [])
  } catch (error) {
    console.error('Failed to fetch pending items:', error)
  } finally {
    loading.value = false
  }
}

async function openDetail(item: Realization) {
  selectedItem.value = item
  verificationNotes.value = ''
  checklist.value = {
    documentsComplete: false,
    priceReasonable: false,
    volumeMatches: false,
    calculationCorrect: false,
  }
  showDetailModal.value = true

  try {
    const response = await api.get(`/realizations/${item.id}/documents`)
    if (selectedItem.value) {
      selectedItem.value.documents = response.data.data
    }
  } catch (error) {
    console.error('Failed to fetch documents:', error)
  }
}

async function verifyItem() {
  if (!selectedItem.value) return
  if (!isChecklistComplete.value) {
    message.warning('Harap lengkapi semua checklist verifikasi')
    return
  }

  processing.value = true
  try {
    await api.post(`/realizations/${selectedItem.value.id}/verify`, {
      notes: verificationNotes.value,
      checklist: checklist.value,
    })
    message.success('Realisasi berhasil diverifikasi')
    showDetailModal.value = false
    await fetchPendingItems()
  } catch (error: any) {
    message.error(error.response?.data?.message || 'Gagal memverifikasi')
  } finally {
    processing.value = false
  }
}

async function rejectItem() {
  if (!selectedItem.value) return
  if (!verificationNotes.value.trim()) {
    message.warning('Harap isi alasan penolakan')
    return
  }

  const confirmed = await message.confirm('Apakah Anda yakin ingin menolak realisasi ini?')
  if (!confirmed) return

  processing.value = true
  try {
    await api.post(`/realizations/${selectedItem.value.id}/verify`, {
      action: 'reject',
      notes: verificationNotes.value,
      checklist: checklist.value,
    })
    message.success('Realisasi telah ditolak')
    showDetailModal.value = false
    await fetchPendingItems()
  } catch (error: any) {
    message.error(error.response?.data?.message || 'Gagal menolak')
  } finally {
    processing.value = false
  }
}

async function downloadDocument(realization: Realization, doc: any) {
  try {
    const response = await api.get(
      `/realizations/${realization.id}/documents/${doc.id}/download`,
      { responseType: 'blob' }
    )
    const url = window.URL.createObjectURL(response.data)
    const link = document.createElement('a')
    link.href = url
    link.download = doc.file_name
    link.click()
    window.URL.revokeObjectURL(url)
  } catch (error) {
    message.error('Gagal download dokumen')
  }
}

async function batchVerify() {
  if (selectedIds.value.length === 0) {
    message.warning('Pilih minimal 1 item')
    return
  }

  const confirmed = await message.confirm(`Verifikasi ${selectedIds.value.length} item sekaligus?`)
  if (!confirmed) return

  processing.value = true
  try {
    await api.post('/realizations/batch-verify', { ids: selectedIds.value })
    message.success(`${selectedIds.value.length} item berhasil diverifikasi`)
    selectedIds.value = []
    await fetchPendingItems()
  } catch (error: any) {
    message.error(error.response?.data?.message || 'Gagal verifikasi batch')
  } finally {
    processing.value = false
  }
}

const columns: DataTableColumns<Realization> = [
  { type: 'selection', disabled: () => false },
  {
    title: 'Bulan',
    key: 'month',
    width: 100,
    render: (row) =>
      h('div', [
        h('div', { class: 'font-medium' }, monthNames[row.month - 1]),
        h('div', { class: 'text-xs text-gray-500' }, String(row.year)),
      ]),
  },
  {
    title: 'Kategori',
    key: 'category',
    width: 130,
    render: (row) =>
      h(NTag, { type: 'info', size: 'small' }, () => row.budget_item?.sub_activity?.category || '-'),
  },
  {
    title: 'Item Belanja',
    key: 'budget_item',
    render: (row) =>
      h('div', [
        h('div', { class: 'font-mono text-xs text-gray-500' }, row.budget_item?.account_code),
        h('div', { class: 'text-sm' }, row.budget_item?.description),
      ]),
  },
  {
    title: 'Jumlah',
    key: 'realization_amount',
    width: 150,
    align: 'right',
    render: (row) =>
      h('div', [
        h('div', { class: 'font-medium' }, formatCurrency(row.realization_amount)),
        h('div', { class: 'text-xs text-gray-500' }, `${row.realization_volume} ${row.budget_item?.unit}`),
      ]),
  },
  {
    title: 'Diinput Oleh',
    key: 'user',
    width: 150,
    render: (row) =>
      h('div', [
        h('div', { class: 'text-sm' }, row.user?.name || '-'),
        h('div', { class: 'text-xs text-gray-500' }, formatDate(row.input_date, { format: 'datetime' })),
      ]),
  },
  {
    title: 'Dokumen',
    key: 'documents',
    width: 80,
    align: 'center',
    render: (row) => h(NTag, { size: 'small' }, () => `${row.documents?.length || 0} file`),
  },
  {
    title: 'Aksi',
    key: 'actions',
    width: 100,
    align: 'center',
    render: (row) =>
      h(
        NButton,
        { type: 'primary', size: 'small', onClick: () => openDetail(row) },
        () => 'Verifikasi'
      ),
  },
]

onMounted(fetchPendingItems)
</script>

<template>
  <div>
    <PageHeader title="Verifikasi Bendahara" subtitle="Verifikasi realisasi belanja sebelum approval Kadis">
      <template #actions>
        <NSpace>
          <NTag type="warning">{{ pendingItems.length }} item menunggu verifikasi</NTag>
          <NButton
            v-if="selectedCount > 0"
            type="success"
            :loading="processing"
            @click="batchVerify"
          >
            <template #icon><NIcon><CheckmarkCircleOutline /></NIcon></template>
            Verifikasi {{ selectedCount }} Item
          </NButton>
        </NSpace>
      </template>
    </PageHeader>

    <!-- Filters -->
    <PageCard class="mb-6">
      <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <FormSelect
          v-model="filters.month"
          label="Bulan"
          :options="months"
          @update:model-value="fetchPendingItems"
        />
        <FormSelect
          v-model="filters.year"
          label="Tahun"
          :options="years"
          @update:model-value="fetchPendingItems"
        />
        <FormSelect
          v-model="filters.category"
          label="Kategori"
          :options="categories"
          @update:model-value="fetchPendingItems"
        />
        <div class="flex items-end">
          <NButton @click="fetchPendingItems">
            <template #icon><NIcon><RefreshOutline /></NIcon></template>
            Refresh
          </NButton>
        </div>
      </div>
    </PageCard>

    <!-- Table -->
    <PageCard :padding="false">
      <LoadingSpinner v-if="loading" />

      <div v-else-if="pendingItems.length === 0" class="text-center py-12 text-gray-500">
        Tidak ada item yang menunggu verifikasi
      </div>

      <NDataTable
        v-else
        :columns="columns"
        :data="pendingItems"
        :row-key="(row: Realization) => row.id"
        :checked-row-keys="selectedIds"
        @update:checked-row-keys="(keys: any[]) => (selectedIds = keys as number[])"
        :bordered="false"
        striped
      />
    </PageCard>

    <!-- Verification Modal -->
    <NModal v-model:show="showDetailModal" preset="card" title="Verifikasi Realisasi" style="width: 1000px">
      <div v-if="selectedItem">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <!-- Left: Item Info -->
          <div>
            <h4 class="font-medium text-gray-700 mb-3">Informasi Item</h4>
            <div class="bg-gray-50 rounded-lg p-4 space-y-3">
              <div>
                <div class="text-xs text-gray-500">Kode Rekening</div>
                <div class="font-mono">{{ selectedItem.budget_item?.account_code }}</div>
              </div>
              <div>
                <div class="text-xs text-gray-500">Deskripsi</div>
                <div class="font-medium">{{ selectedItem.budget_item?.description }}</div>
              </div>
              <div>
                <div class="text-xs text-gray-500">Bulan</div>
                <div>{{ monthNames[selectedItem.month - 1] }} {{ selectedItem.year }}</div>
              </div>
            </div>

            <!-- Compare Plan vs Realization -->
            <h4 class="font-medium text-gray-700 mt-4 mb-3">Perbandingan Rencana vs Realisasi</h4>
            <div class="bg-gray-50 rounded-lg p-4">
              <table class="w-full text-sm">
                <thead>
                  <tr>
                    <th class="text-left pb-2"></th>
                    <th class="text-right pb-2">Rencana</th>
                    <th class="text-right pb-2">Realisasi</th>
                    <th class="text-right pb-2">Deviasi</th>
                  </tr>
                </thead>
                <tbody>
                  <tr>
                    <td class="py-1">Volume</td>
                    <td class="text-right">{{ selectedItem.monthly_plan?.planned_volume || 0 }} {{ selectedItem.budget_item?.unit }}</td>
                    <td class="text-right">{{ selectedItem.realization_volume }} {{ selectedItem.budget_item?.unit }}</td>
                    <td :class="['text-right font-medium', Math.abs(volumeDeviation) > 10 ? 'text-red-600' : 'text-green-600']">
                      {{ volumeDeviation.toFixed(1) }}%
                    </td>
                  </tr>
                  <tr>
                    <td class="py-1">Harga Satuan</td>
                    <td class="text-right">{{ formatCurrency(selectedItem.budget_item?.unit_price || 0) }}</td>
                    <td class="text-right">{{ formatCurrency(selectedItem.realization_unit_price) }}</td>
                    <td :class="['text-right font-medium', Math.abs(priceDeviation) > 10 ? 'text-red-600' : 'text-green-600']">
                      {{ priceDeviation.toFixed(1) }}%
                    </td>
                  </tr>
                  <tr class="font-medium border-t">
                    <td class="py-1">Jumlah</td>
                    <td class="text-right">{{ formatCurrency(selectedItem.monthly_plan?.planned_amount || 0) }}</td>
                    <td class="text-right">{{ formatCurrency(selectedItem.realization_amount) }}</td>
                    <td></td>
                  </tr>
                </tbody>
              </table>
            </div>

            <!-- Documents -->
            <h4 class="font-medium text-gray-700 mt-4 mb-3">Dokumen Pendukung</h4>
            <div class="space-y-2">
              <div
                v-for="doc in selectedItem.documents"
                :key="doc.id"
                class="flex items-center justify-between bg-gray-50 rounded px-3 py-2"
              >
                <div>
                  <div class="text-sm">{{ doc.file_name }}</div>
                  <div class="text-xs text-gray-500">{{ doc.document_type }}</div>
                </div>
                <NButton quaternary size="small" @click="downloadDocument(selectedItem!, doc)">
                  <template #icon><NIcon><DownloadOutline /></NIcon></template>
                </NButton>
              </div>
              <div
                v-if="!selectedItem.documents?.length"
                class="text-sm text-gray-500 text-center py-4 bg-gray-50 rounded"
              >
                Tidak ada dokumen
              </div>
            </div>
          </div>

          <!-- Right: Verification Checklist -->
          <div>
            <h4 class="font-medium text-gray-700 mb-3">Checklist Verifikasi</h4>
            <div class="border rounded-lg p-4 space-y-4">
              <NCheckbox v-model:checked="checklist.documentsComplete">
                <div>
                  <div class="font-medium text-gray-800">Dokumen Lengkap dan Sesuai</div>
                  <div class="text-sm text-gray-500">Kwitansi, invoice, dan bukti pendukung lainnya lengkap</div>
                </div>
              </NCheckbox>
              <NCheckbox v-model:checked="checklist.priceReasonable">
                <div>
                  <div class="font-medium text-gray-800">Harga Satuan Wajar</div>
                  <div class="text-sm text-gray-500">Harga sesuai dengan standar atau HPS yang ditetapkan</div>
                </div>
              </NCheckbox>
              <NCheckbox v-model:checked="checklist.volumeMatches">
                <div>
                  <div class="font-medium text-gray-800">Volume Sesuai Kontrak/PO</div>
                  <div class="text-sm text-gray-500">Jumlah barang/jasa sesuai dengan pengadaan</div>
                </div>
              </NCheckbox>
              <NCheckbox v-model:checked="checklist.calculationCorrect">
                <div>
                  <div class="font-medium text-gray-800">Perhitungan Total Benar</div>
                  <div class="text-sm text-gray-500">Volume x Harga Satuan = Total (tidak ada kesalahan hitung)</div>
                </div>
              </NCheckbox>
            </div>

            <!-- Notes -->
            <h4 class="font-medium text-gray-700 mt-4 mb-3">Catatan Verifikasi</h4>
            <NInput
              v-model:value="verificationNotes"
              type="textarea"
              :rows="4"
              placeholder="Catatan dari Bendahara (wajib diisi jika ditolak)..."
            />

            <!-- Status Indicator -->
            <div
              class="mt-4 p-4 rounded-lg"
              :class="isChecklistComplete ? 'bg-green-50' : 'bg-yellow-50'"
            >
              <div class="flex items-center gap-2">
                <NIcon
                  size="20"
                  :color="isChecklistComplete ? '#22c55e' : '#eab308'"
                >
                  <CheckmarkCircleOutline v-if="isChecklistComplete" />
                  <CloseCircleOutline v-else />
                </NIcon>
                <span :class="isChecklistComplete ? 'text-green-800' : 'text-yellow-800'">
                  {{ isChecklistComplete ? 'Checklist lengkap, siap diverifikasi' : 'Lengkapi semua checklist untuk verifikasi' }}
                </span>
              </div>
            </div>
          </div>
        </div>

        <div class="flex justify-end gap-3 pt-4 mt-4 border-t">
          <NButton @click="showDetailModal = false">Tutup</NButton>
          <NButton type="error" :loading="processing" @click="rejectItem">
            <template #icon><NIcon><CloseCircleOutline /></NIcon></template>
            Tolak
          </NButton>
          <NButton type="success" :loading="processing" :disabled="!isChecklistComplete" @click="verifyItem">
            <template #icon><NIcon><CheckmarkCircleOutline /></NIcon></template>
            {{ processing ? 'Memproses...' : 'Verifikasi' }}
          </NButton>
        </div>
      </div>
    </NModal>
  </div>
</template>
