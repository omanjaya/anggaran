export type UserRole =
  | 'ADMIN'
  | 'KADIS'
  | 'TIM_PERENCANAAN'
  | 'TIM_PELAKSANA'
  | 'BENDAHARA'
  | 'MONEV'
  | 'VIEWER'

export type BudgetCategory =
  | 'ANALISIS'
  | 'TATA_KELOLA'
  | 'OPERASIONALISASI'
  | 'LAYANAN'
  | 'ELEK_NON_ELEK'

export type ApprovalStatus =
  | 'DRAFT'
  | 'SUBMITTED'
  | 'VERIFIED'
  | 'APPROVED'
  | 'REJECTED'

export interface User {
  id: number
  name: string
  email: string
  role: UserRole
  is_active: boolean
  created_at: string
  updated_at: string
}

export interface Program {
  id: number
  code: string
  name: string
  category: BudgetCategory
  fiscal_year: number
  total_budget: number
  is_active: boolean
  created_at: string
  updated_at: string
  activities?: Activity[]
}

export interface Activity {
  id: number
  program_id: number
  code: string
  name: string
  total_budget: number
  is_active: boolean
  created_at: string
  updated_at: string
  program?: Program
  sub_activities?: SubActivity[]
}

export interface SubActivity {
  id: number
  activity_id: number
  code: string
  name: string
  total_budget: number
  is_active: boolean
  created_at: string
  updated_at: string
  activity?: Activity
  budget_items?: BudgetItem[]
}

export interface BudgetItem {
  id: number
  sub_activity_id: number
  code: string
  name: string
  unit: string
  volume: number
  unit_price: number
  total_budget: number
  is_active: boolean
  created_at: string
  updated_at: string
  sub_activity?: SubActivity
}

export interface MonthlyPlan {
  id: number
  budget_item_id: number
  month: number
  year: number
  planned_volume: number
  planned_amount: number
  notes: string | null
  created_by: number
  created_at: string
  updated_at: string
  budget_item?: BudgetItem
}

export interface MonthlyRealization {
  id: number
  monthly_plan_id: number
  realized_volume: number
  realized_amount: number
  deviation_volume: number
  deviation_amount: number
  deviation_percentage: number
  status: ApprovalStatus
  submitted_by: number | null
  submitted_at: string | null
  verified_by: number | null
  verified_at: string | null
  approved_by: number | null
  approved_at: string | null
  locked_at: string | null
  locked_by: number | null
  notes: string | null
  rejection_reason: string | null
  created_at: string
  updated_at: string
  monthly_plan?: MonthlyPlan
  documents?: RealizationDocument[]
}

export interface RealizationDocument {
  id: number
  monthly_realization_id: number
  filename: string
  original_filename: string
  file_path: string
  file_size: number
  mime_type: string
  uploaded_by: number
  created_at: string
}

export interface ApprovalHistory {
  id: number
  monthly_realization_id: number
  from_status: ApprovalStatus
  to_status: ApprovalStatus
  action: string
  notes: string | null
  performed_by: number
  created_at: string
}

// Dashboard types
export interface DashboardStats {
  total_budget: number
  total_realization: number
  realization_percentage: number
  pending_approvals: number
  categories: CategoryStat[]
  fiscal_year: number
}

export interface CategoryStat {
  code: string
  name: string
  budget: number
  realization: number
  percentage: number
}

export interface MonthlyTrend {
  month: number
  month_name: string
  planned: number
  realized: number
  deviation: number
}

export interface ProgramStat {
  id: number
  code: string
  name: string
  category: string
  budget: number
  planned: number
  realized: number
  percentage: number
}

export interface RecentActivity {
  id: number
  program: string
  budget_item: string
  month: number
  year: number
  amount: number
  status: string
  status_label: string
  updated_at: string
}

// Notification types
export interface Notification {
  id: string
  type: string
  data: {
    type: string
    title: string
    message: string
    realization_id: number
    budget_item: string
    month: number
    year: number
    amount: number
  }
  read_at: string | null
  created_at: string
}

// API Response types
export interface PaginatedResponse<T> {
  data: T[]
  meta: {
    current_page: number
    from?: number
    last_page: number
    per_page: number
    to?: number
    total: number
  }
}

export interface ApiResponse<T> {
  success: boolean
  message?: string
  data: T
}
