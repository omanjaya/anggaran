import api from './api'

export interface ReportTemplate {
  id: number
  user_id: number
  name: string
  description: string | null
  report_type: string
  config: Record<string, any>
  is_public: boolean
  created_at: string
  updated_at: string
}

export interface ReportConfig {
  columns: string[]
  filters: Record<string, any>
  groupBy: string
  sortBy: string
  showTotals: boolean
}

export interface GenerateParams {
  template_id?: number
  report_type?: string
  year: number
  month?: number
  program_id?: number
  activity_id?: number
  config?: ReportConfig
}

export interface ReportData {
  title: string
  subtitle: string
  columns: Array<{
    key: string
    label: string
    type: 'text' | 'currency' | 'percentage'
  }>
  data: Array<Record<string, any>>
  totals?: Array<{
    type: 'text' | 'currency' | 'percentage'
    value: number | string
  }>
  summary?: Array<{
    label: string
    value: number | string
    type: 'text' | 'currency' | 'percentage'
  }>
}

export const customReportsService = {
  async getTemplates() {
    const response = await api.get('/custom-reports/templates')
    return response.data
  },

  async getTemplate(id: number) {
    const response = await api.get(`/custom-reports/templates/${id}`)
    return response.data
  },

  async createTemplate(data: Partial<ReportTemplate>) {
    const response = await api.post('/custom-reports/templates', data)
    return response.data
  },

  async updateTemplate(id: number, data: Partial<ReportTemplate>) {
    const response = await api.put(`/custom-reports/templates/${id}`, data)
    return response.data
  },

  async deleteTemplate(id: number) {
    const response = await api.delete(`/custom-reports/templates/${id}`)
    return response.data
  },

  async generate(params: GenerateParams): Promise<{ data: ReportData }> {
    const response = await api.post('/custom-reports/generate', params)
    return response.data
  },

  async exportPdf(params: GenerateParams) {
    const response = await api.post('/custom-reports/export-pdf', params, {
      responseType: 'blob',
    })
    return response.data
  },

  async exportExcel(params: GenerateParams) {
    const response = await api.post('/custom-reports/export-excel', params, {
      responseType: 'blob',
    })
    return response.data
  },

  downloadBlob(blob: Blob, filename: string) {
    const url = window.URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.setAttribute('download', filename)
    document.body.appendChild(link)
    link.click()
    link.remove()
    window.URL.revokeObjectURL(url)
  },
}

export default customReportsService
