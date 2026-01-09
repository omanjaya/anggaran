<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { NProgress, NTag } from 'naive-ui'
import { PageHeader, PageCard, StatCard, LoadingSpinner } from '@/components/ui'
import { useAuthStore } from '@/stores/auth'
import { useFormat } from '@/composables'
import api from '@/services/api'
import type { DashboardStats, MonthlyTrend, RecentActivity } from '@/types/models'

const authStore = useAuthStore()
const { formatCurrency, formatNumber } = useFormat()

const loading = ref(true)
const stats = ref<DashboardStats | null>(null)
const monthlyTrend = ref<MonthlyTrend[]>([])
const recentActivities = ref<RecentActivity[]>([])
const currentYear = ref(new Date().getFullYear())

const formatPercentage = (value: number) => `${value.toFixed(1)}%`

const totalPlanned = computed(() => monthlyTrend.value.reduce((sum, m) => sum + m.planned, 0))
const totalRealized = computed(() => monthlyTrend.value.reduce((sum, m) => sum + m.realized, 0))

async function fetchDashboardData() {
  loading.value = true
  try {
    const [statsRes, trendRes, activitiesRes] = await Promise.all([
      api.get(`/dashboard/stats?year=${currentYear.value}`),
      api.get(`/dashboard/monthly-trend?year=${currentYear.value}`),
      api.get('/dashboard/recent-activities'),
    ])
    stats.value = statsRes.data.data
    monthlyTrend.value = trendRes.data.data
    recentActivities.value = activitiesRes.data.data
  } catch (error) {
    console.error('Failed to fetch dashboard data:', error)
  } finally {
    loading.value = false
  }
}

const statusTypes: Record<string, 'default' | 'warning' | 'info' | 'success' | 'error'> = {
  DRAFT: 'default',
  SUBMITTED: 'warning',
  VERIFIED: 'info',
  APPROVED: 'success',
  REJECTED: 'error',
}

onMounted(fetchDashboardData)
</script>

<template>
  <div>
    <PageHeader
      title="Dashboard"
      :subtitle="`Selamat datang, ${authStore.user?.name} - Tahun Anggaran ${currentYear}`"
    />

    <LoadingSpinner v-if="loading" />

    <template v-else-if="stats">
      <!-- Summary Cards -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <StatCard
          label="Total Anggaran"
          :value="formatCurrency(stats.total_budget)"
          icon="wallet"
          variant="primary"
        />
        <StatCard
          label="Total Realisasi"
          :value="formatCurrency(stats.total_realization)"
          icon="check"
          variant="success"
        />
        <PageCard>
          <div class="flex items-center justify-between mb-2">
            <p class="text-sm font-medium text-gray-600">Persentase Realisasi</p>
          </div>
          <p class="text-xl font-bold text-gray-900 mb-2">{{ formatPercentage(stats.realization_percentage) }}</p>
          <NProgress
            type="line"
            :percentage="Math.min(stats.realization_percentage, 100)"
            :show-indicator="false"
            color="#8b5cf6"
          />
        </PageCard>
        <StatCard
          label="Menunggu Persetujuan"
          :value="stats.pending_approvals"
          icon="clock"
          variant="warning"
        />
      </div>

      <!-- Category Stats & Monthly Trend -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <!-- Category Stats -->
        <PageCard title="Realisasi per Kategori">
          <div class="space-y-4">
            <div v-for="cat in stats.categories" :key="cat.code" class="space-y-2">
              <div class="flex justify-between text-sm">
                <span class="text-gray-600">{{ cat.name }}</span>
                <span class="font-medium">{{ formatPercentage(cat.percentage) }}</span>
              </div>
              <NProgress
                type="line"
                :percentage="Math.min(cat.percentage, 100)"
                :show-indicator="false"
                color="#3b82f6"
              />
              <div class="flex justify-between text-xs text-gray-500">
                <span>Realisasi: {{ formatCurrency(cat.realization) }}</span>
                <span>Anggaran: {{ formatCurrency(cat.budget) }}</span>
              </div>
            </div>
          </div>
        </PageCard>

        <!-- Monthly Trend -->
        <PageCard title="Trend Bulanan">
          <div class="overflow-x-auto max-h-80">
            <table class="min-w-full text-sm">
              <thead class="sticky top-0 bg-white">
                <tr class="border-b">
                  <th class="text-left py-2 text-gray-600">Bulan</th>
                  <th class="text-right py-2 text-gray-600">Rencana</th>
                  <th class="text-right py-2 text-gray-600">Realisasi</th>
                  <th class="text-right py-2 text-gray-600">Deviasi</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="trend in monthlyTrend" :key="trend.month" class="border-b">
                  <td class="py-2">{{ trend.month_name }}</td>
                  <td class="text-right">{{ formatNumber(trend.planned) }}</td>
                  <td class="text-right">{{ formatNumber(trend.realized) }}</td>
                  <td class="text-right" :class="trend.deviation < 0 ? 'text-red-600' : 'text-green-600'">
                    {{ formatNumber(trend.deviation) }}
                  </td>
                </tr>
              </tbody>
              <tfoot>
                <tr class="font-semibold bg-gray-50">
                  <td class="py-2">Total</td>
                  <td class="text-right">{{ formatNumber(totalPlanned) }}</td>
                  <td class="text-right">{{ formatNumber(totalRealized) }}</td>
                  <td class="text-right" :class="totalRealized - totalPlanned < 0 ? 'text-red-600' : 'text-green-600'">
                    {{ formatNumber(totalRealized - totalPlanned) }}
                  </td>
                </tr>
              </tfoot>
            </table>
          </div>
        </PageCard>
      </div>

      <!-- Recent Activities -->
      <PageCard title="Aktivitas Terbaru">
        <div v-if="recentActivities.length === 0" class="text-center py-8 text-gray-500">
          Belum ada aktivitas
        </div>
        <div v-else class="overflow-x-auto">
          <table class="min-w-full text-sm">
            <thead>
              <tr class="border-b">
                <th class="text-left py-3 text-gray-600">Program</th>
                <th class="text-left py-3 text-gray-600">Item</th>
                <th class="text-center py-3 text-gray-600">Periode</th>
                <th class="text-right py-3 text-gray-600">Jumlah</th>
                <th class="text-center py-3 text-gray-600">Status</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="activity in recentActivities" :key="activity.id" class="border-b hover:bg-gray-50">
                <td class="py-3 max-w-xs truncate">{{ activity.program }}</td>
                <td class="py-3 max-w-xs truncate">{{ activity.budget_item }}</td>
                <td class="text-center py-3">{{ activity.month }}/{{ activity.year }}</td>
                <td class="text-right py-3">{{ formatCurrency(activity.amount) }}</td>
                <td class="text-center py-3">
                  <NTag :type="statusTypes[activity.status]" size="small">
                    {{ activity.status_label }}
                  </NTag>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </PageCard>
    </template>

    <div v-else class="text-center text-gray-500 py-12">
      Gagal memuat data dashboard
    </div>
  </div>
</template>
