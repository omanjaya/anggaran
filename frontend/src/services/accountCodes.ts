import api from './api'

export interface AccountCode {
    id: number
    code: string
    description: string
    level: number
    parent_code: string | null
    is_active: boolean
    created_at: string
    children?: AccountCode[]
}

export interface AccountCodeParams {
    page?: number
    per_page?: number
    search?: string
    level?: number
    parent_code?: string
}

export const accountCodeService = {
    // Get all account codes with pagination
    async getAll(params?: AccountCodeParams) {
        const response = await api.get('/account-codes', { params })
        return response.data
    },

    // Get single account code
    async get(code: string) {
        const response = await api.get(`/account-codes/${code}`)
        return response.data
    },

    // Create account code
    async create(data: Partial<AccountCode>) {
        const response = await api.post('/account-codes', data)
        return response.data
    },

    // Update account code
    async update(code: string, data: Partial<AccountCode>) {
        const response = await api.put(`/account-codes/${code}`, data)
        return response.data
    },

    // Delete account code
    async delete(code: string) {
        const response = await api.delete(`/account-codes/${code}`)
        return response.data
    },

    // Get tree structure
    async getTree() {
        const response = await api.get('/account-codes/tree')
        return response.data
    },

    // Get leaf nodes only
    async getLeafNodes() {
        const response = await api.get('/account-codes/leaf-nodes')
        return response.data
    },

    // Get level descriptions
    async getLevels() {
        const response = await api.get('/account-codes/levels')
        return response.data
    },

    // Import from Excel
    async import(file: File) {
        const formData = new FormData()
        formData.append('file', file)
        const response = await api.post('/account-codes/import', formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        })
        return response.data
    },
}

export default accountCodeService
