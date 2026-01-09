import api from './api'

export interface AuditLog {
    id: number
    user_id: number | null
    action: string
    auditable_type: string
    auditable_id: number | null
    old_values: Record<string, any> | null
    new_values: Record<string, any> | null
    ip_address: string | null
    user_agent: string | null
    url: string | null
    method: string | null
    created_at: string
    user?: {
        id: number
        name: string
        email: string
    }
}

export interface AuditLogParams {
    page?: number
    per_page?: number
    action?: string
    user_id?: number
    model_type?: string
    from?: string
    to?: string
    search?: string
}

export interface AuditLogStats {
    total: number
    by_action: Record<string, number>
    by_user: Array<{
        user_id: number
        count: number
        user: { id: number; name: string }
    }>
    recent: AuditLog[]
}

export const auditLogService = {
    // Get all audit logs with filters
    async getAll(params?: AuditLogParams) {
        const response = await api.get('/audit-logs', { params })
        return response.data
    },

    // Get single audit log
    async get(id: number) {
        const response = await api.get(`/audit-logs/${id}`)
        return response.data
    },

    // Get logs for specific model
    async getForModel(modelType: string, modelId: number) {
        const response = await api.get('/audit-logs/for-model', {
            params: { model_type: modelType, model_id: modelId },
        })
        return response.data
    },

    // Get available actions
    async getActions() {
        const response = await api.get('/audit-logs/actions')
        return response.data
    },

    // Get statistics
    async getStats() {
        const response = await api.get('/audit-logs/stats')
        return response.data
    },
}

export default auditLogService
