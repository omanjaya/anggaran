<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { NButton, NSelect, NProgress, NDataTable, NSpace, NIcon } from 'naive-ui'
import type { DataTableColumns } from 'naive-ui'
import { DocumentOutline, DocumentTextOutline } from '@vicons/ionicons5'
import { PageHeader, PageCard, StatCard, LoadingSpinner } from '@/components/ui'
import { useFormat } from '@/composables'
import api from '@/services/api'
import type { ProgramStat, MonthlyTrend } from '@/types/models'

const { formatCurrency } = useFormat()

const loading = ref(true)
const programStats = ref<ProgramStat[]>([])
const monthlyTrend = ref<MonthlyTrend[]>([])
const selectedYear = ref(new Date().getFullYear())
const exporting = ref(false)

const years = computed(() => {
  const currentYear = new Date().getFullYear()
  return Array.from({ length: 5 }, (_, i) => ({
    label: String(currentYear - 2 + i),
    value: currentYear - 2 + i,
  }))
})

const totalBudget = computed(() => programStats.value.reduce((sum, p) => sum + p.budget, 0))
const totalPlanned = computed(() => programStats.value.reduce((sum, p) => sum + p.planned, 0))
const totalRealized = computed(() => programStats.value.reduce((sum, p) => sum + p.realized, 0))
const overallPercentage = computed(() => {
  if (totalBudget.value === 0) return 0
  return (totalRealized.value / totalBudget.value) * 100
})

async function fetchReportData() {
  loading.value = true
  try {
    const [statsRes, trendRes] = await Promise.all([
      api.get(`/dashboard/program-stats?year=${selectedYear.value}`),
      api.get(`/dashboard/monthly-trend?year=${selectedYear.value}`),
    ])
    programStats.value = statsRes.data.data
    monthlyTrend.value = trendRes.data.data
  } catch (err) {
    console.error('Failed to fetch report data:', err)
  } finally {
    loading.value = false
  }
}

async function exportPdf() {
  exporting.value = true
  try {
    const response = await api.get(`/reports/export/yearly/pdf?year=${selectedYear.value}`, {
      responseType: 'blob',
    })
    const url = window.URL.createObjectURL(new Blob([response.data]))
    const link = document.createElement('a')
    link.href = url
    link.setAttribute('download', `laporan-tahunan-${selectedYear.value}.pdf`)
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
    const response = await api.get(`/reports/export/yearly/excel?year=${selectedYear.value}`, {
      responseType: 'blob',
    })
    const url = window.URL.createObjectURL(new Blob([response.data]))
    const link = document.createElement('a')
    link.href = url
    link.setAttribute('download', `laporan-tahunan-${selectedYear.value}.xlsx`)
    document.body.appendChild(link)
    link.click()
    link.remove()
  } catch (err) {
    console.error('Failed to export Excel:', err)
  } finally {
    exporting.value = false
  }
}

const programColumns: DataTableColumns<ProgramStat> = [
  { title: 'Kode', key: 'code', width: 100 },
  { title: 'Nama Program', key: 'name' },
  {
    title: 'Anggaran',
    key: 'budget',
    width: 150,
    align: 'right',
    render: (row) => formatCurrency(row.budget),
  },
  {
    title: 'Rencana',
    key: 'planned',
    width: 150,
    align: 'right',
    render: (row) => formatCurrency(row.planned),
  },
  {
    title: 'Realisasi',
    key: 'realized',
    width: 150,
    align: 'right',
    render: (row) => formatCurrency(row.realized),
  },
  {
    title: '%',
    key: 'percentage',
    width: 80,
    align: 'right',
    render: (row) => {
      const color = row.percentage >= 80 ? 'text-green-600' : row.percentage >= 50 ? 'text-yellow-600' : 'text-red-600'
      return `<span class="${color}">${row.percentage.toFixed(1)}%</span>`
    },
  },
]

onMounted(fetchReportData)
</script>

<template>
  <div>
    <PageHeader title="Laporan Ringkasan" subtitle="Ringkasan realisasi anggaran tahunan">
      <template #actions>
        <NSpace>
          <NSelect
            v-model:value="selectedYear"
            :options="years"
            style="width: 120px"
            @update:value="fetchReportData"
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

    <LoadingSpinner v-if="loading" />

    <template v-else>
      <!-- Summary Cards -->
      <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
        <StatCard label="Total Anggaran" :value="formatCurrency(totalBudget)" />
        <StatCard label="Total Rencana" :value="formatCurrency(totalPlanned)" />
        <StatCard label="Total Realisasi" :value="formatCurrency(totalRealized)" variant="success" />
        <PageCard>
          <p class="text-sm font-medium text-gray-600 mb-1">Persentase Realisasi</p>
          <p class="text-xl font-bold text-gray-900 mb-2">{{ overallPercentage.toFixed(1) }}%</p>
          <NProgress
            type="line"
            :percentage="Math.min(overallPercentage, 100)"
            :show-indicator="false"
            color="#3b82f6"
          />
        </PageCard>
      </div>

      <!-- Program Stats -->
      <PageCard title="Realisasi per Program" class="mb-8">
        <NDataTable
          :columns="programColumns"
          :data="programStats"
          :bordered="false"
          striped
          :summary="() => ({
            code: { value: 'Total', colSpan: 2 },
            budget: { value: formatCurrency(totalBudget) },
            planned: { value: formatCurrency(totalPlanned) },
            realized: { value: formatCurrency(totalRealized) },
            percentage: { value: `${overallPercentage.toFixed(1)}%` },
          })"
        />
      </PageCard>

      <!-- Monthly Trend -->
      <PageCard title="Trend Bulanan">
        <div class="overflow-x-auto">
          <table class="min-w-full text-sm">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Bulan</th>
                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Rencana</th>
                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Realisasi</th>
                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Deviasi</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
              <tr v-for="trend in monthlyTrend" :key="trend.month" class="hover:bg-gray-50">
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                  {{ trend.month_name }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 text-right">
                  {{ formatCurrency(trend.planned) }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 text-right">
                  {{ formatCurrency(trend.realized) }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-right"
                    :class="trend.deviation < 0 ? 'text-red-600' : 'text-green-600'">
                  {{ formatCurrency(trend.deviation) }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </PageCard>
    </template>
  </div>
</template>
