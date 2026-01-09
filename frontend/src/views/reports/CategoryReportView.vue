<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { NButton, NSelect, NProgress, NDataTable, NSpace, NIcon } from 'naive-ui'
import type { DataTableColumns } from 'naive-ui'
import { DocumentOutline, DocumentTextOutline } from '@vicons/ionicons5'
import { PageHeader, PageCard, StatCard, LoadingSpinner } from '@/components/ui'
import { useFormat } from '@/composables'
import api from '@/services/api'
import type { CategoryStat } from '@/types/models'

const { formatCurrency } = useFormat()

const loading = ref(true)
const categoryStats = ref<CategoryStat[]>([])
const selectedYear = ref(new Date().getFullYear())
const exporting = ref(false)

const years = computed(() => {
  const currentYear = new Date().getFullYear()
  return Array.from({ length: 5 }, (_, i) => ({
    label: String(currentYear - 2 + i),
    value: currentYear - 2 + i,
  }))
})

const totalBudget = computed(() => categoryStats.value.reduce((sum, c) => sum + c.budget, 0))
const totalRealization = computed(() => categoryStats.value.reduce((sum, c) => sum + c.realization, 0))
const overallPercentage = computed(() => {
  if (totalBudget.value === 0) return 0
  return (totalRealization.value / totalBudget.value) * 100
})

const categoryColors: Record<string, string> = {
  ANALISIS: '#3b82f6',
  TATA_KELOLA: '#22c55e',
  OPERASIONALISASI: '#eab308',
  LAYANAN: '#a855f7',
  ELEK_NON_ELEK: '#f97316',
}

async function fetchReportData() {
  loading.value = true
  try {
    const response = await api.get(`/dashboard/stats?year=${selectedYear.value}`)
    categoryStats.value = response.data.data.categories
  } catch (err) {
    console.error('Failed to fetch report data:', err)
  } finally {
    loading.value = false
  }
}

async function exportPdf() {
  exporting.value = true
  try {
    const response = await api.get(`/reports/export/realisasi/pdf?year=${selectedYear.value}`, {
      responseType: 'blob',
    })
    const url = window.URL.createObjectURL(new Blob([response.data]))
    const link = document.createElement('a')
    link.href = url
    link.setAttribute('download', `laporan-kategori-${selectedYear.value}.pdf`)
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
    const response = await api.get(`/reports/export/realisasi/excel?year=${selectedYear.value}`, {
      responseType: 'blob',
    })
    const url = window.URL.createObjectURL(new Blob([response.data]))
    const link = document.createElement('a')
    link.href = url
    link.setAttribute('download', `laporan-kategori-${selectedYear.value}.xlsx`)
    document.body.appendChild(link)
    link.click()
    link.remove()
  } catch (err) {
    console.error('Failed to export Excel:', err)
  } finally {
    exporting.value = false
  }
}

function getCategoryColor(code: string) {
  return categoryColors[code] || '#6b7280'
}

const columns: DataTableColumns<CategoryStat> = [
  {
    title: 'Kategori',
    key: 'name',
    render: (row) => {
      const color = getCategoryColor(row.code)
      return `<div class="flex items-center"><div class="w-3 h-3 rounded-full mr-3" style="background-color: ${color}"></div><span class="font-medium">${row.name}</span></div>`
    },
  },
  {
    title: 'Anggaran',
    key: 'budget',
    align: 'right',
    render: (row) => formatCurrency(row.budget),
  },
  {
    title: 'Realisasi',
    key: 'realization',
    align: 'right',
    render: (row) => formatCurrency(row.realization),
  },
  {
    title: 'Sisa',
    key: 'remaining',
    align: 'right',
    render: (row) => {
      const remaining = row.budget - row.realization
      const color = remaining >= 0 ? 'text-green-600' : 'text-red-600'
      return `<span class="${color}">${formatCurrency(remaining)}</span>`
    },
  },
  {
    title: 'Persentase',
    key: 'percentage',
    width: 180,
    align: 'right',
    render: (row) => {
      const color = row.percentage >= 80 ? '#22c55e' : row.percentage >= 50 ? '#eab308' : '#ef4444'
      return `<div class="flex items-center justify-end gap-2">
        <div class="w-20 h-2 bg-gray-200 rounded-full overflow-hidden">
          <div class="h-full rounded-full" style="width: ${Math.min(row.percentage, 100)}%; background-color: ${color}"></div>
        </div>
        <span class="font-medium">${row.percentage.toFixed(1)}%</span>
      </div>`
    },
  },
]

onMounted(fetchReportData)
</script>

<template>
  <div>
    <PageHeader title="Laporan per Kategori" subtitle="Realisasi anggaran berdasarkan kategori">
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
      <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <StatCard label="Total Anggaran" :value="formatCurrency(totalBudget)" />
        <StatCard label="Total Realisasi" :value="formatCurrency(totalRealization)" variant="success" />
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

      <!-- Category Cards -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
        <PageCard
          v-for="cat in categoryStats"
          :key="cat.code"
          class="overflow-hidden"
        >
          <div
            class="h-2 -mx-6 -mt-6 mb-4"
            :style="{ backgroundColor: getCategoryColor(cat.code) }"
          ></div>
          <h3 class="text-lg font-semibold text-gray-800 mb-4">{{ cat.name }}</h3>
          <div class="space-y-3">
            <div class="flex justify-between text-sm">
              <span class="text-gray-600">Anggaran</span>
              <span class="font-medium">{{ formatCurrency(cat.budget) }}</span>
            </div>
            <div class="flex justify-between text-sm">
              <span class="text-gray-600">Realisasi</span>
              <span class="font-medium">{{ formatCurrency(cat.realization) }}</span>
            </div>
            <div class="flex justify-between text-sm">
              <span class="text-gray-600">Persentase</span>
              <span
                :class="cat.percentage >= 80 ? 'text-green-600' : cat.percentage >= 50 ? 'text-yellow-600' : 'text-red-600'"
                class="font-bold"
              >
                {{ cat.percentage.toFixed(1) }}%
              </span>
            </div>
            <NProgress
              type="line"
              :percentage="Math.min(cat.percentage, 100)"
              :show-indicator="false"
              :color="getCategoryColor(cat.code)"
            />
          </div>
        </PageCard>
      </div>

      <!-- Detailed Table -->
      <PageCard title="Detail per Kategori">
        <NDataTable
          :columns="columns"
          :data="categoryStats"
          :bordered="false"
          striped
          :summary="() => ({
            name: { value: 'Total', colSpan: 1 },
            budget: { value: formatCurrency(totalBudget) },
            realization: { value: formatCurrency(totalRealization) },
            remaining: { value: formatCurrency(totalBudget - totalRealization) },
            percentage: { value: `${overallPercentage.toFixed(1)}%` },
          })"
        />
      </PageCard>
    </template>
  </div>
</template>
