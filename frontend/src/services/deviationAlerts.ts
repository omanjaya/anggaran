import api from './api'

export interface DeviationAlert {
  id: number
  budget_item_id: number
  monthly_realization_id: number | null
  month: number
  year: number
  alert_type: 'UNDER_REALIZATION' | 'OVER_REALIZATION' | 'NOT_REALIZED' | 'DEADLINE_APPROACHING'
  severity: 'CRITICAL' | 'HIGH' | 'MEDIUM' | 'LOW'
  severity_label: string
  planned_amount: number
  realized_amount: number
  deviation_percentage: number
  message: string
  status: 'ACTIVE' | 'ACKNOWLEDGED' | 'RESOLVED' | 'DISMISSED'
  acknowledged_by: number | null
  acknowledged_at: string | null
  acknowledged_notes: string | null
  resolved_by: number | null
  resolved_at: string | null
  resolution_notes: string | null
  created_at: string
  updated_at: string
  budget_item?: {
    id: number
    name: string
    code: string
    sub_activity?: { name: string }
  }
}

export interface AlertFilters {
  year?: number
  month?: number
  status?: string
  severity?: string
  alert_type?: string
}

export interface DashboardStats {
  total_active: number
  critical_count: number
  high_count: number
  medium_count: number
  low_count: number
  by_type: Record<string, number>
  by_month: Array<{ month: number; count: number }>
}

export const deviationAlertsService = {
  async getAll(filters: AlertFilters = {}) {
    const response = await api.get('/deviation-alerts', { params: filters })
    return response.data
  },

  async getById(id: number) {
    const response = await api.get(`/deviation-alerts/${id}`)
    return response.data
  },

  async getDashboard() {
    const response = await api.get('/deviation-alerts/dashboard')
    return response.data
  },

  async check(year?: number, month?: number) {
    const response = await api.post('/deviation-alerts/check', { year, month })
    return response.data
  },

  async acknowledge(id: number, notes?: string) {
    const response = await api.post(`/deviation-alerts/${id}/acknowledge`, { notes })
    return response.data
  },

  async resolve(id: number, resolutionNotes: string) {
    const response = await api.post(`/deviation-alerts/${id}/resolve`, {
      resolution_notes: resolutionNotes,
    })
    return response.data
  },

  async dismiss(id: number) {
    const response = await api.post(`/deviation-alerts/${id}/dismiss`)
    return response.data
  },

  async bulkAcknowledge(ids: number[], notes?: string) {
    const response = await api.post('/deviation-alerts/bulk-acknowledge', { ids, notes })
    return response.data
  },
}

export default deviationAlertsService
