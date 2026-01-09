import { createRouter, createWebHistory, type RouteRecordRaw } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const routes: RouteRecordRaw[] = [
  {
    path: '/login',
    name: 'login',
    component: () => import('@/views/auth/LoginView.vue'),
    meta: { requiresAuth: false },
  },
  {
    path: '/',
    component: () => import('@/layouts/MainLayout.vue'),
    meta: { requiresAuth: true },
    children: [
      {
        path: '',
        name: 'dashboard',
        component: () => import('@/views/dashboard/DashboardView.vue'),
      },
      // Master Data
      {
        path: 'master/programs',
        name: 'programs',
        component: () => import('@/views/master/ProgramsView.vue'),
        meta: { permission: 'master.view' },
      },
      {
        path: 'master/activities',
        name: 'activities',
        component: () => import('@/views/master/ActivitiesView.vue'),
        meta: { permission: 'master.view' },
      },
      {
        path: 'master/sub-activities',
        name: 'sub-activities',
        component: () => import('@/views/master/SubActivitiesView.vue'),
        meta: { permission: 'master.view' },
      },
      {
        path: 'master/budget-items',
        name: 'budget-items',
        component: () => import('@/views/master/BudgetItemsView.vue'),
        meta: { permission: 'master.view' },
      },
      {
        path: 'master/account-codes',
        name: 'account-codes',
        component: () => import('@/views/master/AccountCodesView.vue'),
        meta: { permission: 'master.view' },
      },
      {
        path: 'master/users',
        name: 'users',
        component: () => import('@/views/master/UsersView.vue'),
        meta: { permission: 'users.view' },
      },
      // Planning
      {
        path: 'planning/monthly',
        name: 'monthly-planning',
        component: () => import('@/views/planning/MonthlyPlanningGridView.vue'),
        meta: { permission: 'planning.view' },
      },
      {
        path: 'planning/dpa-entry',
        name: 'dpa-entry',
        component: () => import('@/views/planning/DpaEntryView.vue'),
        meta: { permission: 'planning.manage' },
      },
      {
        path: 'planning/plgk-generator',
        name: 'plgk-generator',
        component: () => import('@/views/planning/PlgkGeneratorView.vue'),
        meta: { permission: 'planning.manage' },
      },
      // Realization
      {
        path: 'realization/monthly',
        name: 'monthly-realization',
        component: () => import('@/views/realization/MonthlyRealizationView.vue'),
        meta: { permission: 'realization.view' },
      },
      {
        path: 'realization/approval',
        name: 'approval',
        component: () => import('@/views/realization/ApprovalView.vue'),
        meta: { permission: 'approval.view' },
      },
      {
        path: 'realization/verification',
        name: 'verification',
        component: () => import('@/views/realization/VerificationView.vue'),
        meta: { permission: 'verification.view' },
      },
      // Reports
      {
        path: 'reports/summary',
        name: 'summary-report',
        component: () => import('@/views/reports/SummaryReportView.vue'),
        meta: { permission: 'reports.view' },
      },
      {
        path: 'reports/category',
        name: 'category-report',
        component: () => import('@/views/reports/CategoryReportView.vue'),
        meta: { permission: 'reports.view' },
      },
      {
        path: 'reports/realisasi',
        name: 'realisasi-report',
        component: () => import('@/views/reports/RealisasiReportView.vue'),
        meta: { permission: 'reports.view' },
      },
      {
        path: 'reports/custom',
        name: 'custom-report',
        component: () => import('@/views/reports/CustomReportBuilderView.vue'),
        meta: { permission: 'reports.view' },
      },
      // ROK OP - Operational Schedule
      {
        path: 'operational-schedule',
        name: 'operational-schedule',
        component: () => import('@/views/operational-schedule/OperationalScheduleView.vue'),
        meta: { permission: 'planning.view' },
      },
      // Deviation Alerts
      {
        path: 'deviation-alerts',
        name: 'deviation-alerts',
        component: () => import('@/views/alerts/DeviationAlertsView.vue'),
        meta: { permission: 'reports.view' },
      },
      // Data Import
      {
        path: 'import',
        name: 'data-import',
        component: () => import('@/views/import/DataImportView.vue'),
        meta: { permission: 'master.manage' },
      },
      {
        path: 'import/dpa-pdf',
        name: 'dpa-pdf-import',
        component: () => import('@/views/import/DpaPdfImportView.vue'),
        meta: { permission: 'master.manage' },
      },
      // Settings - SKPD Management
      {
        path: 'settings/skpd',
        name: 'skpd-management',
        component: () => import('@/views/settings/SkpdManagementView.vue'),
        meta: { permission: 'master.manage' },
      },
      {
        path: 'settings/audit-log',
        name: 'audit-log',
        component: () => import('@/views/settings/AuditLogView.vue'),
        meta: { permission: 'master.manage' },
      },
    ],
  },
  {
    path: '/:pathMatch(.*)*',
    name: 'not-found',
    component: () => import('@/views/NotFoundView.vue'),
  },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
})

router.beforeEach(async (to, _from, next) => {
  const authStore = useAuthStore()

  // Wait for auth initialization if not yet done
  if (!authStore.initialized && authStore.token) {
    await authStore.fetchUser()
  }

  // Check authentication for protected routes
  if (to.meta.requiresAuth && !authStore.isAuthenticated) {
    next({ name: 'login', query: { redirect: to.fullPath } })
    return
  }

  // Redirect to dashboard if already logged in and trying to access login
  if (to.name === 'login' && authStore.isAuthenticated) {
    next({ name: 'dashboard' })
    return
  }

  // Check permissions
  if (to.meta.permission && !authStore.hasPermission(to.meta.permission as string)) {
    next({ name: 'dashboard' })
    return
  }

  next()
})

export default router
