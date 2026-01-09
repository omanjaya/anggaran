import api from './api'

export interface PlgkMethod {
    value: string
    label: string
    description: string
}

export interface MonthlyPlan {
    id?: number
    month: number
    planned_volume: number
    planned_amount: number
}

export interface BudgetItemWithPlan {
    id: number
    account_code: string
    description: string
    unit: string
    unit_price: number
    total_volume: number
    total_amount: number
    monthly_plans?: MonthlyPlan[]
}

export interface PlgkData {
    sub_activity: {
        id: number
        category: string
        name: string
        budget_current_year: number
    }
    year: number
    budget_items: BudgetItemWithPlan[]
    summary: {
        total_budget: number
        total_planned: number
    }
}

export interface PlgkPreviewData {
    sub_activity: {
        id: number
        category: string
        name: string
        budget_current_year: number
    }
    year: number
    method: string
    items: Array<{
        budget_item: BudgetItemWithPlan
        monthly_plans: MonthlyPlan[]
    }>
    summary: {
        total_items: number
        total_budget: number
    }
}

export interface PlgkGenerateParams {
    method: 'equal' | 'custom' | 'copy_previous'
    year: number
    custom_allocation?: Record<number, number[]> // budget_item_id => monthly values
    source_year?: number // for copy_previous
}

export const plgkService = {
    // Get available allocation methods
    async getMethods(): Promise<{ data: PlgkMethod[] }> {
        const response = await api.get('/plgk/methods')
        return response.data
    },

    // Get available years
    async getYears(): Promise<{ data: number[] }> {
        const response = await api.get('/plgk/years')
        return response.data
    },

    // Get PLGK data for a sub-activity
    async getPlgkData(subActivityId: number, year: number): Promise<{ data: PlgkData }> {
        const response = await api.get(`/plgk/${subActivityId}`, {
            params: { year },
        })
        return response.data
    },

    // Preview PLGK generation
    async preview(
        subActivityId: number,
        params: PlgkGenerateParams
    ): Promise<{ data: PlgkPreviewData }> {
        const response = await api.post(`/plgk/${subActivityId}/preview`, params)
        return response.data
    },

    // Generate PLGK
    async generate(subActivityId: number, params: PlgkGenerateParams) {
        const response = await api.post(`/plgk/${subActivityId}/generate`, params)
        return response.data
    },

    // Validate PLGK data
    async validate(subActivityId: number, year: number) {
        const response = await api.get(`/plgk/${subActivityId}/validate`, {
            params: { year },
        })
        return response.data
    },
}

export default plgkService
