# Architecture Blueprint
## SIPERA - Sistem Informasi Perencanaan dan Realisasi Anggaran

**Version:** 1.0
**Date:** 8 Januari 2026

---

## 1. ARCHITECTURE OVERVIEW

### 1.1 Architecture Style
**Monolithic with Modular Structure** - Dipilih untuk simplicity dan kemudahan deployment pada fase awal, dengan struktur modular yang memungkinkan ekstraksi ke microservices di masa depan.

### 1.2 Architecture Principles
1. **Separation of Concerns** - Setiap layer memiliki tanggung jawab spesifik
2. **Single Responsibility** - Setiap class/module hanya memiliki satu alasan untuk berubah
3. **Dependency Injection** - Dependencies di-inject, bukan di-hardcode
4. **Interface Segregation** - Client tidak dipaksa depend pada interface yang tidak digunakan
5. **Open/Closed** - Open for extension, closed for modification

---

## 2. BACKEND ARCHITECTURE (Laravel)

### 2.1 Layer Architecture
```
┌─────────────────────────────────────────────────────────────────────────────┐
│                           PRESENTATION LAYER                                 │
│  ┌───────────────────────────────────────────────────────────────────────┐  │
│  │                          HTTP Controllers                             │  │
│  │  - Handle HTTP requests/responses                                     │  │
│  │  - Input validation                                                   │  │
│  │  - Authentication/Authorization checks                                │  │
│  │  - Transform data to/from DTOs                                       │  │
│  └───────────────────────────────────────────────────────────────────────┘  │
│                                    │                                        │
│                                    ▼                                        │
│  ┌───────────────────────────────────────────────────────────────────────┐  │
│  │                          Form Requests                                │  │
│  │  - Request validation rules                                           │  │
│  │  - Authorization logic                                                │  │
│  │  - Custom validation messages                                         │  │
│  └───────────────────────────────────────────────────────────────────────┘  │
│                                    │                                        │
│                                    ▼                                        │
│  ┌───────────────────────────────────────────────────────────────────────┐  │
│  │                          API Resources                                │  │
│  │  - Transform models to JSON                                          │  │
│  │  - Control what data is exposed                                       │  │
│  │  - Conditional includes                                               │  │
│  └───────────────────────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────────────────────┘
                                     │
                                     ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                           APPLICATION LAYER                                  │
│  ┌───────────────────────────────────────────────────────────────────────┐  │
│  │                            Services                                    │  │
│  │  - Business logic orchestration                                        │  │
│  │  - Transaction management                                              │  │
│  │  - Coordination between repositories                                   │  │
│  │  - Event dispatching                                                   │  │
│  └───────────────────────────────────────────────────────────────────────┘  │
│                                    │                                        │
│                                    ▼                                        │
│  ┌───────────────────────────────────────────────────────────────────────┐  │
│  │                        Action Classes                                 │  │
│  │  - Single-purpose actions (e.g., ApproveRealizationAction)           │  │
│  │  - Encapsulate complex business operations                           │  │
│  │  - Reusable across controllers/commands                               │  │
│  └───────────────────────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────────────────────┘
                                     │
                                     ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                            DOMAIN LAYER                                      │
│  ┌───────────────────────────────────────────────────────────────────────┐  │
│  │                            Models                                      │  │
│  │  - Eloquent models                                                     │  │
│  │  - Relationships                                                       │  │
│  │  - Scopes                                                              │  │
│  │  - Mutators/Accessors                                                 │  │
│  └───────────────────────────────────────────────────────────────────────┘  │
│                                    │                                        │
│                                    ▼                                        │
│  ┌───────────────────────────────────────────────────────────────────────┐  │
│  │                        Domain Events                                  │  │
│  │  - RealizationSubmitted                                               │  │
│  │  - RealizationApproved                                                │  │
│  │  - DeadlineApproaching                                                │  │
│  └───────────────────────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────────────────────┘
                                     │
                                     ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                         INFRASTRUCTURE LAYER                                 │
│  ┌───────────────────────────────────────────────────────────────────────┐  │
│  │                         Repositories                                   │  │
│  │  - Data access abstraction                                            │  │
│  │  - Complex queries                                                     │  │
│  │  - Caching logic                                                       │  │
│  └───────────────────────────────────────────────────────────────────────┘  │
│                                    │                                        │
│  ┌───────────────────────────────────────────────────────────────────────┐  │
│  │                      External Services                                │  │
│  │  - Email service                                                       │  │
│  │  - File storage service                                               │  │
│  │  - PDF generation service                                              │  │
│  └───────────────────────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────────────────────┘
```

### 2.2 Folder Structure
```
app/
├── Http/
│   ├── Controllers/
│   │   ├── Api/
│   │   │   ├── AuthController.php
│   │   │   ├── DashboardController.php
│   │   │   ├── Planning/
│   │   │   │   ├── ProgramController.php
│   │   │   │   ├── ActivityController.php
│   │   │   │   ├── SubActivityController.php
│   │   │   │   ├── BudgetItemController.php
│   │   │   │   └── MonthlyPlanController.php
│   │   │   ├── Realization/
│   │   │   │   ├── RealizationController.php
│   │   │   │   ├── VerificationController.php
│   │   │   │   └── ApprovalController.php
│   │   │   ├── Reporting/
│   │   │   │   ├── MonthlyReportController.php
│   │   │   │   └── CustomReportController.php
│   │   │   └── Master/
│   │   │       ├── AccountCodeController.php
│   │   │       └── UserController.php
│   │   └── Middleware/
│   │       ├── RoleMiddleware.php
│   │       ├── AuditLogMiddleware.php
│   │       └── CategoryAccessMiddleware.php
│   ├── Requests/
│   │   ├── Auth/
│   │   │   └── LoginRequest.php
│   │   ├── Planning/
│   │   │   ├── StoreBudgetItemRequest.php
│   │   │   └── GeneratePlgkRequest.php
│   │   └── Realization/
│   │       ├── StoreRealizationRequest.php
│   │       └── VerifyRealizationRequest.php
│   └── Resources/
│       ├── UserResource.php
│       ├── BudgetItemResource.php
│       ├── RealizationResource.php
│       └── ReportResource.php
│
├── Services/
│   ├── Auth/
│   │   └── AuthService.php
│   ├── Planning/
│   │   ├── DpaService.php
│   │   ├── PlgkService.php
│   │   └── RokOpService.php
│   ├── Realization/
│   │   ├── RealizationService.php
│   │   ├── VerificationService.php
│   │   └── ApprovalService.php
│   ├── Reporting/
│   │   ├── ReportService.php
│   │   ├── PdfGeneratorService.php
│   │   └── ExcelGeneratorService.php
│   └── Notification/
│       └── NotificationService.php
│
├── Actions/
│   ├── Realization/
│   │   ├── SubmitRealizationAction.php
│   │   ├── VerifyRealizationAction.php
│   │   ├── ApproveRealizationAction.php
│   │   └── RejectRealizationAction.php
│   └── Planning/
│       └── GeneratePlgkAction.php
│
├── Models/
│   ├── User.php
│   ├── Program.php
│   ├── Activity.php
│   ├── SubActivity.php
│   ├── AccountCode.php
│   ├── BudgetItem.php
│   ├── MonthlyPlan.php
│   ├── MonthlyRealization.php
│   ├── RealizationDocument.php
│   ├── Notification.php
│   └── AuditLog.php
│
├── Repositories/
│   ├── Contracts/
│   │   ├── ProgramRepositoryInterface.php
│   │   ├── BudgetRepositoryInterface.php
│   │   └── RealizationRepositoryInterface.php
│   └── Eloquent/
│       ├── ProgramRepository.php
│       ├── BudgetRepository.php
│       └── RealizationRepository.php
│
├── Events/
│   ├── RealizationSubmitted.php
│   ├── RealizationVerified.php
│   ├── RealizationApproved.php
│   └── RealizationRejected.php
│
├── Listeners/
│   ├── SendVerificationNotification.php
│   ├── SendApprovalNotification.php
│   └── LogAuditTrail.php
│
├── Jobs/
│   ├── GenerateMonthlyReport.php
│   ├── SendEmailNotification.php
│   └── CheckDeadlineAlerts.php
│
├── Enums/
│   ├── UserRole.php
│   ├── RealizationStatus.php
│   ├── SubActivityCategory.php
│   └── DocumentType.php
│
└── Exceptions/
    ├── RealizationLockedException.php
    ├── InvalidStatusTransitionException.php
    └── InsufficientPermissionException.php
```

### 2.3 Design Patterns Used

| Pattern | Usage |
|---------|-------|
| **Repository Pattern** | Data access abstraction, easier testing |
| **Service Pattern** | Business logic encapsulation |
| **Action Pattern** | Single-purpose business operations |
| **Observer Pattern** | Event-driven architecture (Laravel Events) |
| **Strategy Pattern** | PLGK generation methods |
| **Factory Pattern** | Report generation |

---

## 3. FRONTEND ARCHITECTURE (Vue.js)

### 3.1 Component Architecture
```
┌─────────────────────────────────────────────────────────────────────────────┐
│                              VIEWS (Pages)                                   │
│  ┌───────────────────────────────────────────────────────────────────────┐  │
│  │  - Route-level components                                             │  │
│  │  - Page layout composition                                            │  │
│  │  - Data fetching (via stores)                                         │  │
│  │  - Route guards                                                        │  │
│  └───────────────────────────────────────────────────────────────────────┘  │
│                                    │                                        │
│                                    ▼                                        │
│  ┌───────────────────────────────────────────────────────────────────────┐  │
│  │                         SMART COMPONENTS                              │  │
│  │  - Business logic aware                                               │  │
│  │  - Connect to stores                                                   │  │
│  │  - Handle user interactions                                           │  │
│  │  - Orchestrate dumb components                                         │  │
│  └───────────────────────────────────────────────────────────────────────┘  │
│                                    │                                        │
│                                    ▼                                        │
│  ┌───────────────────────────────────────────────────────────────────────┐  │
│  │                         DUMB COMPONENTS                               │  │
│  │  - Pure presentation                                                   │  │
│  │  - Props in, events out                                               │  │
│  │  - No direct store access                                             │  │
│  │  - Highly reusable                                                    │  │
│  └───────────────────────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────────────────────┘
                                     │
                                     ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                              STATE MANAGEMENT                                │
│  ┌───────────────────────────────────────────────────────────────────────┐  │
│  │                          Pinia Stores                                  │  │
│  │  - Centralized state                                                   │  │
│  │  - Actions for async operations                                       │  │
│  │  - Getters for computed state                                         │  │
│  │  - Persist to localStorage (optional)                                 │  │
│  └───────────────────────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────────────────────┘
                                     │
                                     ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                              API LAYER                                       │
│  ┌───────────────────────────────────────────────────────────────────────┐  │
│  │                         API Services                                   │  │
│  │  - Axios instance configuration                                       │  │
│  │  - Request/response interceptors                                      │  │
│  │  - Error handling                                                      │  │
│  │  - API endpoint definitions                                           │  │
│  └───────────────────────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────────────────────┘
```

### 3.2 Folder Structure
```
src/
├── assets/
│   ├── images/
│   └── styles/
│       ├── variables.css
│       └── global.css
│
├── components/
│   ├── common/
│   │   ├── BaseButton.vue
│   │   ├── BaseInput.vue
│   │   ├── BaseSelect.vue
│   │   ├── BaseTable.vue
│   │   ├── BaseModal.vue
│   │   ├── BaseCard.vue
│   │   ├── FileUpload.vue
│   │   ├── DataTable.vue
│   │   └── Pagination.vue
│   │
│   ├── layout/
│   │   ├── AppHeader.vue
│   │   ├── AppSidebar.vue
│   │   ├── AppFooter.vue
│   │   ├── AppBreadcrumb.vue
│   │   └── NotificationBell.vue
│   │
│   ├── charts/
│   │   ├── LineChart.vue
│   │   ├── BarChart.vue
│   │   ├── PieChart.vue
│   │   └── GaugeChart.vue
│   │
│   └── modules/
│       ├── dashboard/
│       │   ├── SummaryCards.vue
│       │   ├── AbsorptionChart.vue
│       │   ├── CategoryBreakdown.vue
│       │   └── AlertList.vue
│       │
│       ├── planning/
│       │   ├── DpaWizard.vue
│       │   ├── BudgetItemForm.vue
│       │   ├── PlgkTable.vue
│       │   └── RokOpCalendar.vue
│       │
│       ├── realization/
│       │   ├── RealizationForm.vue
│       │   ├── DocumentUploader.vue
│       │   ├── VerificationQueue.vue
│       │   ├── ApprovalQueue.vue
│       │   └── RealizationDetail.vue
│       │
│       └── reporting/
│           ├── ReportCriteria.vue
│           ├── ReportPreview.vue
│           └── ReportExporter.vue
│
├── views/
│   ├── auth/
│   │   ├── LoginView.vue
│   │   └── ForgotPasswordView.vue
│   │
│   ├── dashboard/
│   │   ├── KadisDashboard.vue
│   │   ├── PelaksanaDashboard.vue
│   │   └── MonevDashboard.vue
│   │
│   ├── planning/
│   │   ├── ProgramList.vue
│   │   ├── DpaEntry.vue
│   │   ├── PlgkGenerator.vue
│   │   └── RokOpCreator.vue
│   │
│   ├── realization/
│   │   ├── RealizationList.vue
│   │   ├── RealizationCreate.vue
│   │   ├── VerificationList.vue
│   │   └── ApprovalList.vue
│   │
│   ├── reporting/
│   │   ├── MonthlyReport.vue
│   │   ├── QuarterlyReport.vue
│   │   └── CustomReport.vue
│   │
│   └── master/
│       ├── UserList.vue
│       └── AccountCodeList.vue
│
├── stores/
│   ├── auth.js
│   ├── dashboard.js
│   ├── planning.js
│   ├── realization.js
│   ├── reporting.js
│   └── notification.js
│
├── services/
│   ├── api.js              # Axios instance & config
│   ├── authService.js
│   ├── dashboardService.js
│   ├── planningService.js
│   ├── realizationService.js
│   └── reportService.js
│
├── router/
│   ├── index.js
│   └── guards.js
│
├── composables/
│   ├── useAuth.js
│   ├── useNotification.js
│   ├── usePagination.js
│   └── useDebounce.js
│
├── utils/
│   ├── validators.js
│   ├── formatters.js
│   ├── constants.js
│   └── helpers.js
│
├── App.vue
└── main.js
```

---

## 4. SECURITY ARCHITECTURE

### 4.1 Authentication Flow
```
┌─────────────────────────────────────────────────────────────────────────────┐
│                          AUTHENTICATION FLOW                                 │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│   ┌─────────────┐                                     ┌─────────────┐       │
│   │   Browser   │────── POST /api/auth/login ────────▶│   Backend   │       │
│   │             │       { nip, password }             │             │       │
│   └─────────────┘                                     └──────┬──────┘       │
│                                                              │              │
│                                                              ▼              │
│                                                    ┌─────────────────────┐  │
│                                                    │ Validate Credentials│  │
│                                                    │ - Check NIP exists  │  │
│                                                    │ - Verify password   │  │
│                                                    │ - Check account lock│  │
│                                                    └──────────┬──────────┘  │
│                                                               │             │
│                                                               ▼             │
│                                                    ┌─────────────────────┐  │
│                                                    │   Generate JWT      │  │
│                                                    │   - user_id         │  │
│                                                    │   - role            │  │
│                                                    │   - permissions     │  │
│                                                    │   - exp (8 hours)   │  │
│                                                    └──────────┬──────────┘  │
│                                                               │             │
│   ┌─────────────┐                                             │             │
│   │   Browser   │◀───── { token, refresh_token, user } ───────┘             │
│   │             │                                                           │
│   │  Store in   │                                                           │
│   │ localStorage│                                                           │
│   └─────────────┘                                                           │
│                                                                              │
│   SUBSEQUENT REQUESTS:                                                      │
│   ┌─────────────┐                                     ┌─────────────┐       │
│   │   Browser   │────── Authorization: Bearer {token} ▶│   Backend   │       │
│   │             │                                     │             │       │
│   │             │◀───── Response / 401 Unauthorized ──│ Verify JWT  │       │
│   └─────────────┘                                     └─────────────┘       │
│                                                                              │
└─────────────────────────────────────────────────────────────────────────────┘
```

### 4.2 Authorization Matrix

```php
// Role Permissions
$permissions = [
    'ADMIN' => [
        'users.*',
        'programs.*',
        'activities.*',
        'sub_activities.*',
        'budget_items.*',
        'realizations.*',
        'reports.*',
        'notifications.*',
    ],
    'KADIS' => [
        'dashboard.view',
        'programs.view',
        'activities.view',
        'sub_activities.view',
        'budget_items.view',
        'realizations.view',
        'realizations.approve',
        'reports.*',
    ],
    'TIM_PERENCANAAN' => [
        'dashboard.view',
        'programs.*',
        'activities.*',
        'sub_activities.*',
        'budget_items.*',
        'monthly_plans.*',
        'realizations.view',
        'reports.view',
        'reports.generate',
    ],
    'TIM_PELAKSANA' => [
        'dashboard.view',
        'budget_items.view',
        'realizations.view_own',
        'realizations.create',
        'realizations.update_own',
        'realizations.submit',
    ],
    'BENDAHARA' => [
        'dashboard.view',
        'realizations.view',
        'realizations.verify',
        'reports.view',
    ],
    'MONEV' => [
        'dashboard.view',
        'programs.view',
        'activities.view',
        'sub_activities.view',
        'budget_items.view',
        'realizations.view',
        'reports.*',
    ],
    'VIEWER' => [
        'dashboard.view',
        'reports.view',
    ],
];
```

---

## 5. CACHING STRATEGY

### 5.1 Cache Layers
```
┌─────────────────────────────────────────────────────────────────────────────┐
│                            CACHING LAYERS                                    │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│   BROWSER CACHE                                                             │
│   ┌───────────────────────────────────────────────────────────────────────┐ │
│   │ - Static assets (JS, CSS, images): 1 year with content hash           │ │
│   │ - API responses with ETag: conditional requests                       │ │
│   │ - localStorage: user preferences, draft data                          │ │
│   └───────────────────────────────────────────────────────────────────────┘ │
│                                                                              │
│   CDN CACHE (Optional)                                                      │
│   ┌───────────────────────────────────────────────────────────────────────┐ │
│   │ - Static assets: forever (cache busting via filename hash)            │ │
│   │ - Public API responses: 5 minutes (account codes, etc.)               │ │
│   └───────────────────────────────────────────────────────────────────────┘ │
│                                                                              │
│   APPLICATION CACHE (Redis)                                                 │
│   ┌───────────────────────────────────────────────────────────────────────┐ │
│   │ - Session data: 8 hours                                               │ │
│   │ - User permissions: 1 hour (invalidate on role change)                │ │
│   │ - Dashboard summary: 5 minutes                                        │ │
│   │ - Master data (programs, activities): 1 hour                          │ │
│   │ - Account codes: 24 hours                                             │ │
│   │ - Rate limiting counters: 5 minutes                                   │ │
│   └───────────────────────────────────────────────────────────────────────┘ │
│                                                                              │
│   DATABASE CACHE (PostgreSQL)                                               │
│   ┌───────────────────────────────────────────────────────────────────────┐ │
│   │ - Query plan cache: automatic                                         │ │
│   │ - Shared buffers: 25% of RAM                                         │ │
│   │ - Materialized views for complex reports: refresh on demand          │ │
│   └───────────────────────────────────────────────────────────────────────┘ │
│                                                                              │
└─────────────────────────────────────────────────────────────────────────────┘
```

### 5.2 Cache Keys Convention
```
sipera:{entity}:{id}:{variant}

Examples:
- sipera:user:123:permissions
- sipera:dashboard:123:summary:2026
- sipera:programs:all:2026
- sipera:report:monthly:2026:11:LAYANAN
```

---

## 6. ERROR HANDLING

### 6.1 Backend Error Handling
```php
// app/Exceptions/Handler.php
class Handler extends ExceptionHandler
{
    public function render($request, Throwable $e)
    {
        if ($request->expectsJson()) {
            return $this->handleApiException($request, $e);
        }
        return parent::render($request, $e);
    }

    protected function handleApiException($request, Throwable $e)
    {
        return match (true) {
            $e instanceof ValidationException => response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $e->errors(),
            ], 422),

            $e instanceof AuthenticationException => response()->json([
                'success' => false,
                'message' => 'Token tidak valid atau sudah expired',
                'code' => 'AUTH001',
            ], 401),

            $e instanceof AuthorizationException => response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses ke resource ini',
                'code' => 'PERM001',
            ], 403),

            $e instanceof ModelNotFoundException => response()->json([
                'success' => false,
                'message' => 'Data tidak ditemukan',
                'code' => 'DATA001',
            ], 404),

            $e instanceof RealizationLockedException => response()->json([
                'success' => false,
                'message' => 'Data sudah terkunci dan tidak bisa diubah',
                'code' => 'DATA002',
            ], 422),

            default => response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan internal',
                'code' => 'SYS001',
            ], 500),
        };
    }
}
```

### 6.2 Frontend Error Handling
```javascript
// services/api.js
const api = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL,
});

api.interceptors.response.use(
  (response) => response,
  (error) => {
    const { response } = error;

    if (response?.status === 401) {
      // Token expired - redirect to login
      authStore.logout();
      router.push('/login');
    }

    if (response?.status === 403) {
      // No permission - show error
      notification.error('Anda tidak memiliki akses');
    }

    if (response?.status === 422) {
      // Validation error - let component handle
      return Promise.reject(error);
    }

    if (response?.status >= 500) {
      // Server error - show generic message
      notification.error('Terjadi kesalahan server. Silakan coba lagi.');
    }

    return Promise.reject(error);
  }
);
```

---

## 7. DEPLOYMENT ARCHITECTURE

### 7.1 Production Setup
```
                                    Internet
                                       │
                                       ▼
                            ┌─────────────────┐
                            │   Cloudflare    │
                            │   (CDN + WAF)   │
                            └────────┬────────┘
                                     │
                                     ▼
                            ┌─────────────────┐
                            │     Nginx       │
                            │ (Load Balancer) │
                            └────────┬────────┘
                                     │
                    ┌────────────────┼────────────────┐
                    │                │                │
                    ▼                ▼                ▼
             ┌───────────┐    ┌───────────┐    ┌───────────┐
             │  App 1    │    │  App 2    │    │  App N    │
             │ (Laravel) │    │ (Laravel) │    │ (Laravel) │
             └─────┬─────┘    └─────┬─────┘    └─────┬─────┘
                   │                │                │
                   └────────────────┼────────────────┘
                                    │
                    ┌───────────────┼───────────────┐
                    │               │               │
                    ▼               ▼               ▼
             ┌───────────┐   ┌───────────┐   ┌───────────┐
             │PostgreSQL │   │   Redis   │   │   MinIO   │
             │ (Primary) │   │ (Cluster) │   │ (Storage) │
             └─────┬─────┘   └───────────┘   └───────────┘
                   │
                   ▼
             ┌───────────┐
             │PostgreSQL │
             │ (Replica) │
             └───────────┘
```

---

**Document Status:** Complete
**Last Updated:** 8 Januari 2026
