<script setup lang="ts">
import { ref, onMounted, watch } from 'vue'
import api from '@/services/api'

interface ReportData {
  month: number
  year: number
  skpd: {
    code: string
    name: string
  }
  summary: {
    total_budget: number
    total_planned: number
    total_realized: number
    absorption_rate: number
  }
  items: Array<{
    category: string
    sub_activity: string
    account_code: string
    description: string
    budget: number
    planned: number
    realized: number
    deviation: number
    deviation_percentage: number
  }>
  generated_at: string
}

const props = defineProps<{
  month?: number
  year?: number
}>()

const emit = defineEmits(['close'])

const loading = ref(false)
const generating = ref(false)
const reportData = ref<ReportData | null>(null)
const selectedMonth = ref(props.month || new Date().getMonth() + 1)
const selectedYear = ref(props.year || new Date().getFullYear())
const pdfUrl = ref<string | null>(null)

const monthNames = [
  'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
  'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
]

// Format currency
const formatCurrency = (value: number) => {
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
  }).format(value)
}

// Format percentage
const formatPercentage = (value: number) => {
  return value.toFixed(1) + '%'
}

// Fetch report data
const fetchReport = async () => {
  loading.value = true
  try {
    const response = await api.get('/reports/monthly', {
      params: {
        month: selectedMonth.value,
        year: selectedYear.value,
      }
    })
    reportData.value = response.data.data
  } catch (error) {
    console.error('Failed to fetch report:', error)
  } finally {
    loading.value = false
  }
}

// Generate PDF
const generatePdf = async () => {
  generating.value = true
  try {
    const response = await api.get('/reports/export/monthly/pdf', {
      params: {
        month: selectedMonth.value,
        year: selectedYear.value,
      },
      responseType: 'blob',
    })
    
    const blob = new Blob([response.data], { type: 'application/pdf' })
    pdfUrl.value = URL.createObjectURL(blob)
  } catch (error) {
    console.error('Failed to generate PDF:', error)
    alert('Gagal generate PDF')
  } finally {
    generating.value = false
  }
}

// Download PDF
const downloadPdf = async () => {
  generating.value = true
  try {
    const response = await api.get('/reports/export/monthly/pdf', {
      params: {
        month: selectedMonth.value,
        year: selectedYear.value,
      },
      responseType: 'blob',
    })
    
    const blob = new Blob([response.data], { type: 'application/pdf' })
    const url = URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = `Laporan_Bulanan_${monthNames[selectedMonth.value - 1]}_${selectedYear.value}.pdf`
    link.click()
    URL.revokeObjectURL(url)
  } catch (error) {
    console.error('Failed to download PDF:', error)
    alert('Gagal download PDF')
  } finally {
    generating.value = false
  }
}

// Download Excel
const downloadExcel = async () => {
  generating.value = true
  try {
    const response = await api.get('/reports/export/monthly/excel', {
      params: {
        month: selectedMonth.value,
        year: selectedYear.value,
      },
      responseType: 'blob',
    })
    
    const blob = new Blob([response.data], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' })
    const url = URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = `Laporan_Bulanan_${monthNames[selectedMonth.value - 1]}_${selectedYear.value}.xlsx`
    link.click()
    URL.revokeObjectURL(url)
  } catch (error) {
    console.error('Failed to download Excel:', error)
    alert('Gagal download Excel')
  } finally {
    generating.value = false
  }
}

// Watch for filter changes
watch([selectedMonth, selectedYear], () => {
  pdfUrl.value = null
  fetchReport()
})

onMounted(() => {
  fetchReport()
})
</script>

<template>
  <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-6xl max-h-[95vh] overflow-hidden flex flex-col">
      <!-- Header -->
      <div class="px-6 py-4 border-b flex items-center justify-between">
        <h3 class="text-lg font-semibold">Preview Laporan Bulanan</h3>
        <button @click="emit('close')" class="text-gray-400 hover:text-gray-600">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
          </svg>
        </button>
      </div>

      <!-- Filters -->
      <div class="px-6 py-3 border-b bg-gray-50 flex items-center gap-4">
        <div class="flex items-center gap-2">
          <label class="text-sm font-medium text-gray-700">Bulan:</label>
          <select
            v-model="selectedMonth"
            class="px-3 py-1 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500"
          >
            <option v-for="(month, idx) in monthNames" :key="idx" :value="idx + 1">
              {{ month }}
            </option>
          </select>
        </div>
        <div class="flex items-center gap-2">
          <label class="text-sm font-medium text-gray-700">Tahun:</label>
          <select
            v-model="selectedYear"
            class="px-3 py-1 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500"
          >
            <option v-for="y in [2024, 2025, 2026, 2027]" :key="y" :value="y">
              {{ y }}
            </option>
          </select>
        </div>
        <div class="flex-1"></div>
        <button
          @click="generatePdf"
          :disabled="generating || loading"
          class="px-3 py-1 bg-blue-600 text-white rounded hover:bg-blue-700 disabled:opacity-50 flex items-center gap-2"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
          </svg>
          Preview PDF
        </button>
        <button
          @click="downloadPdf"
          :disabled="generating || loading"
          class="px-3 py-1 border border-red-600 text-red-600 rounded hover:bg-red-50 disabled:opacity-50 flex items-center gap-2"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
          </svg>
          PDF
        </button>
        <button
          @click="downloadExcel"
          :disabled="generating || loading"
          class="px-3 py-1 border border-green-600 text-green-600 rounded hover:bg-green-50 disabled:opacity-50 flex items-center gap-2"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
          </svg>
          Excel
        </button>
      </div>

      <!-- Content -->
      <div class="flex-1 overflow-auto">
        <!-- PDF Preview -->
        <div v-if="pdfUrl" class="h-full">
          <iframe :src="pdfUrl" class="w-full h-full" frameborder="0"></iframe>
        </div>

        <!-- Data Preview -->
        <div v-else-if="!loading && reportData" class="p-6">
          <!-- Report Header -->
          <div class="text-center mb-6 pb-4 border-b-2 border-gray-200">
            <h2 class="text-xl font-bold text-gray-800">LAPORAN REALISASI ANGGARAN BULANAN</h2>
            <h3 class="text-lg font-semibold text-gray-700">{{ reportData.skpd.name }}</h3>
            <p class="text-gray-600">
              Bulan {{ monthNames[reportData.month - 1] }} Tahun {{ reportData.year }}
            </p>
          </div>

          <!-- Summary Cards -->
          <div class="grid grid-cols-4 gap-4 mb-6">
            <div class="bg-blue-50 rounded-lg p-4 text-center">
              <div class="text-sm text-blue-600">Pagu Anggaran</div>
              <div class="text-lg font-bold text-blue-800">
                {{ formatCurrency(reportData.summary.total_budget) }}
              </div>
            </div>
            <div class="bg-yellow-50 rounded-lg p-4 text-center">
              <div class="text-sm text-yellow-600">Rencana Bulan Ini</div>
              <div class="text-lg font-bold text-yellow-800">
                {{ formatCurrency(reportData.summary.total_planned) }}
              </div>
            </div>
            <div class="bg-green-50 rounded-lg p-4 text-center">
              <div class="text-sm text-green-600">Realisasi</div>
              <div class="text-lg font-bold text-green-800">
                {{ formatCurrency(reportData.summary.total_realized) }}
              </div>
            </div>
            <div class="bg-purple-50 rounded-lg p-4 text-center">
              <div class="text-sm text-purple-600">Tingkat Serapan</div>
              <div class="text-lg font-bold text-purple-800">
                {{ formatPercentage(reportData.summary.absorption_rate) }}
              </div>
            </div>
          </div>

          <!-- Detail Table -->
          <div class="border rounded-lg overflow-hidden">
            <table class="w-full text-sm">
              <thead class="bg-gray-100">
                <tr>
                  <th class="px-3 py-2 text-left font-medium text-gray-700">Kategori</th>
                  <th class="px-3 py-2 text-left font-medium text-gray-700">Kode</th>
                  <th class="px-3 py-2 text-left font-medium text-gray-700">Uraian</th>
                  <th class="px-3 py-2 text-right font-medium text-gray-700">Pagu</th>
                  <th class="px-3 py-2 text-right font-medium text-gray-700">Rencana</th>
                  <th class="px-3 py-2 text-right font-medium text-gray-700">Realisasi</th>
                  <th class="px-3 py-2 text-right font-medium text-gray-700">Deviasi</th>
                  <th class="px-3 py-2 text-center font-medium text-gray-700">%</th>
                </tr>
              </thead>
              <tbody class="divide-y">
                <tr v-for="(item, idx) in reportData.items" :key="idx" class="hover:bg-gray-50">
                  <td class="px-3 py-2">
                    <span class="px-2 py-1 bg-blue-100 text-blue-800 rounded text-xs">
                      {{ item.category }}
                    </span>
                  </td>
                  <td class="px-3 py-2 font-mono text-xs">{{ item.account_code }}</td>
                  <td class="px-3 py-2">{{ item.description }}</td>
                  <td class="px-3 py-2 text-right">{{ formatCurrency(item.budget) }}</td>
                  <td class="px-3 py-2 text-right">{{ formatCurrency(item.planned) }}</td>
                  <td class="px-3 py-2 text-right">{{ formatCurrency(item.realized) }}</td>
                  <td class="px-3 py-2 text-right" :class="item.deviation < 0 ? 'text-red-600' : 'text-green-600'">
                    {{ formatCurrency(item.deviation) }}
                  </td>
                  <td class="px-3 py-2 text-center" :class="Math.abs(item.deviation_percentage) > 10 ? 'text-red-600 font-medium' : ''">
                    {{ formatPercentage(item.deviation_percentage) }}
                  </td>
                </tr>
                <tr v-if="!reportData.items?.length">
                  <td colspan="8" class="px-3 py-8 text-center text-gray-500">
                    Tidak ada data untuk bulan ini
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Footer -->
          <div class="mt-6 text-sm text-gray-500 text-right">
            Digenerate pada: {{ new Date(reportData.generated_at).toLocaleString('id-ID') }}
          </div>
        </div>

        <!-- Loading -->
        <div v-else-if="loading" class="flex items-center justify-center h-96">
          <div class="text-center">
            <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto mb-4"></div>
            <p class="text-gray-600">Memuat data...</p>
          </div>
        </div>
      </div>

      <!-- Footer -->
      <div class="px-6 py-4 border-t flex justify-end">
        <button
          @click="emit('close')"
          class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200"
        >
          Tutup
        </button>
      </div>
    </div>
  </div>
</template>
