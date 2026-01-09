import api from './api'

export interface OperationalSchedule {
  id: number
  budget_item_id: number
  activity_name: string
  start_date: string
  end_date: string
  planned_amount: number
  status: 'NOT_STARTED' | 'IN_PROGRESS' | 'COMPLETED' | 'DELAYED'
  progress_percentage: number
  pic_name: string | null
  pic_user_id: number | null
  notes: string | null
  created_at: string
  updated_at: string
  budget_item?: {
    id: number
    name: string
    code: string
    sub_activity?: { name: string }
  }
}

export interface ScheduleFilters {
  year?: number
  month?: number
  status?: string
  budget_item_id?: number
}

export const operationalScheduleService = {
  async getAll(filters: ScheduleFilters = {}) {
    const response = await api.get('/operational-schedules', { params: filters })
    return response.data
  },

  async getById(id: number) {
    const response = await api.get(`/operational-schedules/${id}`)
    return response.data
  },

  async create(data: Partial<OperationalSchedule>) {
    const response = await api.post('/operational-schedules', data)
    return response.data
  },

  async update(id: number, data: Partial<OperationalSchedule>) {
    const response = await api.put(`/operational-schedules/${id}`, data)
    return response.data
  },

  async delete(id: number) {
    const response = await api.delete(`/operational-schedules/${id}`)
    return response.data
  },

  async getCalendar(year: number, month: number) {
    const response = await api.get('/operational-schedules/calendar', {
      params: { year, month },
    })
    return response.data
  },

  async getGantt(year: number, month?: number) {
    const response = await api.get('/operational-schedules/gantt', {
      params: { year, month },
    })
    return response.data
  },

  async generateFromPlgk(year: number, month?: number) {
    const response = await api.post('/operational-schedules/generate-from-plgk', {
      year,
      month,
    })
    return response.data
  },

  async updateStatus(id: number, status: string, progressPercentage?: number) {
    const response = await api.post(`/operational-schedules/${id}/status`, {
      status,
      progress_percentage: progressPercentage,
    })
    return response.data
  },

  async assignPic(id: number, picUserId: number | null, picName?: string) {
    const response = await api.post(`/operational-schedules/${id}/assign-pic`, {
      pic_user_id: picUserId,
      pic_name: picName,
    })
    return response.data
  },
}

export default operationalScheduleService
