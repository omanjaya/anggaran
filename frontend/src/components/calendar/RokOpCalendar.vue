<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import api from '@/services/api'

interface ScheduleEvent {
  id: number
  title: string
  start_date: string
  end_date: string
  status: 'PLANNED' | 'IN_PROGRESS' | 'COMPLETED' | 'DELAYED'
  category: string
  budget_item: {
    account_code: string
    description: string
  }
  sub_activity: {
    category: string
    name: string
  }
  pic_name: string | null
  planned_amount: number
}

interface CalendarDay {
  date: Date
  dayOfMonth: number
  isCurrentMonth: boolean
  isToday: boolean
  events: ScheduleEvent[]
}

const loading = ref(false)
const events = ref<ScheduleEvent[]>([])
const selectedDate = ref(new Date())
const selectedEvent = ref<ScheduleEvent | null>(null)
const showEventModal = ref(false)

// Current month/year display
const currentMonthYear = computed(() => {
  return new Intl.DateTimeFormat('id-ID', { 
    month: 'long', 
    year: 'numeric' 
  }).format(selectedDate.value)
})

// Calendar days
const calendarDays = computed<CalendarDay[]>(() => {
  const year = selectedDate.value.getFullYear()
  const month = selectedDate.value.getMonth()
  
  // First day of month
  const firstDay = new Date(year, month, 1)
  // Last day of month
  const lastDay = new Date(year, month + 1, 0)
  
  // Start from the previous Sunday
  const startDate = new Date(firstDay)
  startDate.setDate(startDate.getDate() - startDate.getDay())
  
  // End on the next Saturday
  const endDate = new Date(lastDay)
  endDate.setDate(endDate.getDate() + (6 - endDate.getDay()))
  
  const days: CalendarDay[] = []
  const current = new Date(startDate)
  const today = new Date()
  today.setHours(0, 0, 0, 0)
  
  while (current <= endDate) {
    const dayEvents = events.value.filter(e => {
      const start = new Date(e.start_date)
      const end = new Date(e.end_date)
      return current >= start && current <= end
    })
    
    days.push({
      date: new Date(current),
      dayOfMonth: current.getDate(),
      isCurrentMonth: current.getMonth() === month,
      isToday: current.getTime() === today.getTime(),
      events: dayEvents,
    })
    
    current.setDate(current.getDate() + 1)
  }
  
  return days
})

// Week days header
const weekDays = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab']

// Status colors
const statusColors: Record<string, string> = {
  PLANNED: 'bg-blue-100 text-blue-800 border-blue-300',
  IN_PROGRESS: 'bg-yellow-100 text-yellow-800 border-yellow-300',
  COMPLETED: 'bg-green-100 text-green-800 border-green-300',
  DELAYED: 'bg-red-100 text-red-800 border-red-300',
}

const statusLabels: Record<string, string> = {
  PLANNED: 'Direncanakan',
  IN_PROGRESS: 'Berjalan',
  COMPLETED: 'Selesai',
  DELAYED: 'Terlambat',
}

// Format currency
const formatCurrency = (value: number) => {
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
  }).format(value)
}

// Format date
const formatDate = (dateStr: string) => {
  return new Date(dateStr).toLocaleDateString('id-ID', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
  })
}

// Fetch calendar data
const fetchCalendarData = async () => {
  loading.value = true
  try {
    const year = selectedDate.value.getFullYear()
    const month = selectedDate.value.getMonth() + 1
    
    const response = await api.get('/operational-schedules/calendar', {
      params: { year, month }
    })
    events.value = response.data.data
  } catch (error) {
    console.error('Failed to fetch calendar data:', error)
  } finally {
    loading.value = false
  }
}

// Navigation
const prevMonth = () => {
  selectedDate.value = new Date(
    selectedDate.value.getFullYear(),
    selectedDate.value.getMonth() - 1,
    1
  )
  fetchCalendarData()
}

const nextMonth = () => {
  selectedDate.value = new Date(
    selectedDate.value.getFullYear(),
    selectedDate.value.getMonth() + 1,
    1
  )
  fetchCalendarData()
}

const goToToday = () => {
  selectedDate.value = new Date()
  fetchCalendarData()
}

// Open event detail
const openEventDetail = (event: ScheduleEvent) => {
  selectedEvent.value = event
  showEventModal.value = true
}

// Update status
const updateStatus = async (status: string) => {
  if (!selectedEvent.value) return
  
  try {
    await api.post(`/operational-schedules/${selectedEvent.value.id}/status`, {
      status
    })
    showEventModal.value = false
    await fetchCalendarData()
  } catch (error) {
    console.error('Failed to update status:', error)
    alert('Gagal update status')
  }
}

// Statistics
const stats = computed(() => {
  const total = events.value.length
  const completed = events.value.filter(e => e.status === 'COMPLETED').length
  const inProgress = events.value.filter(e => e.status === 'IN_PROGRESS').length
  const delayed = events.value.filter(e => e.status === 'DELAYED').length
  const planned = events.value.filter(e => e.status === 'PLANNED').length
  
  return { total, completed, inProgress, delayed, planned }
})

onMounted(() => {
  fetchCalendarData()
})
</script>

<template>
  <div class="h-full flex flex-col">
    <!-- Header -->
    <div class="flex items-center justify-between mb-4">
      <div class="flex items-center gap-4">
        <button
          @click="prevMonth"
          class="p-2 hover:bg-gray-100 rounded"
        >
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
          </svg>
        </button>
        <h2 class="text-xl font-semibold text-gray-800 w-48 text-center">
          {{ currentMonthYear }}
        </h2>
        <button
          @click="nextMonth"
          class="p-2 hover:bg-gray-100 rounded"
        >
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
          </svg>
        </button>
        <button
          @click="goToToday"
          class="px-3 py-1 text-sm border border-blue-600 text-blue-600 rounded hover:bg-blue-50"
        >
          Hari Ini
        </button>
      </div>

      <!-- Stats -->
      <div class="flex items-center gap-4 text-sm">
        <div class="flex items-center gap-1">
          <span class="w-3 h-3 rounded bg-blue-200"></span>
          <span>{{ stats.planned }} Direncanakan</span>
        </div>
        <div class="flex items-center gap-1">
          <span class="w-3 h-3 rounded bg-yellow-200"></span>
          <span>{{ stats.inProgress }} Berjalan</span>
        </div>
        <div class="flex items-center gap-1">
          <span class="w-3 h-3 rounded bg-green-200"></span>
          <span>{{ stats.completed }} Selesai</span>
        </div>
        <div class="flex items-center gap-1">
          <span class="w-3 h-3 rounded bg-red-200"></span>
          <span>{{ stats.delayed }} Terlambat</span>
        </div>
      </div>
    </div>

    <!-- Calendar Grid -->
    <div class="flex-1 bg-white rounded-lg shadow-sm overflow-hidden">
      <!-- Week Headers -->
      <div class="grid grid-cols-7 border-b">
        <div 
          v-for="day in weekDays" 
          :key="day"
          class="px-2 py-3 text-center text-sm font-medium text-gray-700 bg-gray-50"
        >
          {{ day }}
        </div>
      </div>

      <!-- Calendar Days -->
      <div class="grid grid-cols-7 grid-rows-6" style="min-height: 500px;">
        <div
          v-for="(day, idx) in calendarDays"
          :key="idx"
          :class="[
            'border-r border-b p-1 min-h-[85px]',
            day.isCurrentMonth ? 'bg-white' : 'bg-gray-50',
            day.isToday ? 'bg-blue-50' : '',
          ]"
        >
          <div 
            :class="[
              'text-sm font-medium mb-1 w-7 h-7 flex items-center justify-center rounded-full',
              day.isToday ? 'bg-blue-600 text-white' : 
              day.isCurrentMonth ? 'text-gray-900' : 'text-gray-400'
            ]"
          >
            {{ day.dayOfMonth }}
          </div>
          
          <!-- Events -->
          <div class="space-y-1 max-h-[60px] overflow-y-auto">
            <div
              v-for="event in day.events.slice(0, 3)"
              :key="event.id"
              @click="openEventDetail(event)"
              :class="[
                'text-xs px-1 py-0.5 rounded truncate cursor-pointer border',
                statusColors[event.status]
              ]"
              :title="event.title"
            >
              {{ event.title }}
            </div>
            <div 
              v-if="day.events.length > 3"
              class="text-xs text-gray-500 text-center"
            >
              +{{ day.events.length - 3 }} lainnya
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Loading Overlay -->
    <div v-if="loading" class="absolute inset-0 bg-white bg-opacity-75 flex items-center justify-center">
      <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
    </div>

    <!-- Event Detail Modal -->
    <div v-if="showEventModal && selectedEvent" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div class="bg-white rounded-lg shadow-xl w-full max-w-lg mx-4">
        <div class="px-6 py-4 border-b flex items-center justify-between">
          <h3 class="text-lg font-semibold">Detail Jadwal Operasional</h3>
          <button @click="showEventModal = false" class="text-gray-400 hover:text-gray-600">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
          </button>
        </div>
        <div class="p-6 space-y-4">
          <div>
            <div class="text-sm text-gray-500">Judul</div>
            <div class="font-medium">{{ selectedEvent.title }}</div>
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <div class="text-sm text-gray-500">Mulai</div>
              <div>{{ formatDate(selectedEvent.start_date) }}</div>
            </div>
            <div>
              <div class="text-sm text-gray-500">Selesai</div>
              <div>{{ formatDate(selectedEvent.end_date) }}</div>
            </div>
          </div>
          <div>
            <div class="text-sm text-gray-500">Status</div>
            <span :class="['px-2 py-1 rounded text-sm', statusColors[selectedEvent.status]]">
              {{ statusLabels[selectedEvent.status] }}
            </span>
          </div>
          <div>
            <div class="text-sm text-gray-500">Kategori</div>
            <div>{{ selectedEvent.sub_activity?.category }}</div>
          </div>
          <div>
            <div class="text-sm text-gray-500">Item Belanja</div>
            <div class="font-mono text-sm">{{ selectedEvent.budget_item?.account_code }}</div>
            <div>{{ selectedEvent.budget_item?.description }}</div>
          </div>
          <div>
            <div class="text-sm text-gray-500">Nilai</div>
            <div class="font-medium text-blue-600">{{ formatCurrency(selectedEvent.planned_amount) }}</div>
          </div>
          <div v-if="selectedEvent.pic_name">
            <div class="text-sm text-gray-500">PIC</div>
            <div>{{ selectedEvent.pic_name }}</div>
          </div>

          <!-- Update Status -->
          <div class="border-t pt-4">
            <div class="text-sm font-medium text-gray-700 mb-2">Update Status:</div>
            <div class="flex gap-2">
              <button
                v-if="selectedEvent.status !== 'IN_PROGRESS'"
                @click="updateStatus('IN_PROGRESS')"
                class="px-3 py-1 bg-yellow-100 text-yellow-800 rounded text-sm hover:bg-yellow-200"
              >
                Mulai
              </button>
              <button
                v-if="selectedEvent.status !== 'COMPLETED'"
                @click="updateStatus('COMPLETED')"
                class="px-3 py-1 bg-green-100 text-green-800 rounded text-sm hover:bg-green-200"
              >
                Selesai
              </button>
              <button
                v-if="selectedEvent.status !== 'DELAYED'"
                @click="updateStatus('DELAYED')"
                class="px-3 py-1 bg-red-100 text-red-800 rounded text-sm hover:bg-red-200"
              >
                Terlambat
              </button>
            </div>
          </div>
        </div>
        <div class="px-6 py-4 border-t flex justify-end">
          <button
            @click="showEventModal = false"
            class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200"
          >
            Tutup
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
