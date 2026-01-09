import api from './api'

export interface PreviewRow {
  row_number: number
  program_code: string
  program_name: string
  activity_code: string
  activity_name: string
  sub_activity_code: string
  sub_activity_name: string
  item_code: string
  item_name: string
  unit: string
  price: number
  quantity: number
  total: number
  status: 'valid' | 'warning' | 'error'
  message?: string
}

export interface PreviewResult {
  total_rows: number
  valid_rows: number
  warning_rows: number
  error_rows: number
  rows: PreviewRow[]
}

export interface ImportResult {
  success: boolean
  total_rows: number
  imported: number
  skipped: number
  errors: string[]
  warnings: string[]
}

export const dataImportService = {
  async preview(file: File, type: 'dpa' | 'plgk', year: number): Promise<{ data: PreviewResult }> {
    const formData = new FormData()
    formData.append('file', file)
    formData.append('type', type)
    formData.append('year', year.toString())

    const response = await api.post('/import/preview', formData, {
      headers: {
        'Content-Type': 'multipart/form-data',
      },
    })
    return response.data
  },

  async importDpa(file: File, year: number): Promise<{ data: ImportResult }> {
    const formData = new FormData()
    formData.append('file', file)
    formData.append('year', year.toString())

    const response = await api.post('/import/dpa', formData, {
      headers: {
        'Content-Type': 'multipart/form-data',
      },
    })
    return response.data
  },

  async importPlgk(file: File, year: number): Promise<{ data: ImportResult }> {
    const formData = new FormData()
    formData.append('file', file)
    formData.append('year', year.toString())

    const response = await api.post('/import/plgk', formData, {
      headers: {
        'Content-Type': 'multipart/form-data',
      },
    })
    return response.data
  },

  async downloadTemplate(type: 'dpa' | 'plgk') {
    const response = await api.get(`/import/template/${type}`, {
      responseType: 'blob',
    })
    return response.data
  },
}

export default dataImportService
