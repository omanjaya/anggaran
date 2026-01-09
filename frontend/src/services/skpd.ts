import api from './api'

export interface Skpd {
  id: number
  code: string
  name: string
  short_name: string | null
  address: string | null
  phone: string | null
  email: string | null
  head_name: string | null
  head_nip: string | null
  is_active: boolean
  users_count?: number
  programs_count?: number
  created_at: string
  updated_at: string
}

export interface SkpdFilters {
  is_active?: boolean
  search?: string
}

export const skpdService = {
  async getAll(filters: SkpdFilters = {}) {
    const response = await api.get('/skpd', { params: filters })
    return response.data
  },

  async getById(id: number) {
    const response = await api.get(`/skpd/${id}`)
    return response.data
  },

  async create(data: Partial<Skpd>) {
    const response = await api.post('/skpd', data)
    return response.data
  },

  async update(id: number, data: Partial<Skpd>) {
    const response = await api.put(`/skpd/${id}`, data)
    return response.data
  },

  async delete(id: number) {
    const response = await api.delete(`/skpd/${id}`)
    return response.data
  },

  async toggleActive(id: number) {
    const response = await api.post(`/skpd/${id}/toggle-active`)
    return response.data
  },

  async getUsers(id: number) {
    const response = await api.get(`/skpd/${id}/users`)
    return response.data
  },

  async getPrograms(id: number) {
    const response = await api.get(`/skpd/${id}/programs`)
    return response.data
  },
}

export default skpdService
