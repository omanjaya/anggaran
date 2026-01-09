<script setup lang="ts">
import { ref, onMounted, computed, watch } from 'vue'
import { NButton, NSelect, NSpace, NIcon, NTag } from 'naive-ui'
import { DocumentOutline, DocumentTextOutline } from '@vicons/ionicons5'
import { PageHeader, PageCard, StatCard, LoadingSpinner } from '@/components/ui'
import { useFormat } from '@/composables'
import api from '@/services/api'

interface RealisasiItem {
  id: number
  code: string
  name: string
  volume: number
  unit: string
  budget: number
  planned: number
  realized: number
  physical_target: number
  physical_progress: number
  balance: number
}

interface MonthData {
  month: number
  month_name: string
  items: RealisasiItem[]
  totals: {
    budget: number
    planned: number
    realized: number
  }
}

const { formatCurrency } = useFormat()

const loading = ref(true)
const exporting = ref(false)
const selectedYear = ref(new Date().getFullYear())
const selectedMonth = ref<number | undefined>(undefined)
const selectedProgram = ref<number | undefined>(undefined)
const programs = ref<{ id: number; name: string; code: string }[]>([])
const monthsData = ref<Record<number, MonthData>>({})

const years = computed(() => {
  const currentYear = new Date().getFullYear()
  return Array.from({ length: 5 }, (_, i) => ({
    label: String(currentYear - 2 + i),
    value: currentYear - 2 + i,
  }))
})

const months = [
  { value: undefined as number | undefined, label: 'Semua Bulan' },
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

const programOptions = computed(() => [
  { label: 'Semua Program', value: undefined as number | undefined },
  ...programs.value.map((p) => ({ label: `${p.code} - ${p.name}`, value: p.id })),
])

const displayMonths = computed((): MonthData[] => {
  if (selectedMonth.value) {
    const month = monthsData.value[selectedMonth.value]
    return month ? [month] : []
  }
  return Object.values(monthsData.value).filter((m): m is MonthData => m !== undefined)
})

const totalSummary = computed(() => {
  const data = displayMonths.value
  return {
    budget: data.reduce((sum, m) => sum + (m?.totals?.budget || 0), 0),
    planned: data.reduce((sum, m) => sum + (m?.totals?.planned || 0), 0),
    realized: data.reduce((sum, m) => sum + (m?.totals?.realized || 0), 0),
  }
})

const overallPercentage = computed(() => {
  if (totalSummary.value.budget === 0) return 0
  return (totalSummary.value.realized / totalSummary.value.budget) * 100
})

async function fetchPrograms() {
  try {
    const response = await api.get('/programs')
    programs.value = response.data.data
  } catch (err) {
    console.error('Failed to fetch programs:', err)
  }
}

async function fetchRealisasi() {
  loading.value = true
  try {
    const params: Record<string, any> = { year: selectedYear.value }
    if (selectedMonth.value) params.month = selectedMonth.value
    if (selectedProgram.value) params.program_id = selectedProgram.value

    const response = await api.get('/reports/realisasi', { params })
    monthsData.value = response.data.data.months
  } catch (err) {
    console.error('Failed to fetch realisasi:', err)
  } finally {
    loading.value = false
  }
}

async function exportPdf() {
  exporting.value = true
  try {
    const params: Record<string, any> = { year: selectedYear.value }
    if (selectedMonth.value) params.month = selectedMonth.value
    if (selectedProgram.value) params.program_id = selectedProgram.value

    const response = await api.get('/reports/export/realisasi/pdf', {
      params,
      responseType: 'blob',
    })

    const url = window.URL.createObjectURL(new Blob([response.data]))
    const link = document.createElement('a')
    link.href = url
    const monthStr = selectedMonth.value ? `_${selectedMonth.value}` : ''
    link.setAttribute('download', `laporan_realisasi_${selectedYear.value}${monthStr}.pdf`)
    document.body.appendChild(link)
    link.click()
    link.remove()
  } catch (err) {
    console.error('Failed to export PDF:', err)
  } finally {
    exporting.value = false
  }
}

async function exportExcel() {
  exporting.value = true
  try {
    const params: Record<string, any> = { year: selectedYear.value }
    if (selectedMonth.value) params.month = selectedMonth.value
    if (selectedProgram.value) params.program_id = selectedProgram.value

    const response = await api.get('/reports/export/realisasi/excel', {
      params,
      responseType: 'blob',
    })

    const url = window.URL.createObjectURL(new Blob([response.data]))
    const link = document.createElement('a')
    link.href = url
    const monthStr = selectedMonth.value ? `_${selectedMonth.value}` : ''
    link.setAttribute('download', `laporan_realisasi_${selectedYear.value}${monthStr}.xlsx`)
    document.body.appendChild(link)
    link.click()
    link.remove()
  } catch (err) {
    console.error('Failed to export Excel:', err)
  } finally {
    exporting.value = false
  }
}

watch([selectedYear, selectedMonth, selectedProgram], () => {
  fetchRealisasi()
})

onMounted(() => {
  fetchPrograms()
  fetchRealisasi()
})
</script>

<template>
  <div>
    <PageHeader title="Laporan Realisasi" subtitle="Format Laporan Realisasi Fisik dan Keuangan">
      <template #actions>
        <NSpace>
          <NSelect
            v-model:value="selectedYear"
            :options="years"
            style="width: 120px"
          />
          <NSelect
            v-model:value="selectedMonth"
            :options="months"
            style="width: 150px"
          />
          <NSelect
            v-model:value="selectedProgram"
            :options="programOptions"
            filterable
            style="width: 250px"
          />
          <NButton type="error" :loading="exporting" @click="exportPdf">
            <template #icon>
              <NIcon><DocumentOutline /></NIcon>
            </template>
            PDF
          </NButton>
          <NButton type="success" :loading="exporting" @click="exportExcel">
            <template #icon>
              <NIcon><DocumentTextOutline /></NIcon>
            </template>
            Excel
          </NButton>
        </NSpace>
      </template>
    </PageHeader>

    <!-- Summary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
      <StatCard label="Total Anggaran" :value="formatCurrency(totalSummary.budget)" />
      <StatCard label="Total Rencana" :value="formatCurrency(totalSummary.planned)" variant="primary" />
      <StatCard label="Total Realisasi" :value="formatCurrency(totalSummary.realized)" variant="success" />
      <StatCard label="Persentase Realisasi" :value="`${overallPercentage.toFixed(1)}%`" variant="warning" />
    </div>

    <LoadingSpinner v-if="loading" />

    <template v-else>
      <!-- Monthly Tables -->
      <PageCard
        v-for="monthData in displayMonths"
        :key="monthData.month"
        :title="`${monthData.month_name} ${selectedYear}`"
        class="mb-6"
      >
        <template #header-extra>
          <NSpace>
            <NTag type="info">Anggaran: {{ formatCurrency(monthData.totals.budget) }}</NTag>
            <NTag type="warning">Rencana: {{ formatCurrency(monthData.totals.planned) }}</NTag>
            <NTag type="success">Realisasi: {{ formatCurrency(monthData.totals.realized) }}</NTag>
          </NSpace>
        </template>

        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50">
              <tr>
                <th rowspan="2" class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase border">Kode Rekening</th>
                <th rowspan="2" class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase border">Uraian Belanja</th>
                <th colspan="2" class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase border">Rencana</th>
                <th rowspan="2" class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase border">Anggaran</th>
                <th colspan="2" class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase border">Progres Fisik</th>
                <th colspan="2" class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase border">Progres Keuangan</th>
                <th rowspan="2" class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase border">Saldo</th>
              </tr>
              <tr>
                <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase border">Vol</th>
                <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase border">Sat</th>
                <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase border">Target</th>
                <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase border">Real</th>
                <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase border">Real (Rp)</th>
                <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase border">%</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              <tr v-for="item in monthData.items" :key="item.id" class="hover:bg-gray-50">
                <td class="px-3 py-2 text-gray-600 font-mono text-xs border">{{ item.code }}</td>
                <td class="px-3 py-2 text-gray-900 border">{{ item.name }}</td>
                <td class="px-3 py-2 text-center text-gray-600 border">{{ item.volume }}</td>
                <td class="px-3 py-2 text-center text-gray-600 border">{{ item.unit }}</td>
                <td class="px-3 py-2 text-right text-gray-600 border">{{ formatCurrency(item.budget) }}</td>
                <td class="px-3 py-2 text-center text-gray-600 border">{{ item.physical_target?.toFixed(1) ?? 0 }}%</td>
                <td class="px-3 py-2 text-center border" :class="item.physical_progress >= item.physical_target ? 'text-green-600' : 'text-red-600'">
                  {{ item.physical_progress?.toFixed(1) ?? 0 }}%
                </td>
                <td class="px-3 py-2 text-right text-gray-600 border">{{ formatCurrency(item.realized) }}</td>
                <td class="px-3 py-2 text-center border" :class="item.budget > 0 ? (item.realized / item.budget * 100 >= 80 ? 'text-green-600' : 'text-amber-600') : ''">
                  {{ item.budget > 0 ? ((item.realized / item.budget) * 100).toFixed(1) : 0 }}%
                </td>
                <td class="px-3 py-2 text-right text-gray-600 border">{{ formatCurrency(item.balance) }}</td>
              </tr>
            </tbody>
            <tfoot>
              <tr class="bg-gray-100 font-semibold">
                <td colspan="4" class="px-3 py-2 text-center border">TOTAL</td>
                <td class="px-3 py-2 text-right border">{{ formatCurrency(monthData.totals.budget) }}</td>
                <td colspan="2" class="px-3 py-2 border"></td>
                <td class="px-3 py-2 text-right border">{{ formatCurrency(monthData.totals.realized) }}</td>
                <td class="px-3 py-2 text-center border">
                  {{ monthData.totals.budget > 0 ? ((monthData.totals.realized / monthData.totals.budget) * 100).toFixed(1) : 0 }}%
                </td>
                <td class="px-3 py-2 text-right border">{{ formatCurrency(monthData.totals.budget - monthData.totals.realized) }}</td>
              </tr>
            </tfoot>
          </table>
        </div>
      </PageCard>

      <PageCard v-if="displayMonths.length === 0">
        <div class="text-center py-8 text-gray-500">
          Tidak ada data untuk periode yang dipilih
        </div>
      </PageCard>
    </template>
  </div>
</template>
