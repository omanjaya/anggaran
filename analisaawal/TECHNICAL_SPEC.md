# TECHNICAL SPECIFICATION DOCUMENT
## SIPERA - Sistem Informasi Perencanaan dan Realisasi Anggaran

**Version:** 1.0  
**Date:** 8 Januari 2026  
**Author:** Development Team

---

## 1. SYSTEM ARCHITECTURE

### 1.1 High-Level Architecture

```
┌──────────────────────────────────────────────────────────────┐
│                      CLIENT LAYER                            │
│  - Web Browser (Desktop & Mobile)                            │
│  - Progressive Web App (Optional)                            │
└────────────────────────┬─────────────────────────────────────┘
                         │ HTTPS
                         ↓
┌──────────────────────────────────────────────────────────────┐
│                    CDN / Load Balancer                       │
│  - Static Assets Caching                                     │
│  - SSL Termination                                           │
│  - Request Distribution                                      │
└────────────────────────┬─────────────────────────────────────┘
                         │
          ┌──────────────┴──────────────┐
          ↓                             ↓
┌──────────────────┐          ┌──────────────────┐
│  WEB SERVER 1    │          │  WEB SERVER 2    │
│    (Nginx)       │          │    (Nginx)       │
│                  │          │                  │
│  ┌────────────┐  │          │  ┌────────────┐  │
│  │   Vue.js   │  │          │  │   Vue.js   │  │
│  │  Frontend  │  │          │  │  Frontend  │  │
│  └────────────┘  │          │  └────────────┘  │
│                  │          │                  │
│  ┌────────────┐  │          │  ┌────────────┐  │
│  │  Laravel   │  │          │  │  Laravel   │  │
│  │   Backend  │  │          │  │   Backend  │  │
│  └────────────┘  │          │  └────────────┘  │
└────────┬─────────┘          └─────────┬────────┘
         │                              │
         └───────────┬──────────────────┘
                     ↓
         ┌───────────────────────┐
         │   REDIS CLUSTER       │
         │  - Session Store      │
         │  - Cache Layer        │
         │  - Job Queue          │
         └───────────┬───────────┘
                     ↓
         ┌───────────────────────┐
         │   PostgreSQL Master   │
         │  - Primary Database   │
         └───────────┬───────────┘
                     │
          ┌──────────┴──────────┐
          ↓                     ↓
┌──────────────────┐  ┌──────────────────┐
│ PostgreSQL Slave │  │ PostgreSQL Slave │
│   (Read Replica) │  │   (Read Replica) │
└──────────────────┘  └──────────────────┘
```

### 1.2 Component Diagram

```
Backend (Laravel)
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── AuthController.php
│   │   │   ├── DashboardController.php
│   │   │   ├── PlanningController.php
│   │   │   ├── RealizationController.php
│   │   │   └── ReportController.php
│   │   ├── Middleware/
│   │   │   ├── Authenticate.php
│   │   │   ├── RoleMiddleware.php
│   │   │   └── AuditLog.php
│   │   └── Requests/
│   │       ├── StoreDpaRequest.php
│   │       └── StoreRealizationRequest.php
│   ├── Models/
│   │   ├── Program.php
│   │   ├── Activity.php
│   │   ├── SubActivity.php
│   │   ├── BudgetItem.php
│   │   ├── MonthlyPlan.php
│   │   ├── MonthlyRealization.php
│   │   └── User.php
│   ├── Services/
│   │   ├── AuthService.php
│   │   ├── DpaService.php
│   │   ├── PlgkService.php
│   │   ├── RealizationService.php
│   │   ├── ReportService.php
│   │   └── NotificationService.php
│   ├── Repositories/
│   │   ├── ProgramRepository.php
│   │   ├── BudgetRepository.php
│   │   └── RealizationRepository.php
│   ├── Jobs/
│   │   ├── GenerateMonthlyReport.php
│   │   ├── SendNotificationEmail.php
│   │   └── CheckDeadlineAlerts.php
│   ├── Events/
│   │   ├── RealizationSubmitted.php
│   │   └── RealizationApproved.php
│   └── Listeners/
│       ├── SendApprovalNotification.php
│       └── LogAudit.php

Frontend (Vue.js)
├── src/
│   ├── components/
│   │   ├── layout/
│   │   │   ├── AppHeader.vue
│   │   │   ├── AppSidebar.vue
│   │   │   └── AppFooter.vue
│   │   ├── common/
│   │   │   ├── DataTable.vue
│   │   │   ├── FormInput.vue
│   │   │   ├── FileUpload.vue
│   │   │   └── Chart.vue
│   │   └── modules/
│   │       ├── dashboard/
│   │       ├── planning/
│   │       ├── realization/
│   │       └── reporting/
│   ├── views/
│   │   ├── auth/
│   │   │   ├── Login.vue
│   │   │   └── ForgotPassword.vue
│   │   ├── dashboard/
│   │   │   ├── KadisDashboard.vue
│   │   │   └── PelaksanaDashboard.vue
│   │   ├── planning/
│   │   │   ├── DpaEntry.vue
│   │   │   ├── PlgkGenerator.vue
│   │   │   └── RokOpCreator.vue
│   │   ├── realization/
│   │   │   ├── RealizationInput.vue
│   │   │   ├── RealizationApproval.vue
│   │   │   └── BulkUpload.vue
│   │   └── reporting/
│   │       ├── MonthlyReport.vue
│   │       └── CustomReport.vue
│   ├── stores/
│   │   ├── auth.js
│   │   ├── dashboard.js
│   │   ├── planning.js
│   │   └── realization.js
│   ├── services/
│   │   ├── api.js
│   │   ├── authService.js
│   │   ├── planningService.js
│   │   └── reportService.js
│   ├── router/
│   │   └── index.js
│   └── utils/
│       ├── validators.js
│       ├── formatters.js
│       └── constants.js
```

---

## 2. TECHNOLOGY STACK DETAILS

### 2.1 Backend Stack

#### Laravel 11 (PHP 8.2+)
```yaml
Framework: Laravel 11.x
PHP Version: 8.2+
Web Server: Nginx 1.24+ or Apache 2.4+
Process Manager: PHP-FPM

Core Packages:
  - laravel/sanctum: API authentication
  - spatie/laravel-permission: Role & permission management
  - barryvdh/laravel-dompdf: PDF generation
  - maatwebsite/excel: Excel import/export
  - intervention/image: Image processing
  - predis/predis: Redis client
  - pusher/pusher-php-server: Real-time notifications (optional)

Development Tools:
  - laravel/telescope: Debugging & monitoring
  - laravel/pint: Code style fixer
  - nunomaduro/larastan: Static analysis
  - pestphp/pest: Testing framework
```

#### Database
```yaml
Primary: PostgreSQL 16.x
  - ACID compliance
  - JSON/JSONB support
  - Full-text search
  - Partitioning for large tables
  
Extensions:
  - pg_stat_statements: Query performance monitoring
  - pg_trgm: Fuzzy text search
  
Connection Pooling: PgBouncer

Backup Strategy:
  - Daily full backup (2 AM)
  - Hourly incremental backup
  - Point-in-time recovery enabled
  - Retention: 30 days
```

#### Cache & Queue
```yaml
Redis 7.x:
  - Session storage
  - Query result caching
  - Job queue
  - Rate limiting
  
Configuration:
  - Master-Slave replication
  - Persistence: AOF (Append-Only File)
  - Max memory: 4GB
  - Eviction policy: allkeys-lru
```

### 2.2 Frontend Stack

#### Vue.js 3
```yaml
Framework: Vue.js 3.x (Composition API)
Build Tool: Vite 5.x
State Management: Pinia 2.x
Router: Vue Router 4.x

UI Libraries (Choose one):
  Option 1: PrimeVue 3.x
    - Comprehensive component library
    - Built-in themes
    - Accessibility support
  
  Option 2: Ant Design Vue 4.x
    - Enterprise-grade
    - Rich components
    - i18n support

Utility Libraries:
  - axios: HTTP client
  - vee-validate: Form validation
  - vue-chartjs: Chart wrapper
  - echarts: Advanced charts
  - dayjs: Date manipulation
  - lodash-es: Utility functions
  - xlsx: Excel manipulation
  - jspdf: PDF generation (client-side)

Development Tools:
  - @vitejs/plugin-vue: Vue plugin for Vite
  - eslint: Linting
  - prettier: Code formatting
  - vitest: Unit testing
  - @vue/test-utils: Component testing
  - cypress: E2E testing
```

### 2.3 DevOps Stack

#### Containerization (Optional)
```yaml
Docker: 24.x
Docker Compose: 2.x

Services:
  - nginx: Web server
  - php-fpm: PHP processor
  - postgres: Database
  - redis: Cache & queue
  - minio: Object storage (optional)
```

#### CI/CD
```yaml
Platform: GitHub Actions / GitLab CI

Pipeline Stages:
  1. Install Dependencies
  2. Run Linters (PHP CS Fixer, ESLint)
  3. Run Unit Tests (PHPUnit, Vitest)
  4. Build Frontend Assets
  5. Run Integration Tests
  6. Deploy to Environment
  7. Run Smoke Tests
  8. Notify Team

Environments:
  - Development (auto-deploy on push to develop)
  - Staging (auto-deploy on push to staging)
  - Production (manual approval required)
```

#### Monitoring
```yaml
Application Monitoring:
  - Laravel Telescope (development)
  - Sentry (error tracking)
  - New Relic / DataDog (optional, performance monitoring)

Infrastructure Monitoring:
  - Prometheus + Grafana (metrics & visualization)
  - ELK Stack (centralized logging)
  - UptimeRobot (uptime monitoring)

Metrics to Track:
  - Response time (p50, p95, p99)
  - Error rate
  - Request rate
  - Database query time
  - Queue job processing time
  - Memory usage
  - CPU usage
```

---

## 3. API DESIGN

### 3.1 RESTful API Conventions

```
Base URL: https://sipera.baliprov.go.id/api

HTTP Methods:
  - GET: Retrieve resource(s)
  - POST: Create new resource
  - PUT/PATCH: Update resource
  - DELETE: Delete resource

Response Format:
  Success (2xx):
    {
      "success": true,
      "data": {...},
      "message": "Optional success message"
    }
  
  Error (4xx, 5xx):
    {
      "success": false,
      "message": "Error message",
      "errors": {
        "field": ["Validation error message"]
      }
    }

Status Codes:
  - 200: OK
  - 201: Created
  - 204: No Content
  - 400: Bad Request
  - 401: Unauthorized
  - 403: Forbidden
  - 404: Not Found
  - 422: Validation Error
  - 500: Internal Server Error
  - 503: Service Unavailable

Pagination:
  Query params:
    - page: Page number (default 1)
    - per_page: Items per page (default 15, max 100)
    - sort: Sort field (default id)
    - order: Sort order (asc/desc, default asc)
  
  Response:
    {
      "data": [...],
      "meta": {
        "current_page": 1,
        "from": 1,
        "last_page": 10,
        "per_page": 15,
        "to": 15,
        "total": 150
      },
      "links": {
        "first": "?page=1",
        "last": "?page=10",
        "prev": null,
        "next": "?page=2"
      }
    }

Filtering:
  Query params: filter[field]=value
  Example: /api/realizations?filter[status]=APPROVED&filter[month]=11

Search:
  Query param: search=keyword
  Example: /api/budget-items?search=kertas

Include Relations:
  Query param: include=relation1,relation2
  Example: /api/sub-activities/123?include=budget_items,monthly_plans
```

### 3.2 Authentication

```yaml
Method: JWT (JSON Web Token)

Login Flow:
  1. POST /api/auth/login
     Request: { nip, password }
     Response: { token, refresh_token, expires_in, user }
  
  2. Client stores token (localStorage or cookie)
  
  3. Client sends token in header:
     Authorization: Bearer {token}
  
  4. Token expires after 8 hours
  
  5. Refresh token (before expiry):
     POST /api/auth/refresh
     Request: { refresh_token }
     Response: { token, refresh_token, expires_in }

Logout:
  POST /api/auth/logout
  - Invalidates token
  - Clears server-side session

Password Reset:
  1. POST /api/auth/forgot-password
     Request: { email }
     Response: { message }
     - Sends reset link to email
  
  2. POST /api/auth/reset-password
     Request: { token, password, password_confirmation }
     Response: { message }
```

### 3.3 API Endpoints (Complete List)

```
Authentication
  POST   /api/auth/login
  POST   /api/auth/logout
  POST   /api/auth/refresh
  GET    /api/auth/me
  POST   /api/auth/forgot-password
  POST   /api/auth/reset-password

Master Data - Programs
  GET    /api/programs
  POST   /api/programs
  GET    /api/programs/{id}
  PUT    /api/programs/{id}
  DELETE /api/programs/{id}

Master Data - Activities
  GET    /api/activities
  POST   /api/activities
  GET    /api/activities/{id}
  PUT    /api/activities/{id}
  DELETE /api/activities/{id}
  GET    /api/programs/{program_id}/activities

Master Data - Sub-Activities
  GET    /api/sub-activities
  POST   /api/sub-activities
  GET    /api/sub-activities/{id}
  PUT    /api/sub-activities/{id}
  DELETE /api/sub-activities/{id}
  GET    /api/activities/{activity_id}/sub-activities

Master Data - Account Codes
  GET    /api/account-codes
  POST   /api/account-codes
  GET    /api/account-codes/{code}
  PUT    /api/account-codes/{code}
  DELETE /api/account-codes/{code}
  GET    /api/account-codes/search?q={keyword}
  POST   /api/account-codes/import (Excel file)

Planning - DPA
  GET    /api/sub-activities/{id}/dpa
  POST   /api/sub-activities/{id}/dpa
  PUT    /api/sub-activities/{id}/dpa
  POST   /api/dpa/{id}/submit-approval
  POST   /api/dpa/{id}/approve
  POST   /api/dpa/{id}/reject

Planning - PLGK
  GET    /api/sub-activities/{id}/plgk
  POST   /api/sub-activities/{id}/plgk/generate
  PUT    /api/sub-activities/{id}/plgk
  GET    /api/plgk/{id}/monthly-plans

Planning - ROK OP
  GET    /api/sub-activities/{id}/rok-op
  POST   /api/sub-activities/{id}/rok-op
  PUT    /api/rok-op/{id}
  DELETE /api/rok-op/{id}

Realization
  GET    /api/realizations
  POST   /api/realizations
  GET    /api/realizations/{id}
  PUT    /api/realizations/{id}
  DELETE /api/realizations/{id}
  POST   /api/realizations/{id}/submit
  POST   /api/realizations/{id}/verify
  POST   /api/realizations/{id}/approve
  POST   /api/realizations/{id}/reject
  POST   /api/realizations/bulk-upload (Excel file)
  GET    /api/realizations/pending-verification
  GET    /api/realizations/pending-approval

Realization Documents
  POST   /api/realizations/{id}/documents (multipart/form-data)
  GET    /api/realizations/{id}/documents
  DELETE /api/realization-documents/{id}
  GET    /api/realization-documents/{id}/download

Dashboard
  GET    /api/dashboard/summary
  GET    /api/dashboard/monthly-trend
  GET    /api/dashboard/category-breakdown
  GET    /api/dashboard/top-items
  GET    /api/dashboard/alerts
  GET    /api/dashboard/pending-approvals

Reporting
  GET    /api/reports/monthly
  GET    /api/reports/quarterly
  GET    /api/reports/annual
  POST   /api/reports/custom
  POST   /api/reports/export/pdf
  POST   /api/reports/export/excel
  GET    /api/reports/templates
  POST   /api/reports/templates
  DELETE /api/reports/templates/{id}

Notifications
  GET    /api/notifications
  POST   /api/notifications/{id}/read
  POST   /api/notifications/mark-all-read
  DELETE /api/notifications/{id}

Users
  GET    /api/users
  POST   /api/users
  GET    /api/users/{id}
  PUT    /api/users/{id}
  DELETE /api/users/{id}
  POST   /api/users/{id}/reset-password
  PUT    /api/users/{id}/change-password

Audit Logs
  GET    /api/audit-logs
  GET    /api/audit-logs/{id}

System
  GET    /api/health
  GET    /api/version
```

---

## 4. DATABASE DESIGN

### 4.1 Indexing Strategy

```sql
-- Primary keys (automatic indexes)
-- Foreign keys
CREATE INDEX idx_activities_program_id ON activities(program_id);
CREATE INDEX idx_sub_activities_activity_id ON sub_activities(activity_id);
CREATE INDEX idx_budget_items_sub_activity_id ON budget_items(sub_activity_id);
CREATE INDEX idx_monthly_plans_budget_item_id ON monthly_plans(budget_item_id);
CREATE INDEX idx_monthly_realizations_budget_item_id ON monthly_realizations(budget_item_id);

-- Composite indexes for common queries
CREATE INDEX idx_monthly_plans_item_month_year ON monthly_plans(budget_item_id, month, year);
CREATE INDEX idx_monthly_realizations_item_month_year ON monthly_realizations(budget_item_id, month, year);
CREATE INDEX idx_monthly_realizations_status ON monthly_realizations(status);
CREATE INDEX idx_monthly_realizations_input_by ON monthly_realizations(input_by);

-- Full-text search
CREATE INDEX idx_budget_items_description_gin ON budget_items USING gin(to_tsvector('indonesian', description));
CREATE INDEX idx_account_codes_description_gin ON account_codes USING gin(to_tsvector('indonesian', description));

-- Partial indexes (for common filtered queries)
CREATE INDEX idx_users_active ON users(id) WHERE is_active = true;
CREATE INDEX idx_notifications_unread ON notifications(user_id, created_at) WHERE is_read = false;
```

### 4.2 Partitioning Strategy

```sql
-- Partition audit_logs by month (for better performance)
CREATE TABLE audit_logs (
    id SERIAL,
    user_id INT,
    action VARCHAR(100),
    table_name VARCHAR(50),
    record_id INT,
    old_values JSONB,
    new_values JSONB,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT NOW()
) PARTITION BY RANGE (created_at);

-- Create partitions for each month
CREATE TABLE audit_logs_2026_01 PARTITION OF audit_logs
    FOR VALUES FROM ('2026-01-01') TO ('2026-02-01');

CREATE TABLE audit_logs_2026_02 PARTITION OF audit_logs
    FOR VALUES FROM ('2026-02-01') TO ('2026-03-01');

-- ... and so on

-- Auto-create partitions using pg_partman extension (optional)
```

### 4.3 Backup & Recovery

```bash
# Daily full backup
pg_dump -U postgres -h localhost -d sipera -F c -f /backup/sipera_$(date +%Y%m%d).dump

# Restore from backup
pg_restore -U postgres -h localhost -d sipera -c /backup/sipera_20260108.dump

# Point-in-time recovery (PITR)
# Enable WAL archiving in postgresql.conf:
# wal_level = replica
# archive_mode = on
# archive_command = 'cp %p /archive/%f'

# Restore to specific timestamp
pg_restore -U postgres -h localhost -d sipera -t "2026-01-08 14:30:00" /backup/base_backup.tar
```

---

## 5. SECURITY IMPLEMENTATION

### 5.1 Input Validation

```php
// Laravel Validation Rules
class StoreRealizationRequest extends FormRequest
{
    public function rules()
    {
        return [
            'budget_item_id' => 'required|exists:budget_items,id',
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer|min:2020|max:2100',
            'realization_volume' => 'required|numeric|min:0',
            'realization_unit_price' => 'required|numeric|min:0',
            'notes' => 'nullable|string|max:1000',
            'documents.*' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120' // 5MB
        ];
    }
    
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // Custom validation: check if realization > 110% of plan
            $plan = MonthlyPlan::where([
                'budget_item_id' => $this->budget_item_id,
                'month' => $this->month,
                'year' => $this->year
            ])->first();
            
            if ($plan && $this->realization_volume > $plan->planned_volume * 1.1) {
                $validator->warnings()->add(
                    'realization_volume',
                    'Realisasi melebihi 110% dari rencana'
                );
            }
        });
    }
}
```

### 5.2 SQL Injection Prevention

```php
// SAFE: Use Eloquent ORM (automatic parameterization)
$users = User::where('nip', $nip)->first();

// SAFE: Use query builder with bindings
$users = DB::table('users')
    ->where('nip', '=', $nip)
    ->first();

// SAFE: Use raw query with bindings
$users = DB::select('SELECT * FROM users WHERE nip = ?', [$nip]);

// UNSAFE: Never do this!
// $users = DB::select("SELECT * FROM users WHERE nip = '$nip'");
```

### 5.3 XSS Prevention

```vue
<!-- Vue.js automatically escapes text interpolation -->
<template>
  <div>
    <!-- SAFE: Automatic escaping -->
    <p>{{ userInput }}</p>
    
    <!-- UNSAFE: Raw HTML (only use if necessary and sanitized) -->
    <div v-html="sanitizedHtml"></div>
  </div>
</template>

<script setup>
import DOMPurify from 'dompurify';

const userInput = ref('User input with <script>alert("XSS")</script>');

// Sanitize HTML if you must use v-html
const sanitizedHtml = computed(() => {
  return DOMPurify.sanitize(userInput.value);
});
</script>
```

### 5.4 CSRF Protection

```php
// Laravel automatically handles CSRF for same-origin requests

// In blade template (if using)
<form method="POST" action="/api/realizations">
    @csrf
    <!-- form fields -->
</form>

// In Vue.js + Axios (automatic from cookie)
// Axios automatically reads XSRF-TOKEN cookie and sends X-XSRF-TOKEN header
axios.post('/api/realizations', data);

// For API-only, use Sanctum CSRF protection
// In config/sanctum.php:
'middleware' => [
    'encrypt_cookies',
    \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
    \Illuminate\Session\Middleware\StartSession::class,
    'verified',
    \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
],
```

### 5.5 File Upload Security

```php
class FileUploadService
{
    public function uploadDocument($file, $type)
    {
        // 1. Validate file extension
        $allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png'];
        if (!in_array($file->extension(), $allowedExtensions)) {
            throw new ValidationException('Invalid file type');
        }
        
        // 2. Validate MIME type (magic bytes)
        $allowedMimeTypes = ['application/pdf', 'image/jpeg', 'image/png'];
        if (!in_array($file->getMimeType(), $allowedMimeTypes)) {
            throw new ValidationException('Invalid MIME type');
        }
        
        // 3. Validate file size (max 5MB)
        if ($file->getSize() > 5 * 1024 * 1024) {
            throw new ValidationException('File too large');
        }
        
        // 4. Generate random filename (prevent path traversal)
        $filename = Str::random(40) . '.' . $file->extension();
        
        // 5. Store in private directory (not public)
        $path = $file->storeAs('documents/' . $type, $filename, 'private');
        
        // 6. Scan for malware (optional, using ClamAV)
        // $this->scanFile(storage_path('app/private/' . $path));
        
        return $path;
    }
    
    protected function scanFile($filepath)
    {
        $clam = new \Xenolope\Quahog\Client(
            new \Socket\Raw\Factory(),
            'unix:///var/run/clamav/clamd.ctl'
        );
        
        $result = $clam->scanFile($filepath);
        
        if ($result['status'] === 'FOUND') {
            unlink($filepath);
            throw new SecurityException('Malware detected');
        }
    }
}
```

### 5.6 Rate Limiting

```php
// In routes/api.php
Route::middleware(['auth:sanctum', 'throttle:60,1'])->group(function () {
    // 60 requests per minute
    Route::apiResource('realizations', RealizationController::class);
});

Route::middleware(['throttle:10,1'])->group(function () {
    // 10 requests per minute (stricter for sensitive operations)
    Route::post('/realizations/{id}/approve', [RealizationController::class, 'approve']);
});

// Custom rate limiter in app/Providers/RouteServiceProvider.php
RateLimiter::for('api', function (Request $request) {
    return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
});

RateLimiter::for('uploads', function (Request $request) {
    return Limit::perMinute(10)->by($request->user()->id);
});
```

---

## 6. PERFORMANCE OPTIMIZATION

### 6.1 Database Optimization

```php
// Use eager loading to prevent N+1 query problem
// BAD: N+1 queries
$subActivities = SubActivity::all();
foreach ($subActivities as $subActivity) {
    echo $subActivity->activity->name; // New query for each iteration
}

// GOOD: 2 queries only
$subActivities = SubActivity::with('activity')->get();
foreach ($subActivities as $subActivity) {
    echo $subActivity->activity->name;
}

// Use select() to load only necessary columns
$users = User::select('id', 'name', 'email')->get(); // Don't load password_hash, etc.

// Use chunk() for large datasets
BudgetItem::chunk(100, function ($items) {
    foreach ($items as $item) {
        // Process item
    }
});

// Use database transactions for multiple related operations
DB::transaction(function () {
    $realization = MonthlyRealization::create($data);
    $realization->documents()->createMany($documents);
    $realization->notify();
});
```

### 6.2 Caching Strategy

```php
// Cache expensive queries
$programs = Cache::remember('programs.all', 3600, function () {
    return Program::with('activities')->get();
});

// Cache user permissions
$permissions = Cache::remember("user.{$userId}.permissions", 3600, function () use ($userId) {
    return User::find($userId)->getAllPermissions();
});

// Cache dashboard summary
$summary = Cache::remember("dashboard.{$userId}.summary", 300, function () use ($userId) {
    return DashboardService::getSummary($userId);
});

// Invalidate cache when data changes
class SubActivity extends Model
{
    protected static function booted()
    {
        static::saved(function ($subActivity) {
            Cache::forget("programs.all");
            Cache::forget("activities.{$subActivity->activity_id}");
        });
    }
}

// Cache API responses with ETags
Route::middleware('cache.headers:public;max_age=3600;etag')->group(function () {
    Route::get('/api/account-codes', [AccountCodeController::class, 'index']);
});
```

### 6.3 Frontend Optimization

```javascript
// Code splitting (lazy loading routes)
const routes = [
  {
    path: '/',
    component: () => import('./views/Dashboard.vue')
  },
  {
    path: '/realizations',
    component: () => import('./views/Realization/Index.vue')
  }
];

// Lazy load heavy components
const HeavyChart = defineAsyncComponent(() =>
  import('./components/HeavyChart.vue')
);

// Debounce search input
import { debounce } from 'lodash-es';

const search = ref('');
const searchResults = ref([]);

const performSearch = debounce(async (query) => {
  if (query.length < 3) return;
  searchResults.value = await api.search(query);
}, 300);

watch(search, performSearch);

// Virtual scrolling for large lists
import { VirtualScroller } from 'primevue/virtualscroller';

<VirtualScroller :items="largeDataset" :itemSize="50">
  <template v-slot:item="{ item }">
    <div>{{ item.name }}</div>
  </template>
</VirtualScroller>

// Image lazy loading
<img v-lazy="imageUrl" alt="Document">

// Memoize expensive computations
import { computed } from 'vue';

const expensiveComputed = computed(() => {
  return heavyCalculation(props.data);
});
```

### 6.4 API Optimization

```php
// Use API Resources to transform data efficiently
class RealizationResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'month' => $this->month,
            'year' => $this->year,
            'volume' => $this->realization_volume,
            'amount' => $this->realization_amount,
            'status' => $this->status,
            'budget_item' => new BudgetItemResource($this->whenLoaded('budgetItem')),
            'documents' => DocumentResource::collection($this->whenLoaded('documents'))
        ];
    }
}

// Paginate results
public function index(Request $request)
{
    $realizations = MonthlyRealization::query()
        ->with(['budgetItem', 'documents'])
        ->where('month', $request->month)
        ->paginate(15);
    
    return RealizationResource::collection($realizations);
}

// Use Conditional Queries
$query = MonthlyRealization::query();

if ($request->has('status')) {
    $query->where('status', $request->status);
}

if ($request->has('category')) {
    $query->whereHas('budgetItem.subActivity', function ($q) use ($request) {
        $q->where('category', $request->category);
    });
}

return $query->paginate();
```

---

## 7. TESTING STRATEGY

### 7.1 Backend Testing

```php
// Unit Test Example
namespace Tests\Unit;

use Tests\TestCase;
use App\Services\PlgkService;

class PlgkServiceTest extends TestCase
{
    public function test_generate_plgk_with_equal_distribution()
    {
        $dpa = DPA::factory()->create(['total_amount' => 1200000]);
        $service = new PlgkService();
        
        $plgk = $service->generate($dpa, 'equal');
        
        $this->assertCount(12, $plgk);
        $this->assertEquals(100000, $plgk[0]['planned_amount']);
    }
}

// Feature Test Example
namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;

class RealizationTest extends TestCase
{
    public function test_user_can_create_realization()
    {
        $user = User::factory()->create(['role' => 'TIM_PELAKSANA']);
        
        $response = $this->actingAs($user)->postJson('/api/realizations', [
            'budget_item_id' => 1,
            'month' => 11,
            'year' => 2026,
            'realization_volume' => 10,
            'realization_unit_price' => 34000
        ]);
        
        $response->assertStatus(201)
                 ->assertJson(['success' => true]);
        
        $this->assertDatabaseHas('monthly_realizations', [
            'budget_item_id' => 1,
            'month' => 11
        ]);
    }
    
    public function test_unauthorized_user_cannot_create_realization()
    {
        $user = User::factory()->create(['role' => 'VIEWER']);
        
        $response = $this->actingAs($user)->postJson('/api/realizations', [
            'budget_item_id' => 1,
            'month' => 11,
            'year' => 2026
        ]);
        
        $response->assertStatus(403);
    }
}
```

### 7.2 Frontend Testing

```javascript
// Component Unit Test (Vitest)
import { mount } from '@vue/test-utils';
import { describe, it, expect } from 'vitest';
import RealizationForm from '@/components/RealizationForm.vue';

describe('RealizationForm', () => {
  it('calculates total amount correctly', async () => {
    const wrapper = mount(RealizationForm);
    
    await wrapper.find('[data-test="volume"]').setValue(10);
    await wrapper.find('[data-test="price"]').setValue(34000);
    
    expect(wrapper.find('[data-test="total"]').text()).toBe('340,000');
  });
  
  it('validates required fields', async () => {
    const wrapper = mount(RealizationForm);
    
    await wrapper.find('[data-test="submit"]').trigger('click');
    
    expect(wrapper.find('.error-message').exists()).toBe(true);
  });
});

// E2E Test (Cypress)
describe('Realization Input Flow', () => {
  beforeEach(() => {
    cy.login('pelaksana', 'password');
  });
  
  it('should input realization successfully', () => {
    cy.visit('/realizations/create');
    
    cy.get('[data-test="month"]').select('November 2026');
    cy.get('[data-test="item"]').select('Kertas & Cover');
    cy.get('[data-test="volume"]').type('10');
    cy.get('[data-test="price"]').type('34000');
    
    cy.get('[data-test="file-upload"]').attachFile('receipt.pdf');
    
    cy.get('[data-test="submit"]').click();
    
    cy.contains('Realisasi berhasil disubmit').should('be.visible');
    cy.url().should('include', '/realizations');
  });
});
```

### 7.3 Performance Testing

```javascript
// k6 Load Test Script
import http from 'k6/http';
import { check, sleep } from 'k6';

export let options = {
  stages: [
    { duration: '2m', target: 10 }, // Ramp up to 10 users
    { duration: '5m', target: 10 }, // Stay at 10 users
    { duration: '2m', target: 50 }, // Ramp up to 50 users
    { duration: '5m', target: 50 }, // Stay at 50 users
    { duration: '2m', target: 0 },  // Ramp down to 0 users
  ],
  thresholds: {
    http_req_duration: ['p(95)<500'], // 95% of requests should be below 500ms
    http_req_failed: ['rate<0.01'],   // Error rate should be < 1%
  },
};

export default function () {
  let token = 'YOUR_JWT_TOKEN';
  
  let params = {
    headers: {
      'Authorization': `Bearer ${token}`,
      'Content-Type': 'application/json',
    },
  };
  
  // Test dashboard endpoint
  let dashboardRes = http.get('https://sipera.baliprov.go.id/api/dashboard/summary', params);
  check(dashboardRes, {
    'dashboard status is 200': (r) => r.status === 200,
    'dashboard response time < 500ms': (r) => r.timings.duration < 500,
  });
  
  sleep(1);
  
  // Test realizations list
  let realizationsRes = http.get('https://sipera.baliprov.go.id/api/realizations?page=1', params);
  check(realizationsRes, {
    'realizations status is 200': (r) => r.status === 200,
  });
  
  sleep(1);
}
```

---

## 8. DEPLOYMENT GUIDE

### 8.1 Server Setup (Ubuntu 22.04)

```bash
# Update system
sudo apt update && sudo apt upgrade -y

# Install Nginx
sudo apt install nginx -y

# Install PHP 8.2
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update
sudo apt install php8.2-fpm php8.2-cli php8.2-pgsql php8.2-redis php8.2-mbstring php8.2-xml php8.2-curl php8.2-zip php8.2-gd php8.2-intl -y

# Install PostgreSQL 16
sudo sh -c 'echo "deb http://apt.postgresql.org/pub/repos/apt $(lsb_release -cs)-pgdg main" > /etc/apt/sources.list.d/pgdg.list'
wget --quiet -O - https://www.postgresql.org/media/keys/ACCC4CF8.asc | sudo apt-key add -
sudo apt update
sudo apt install postgresql-16 -y

# Install Redis
sudo apt install redis-server -y

# Install Composer
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
php composer-setup.php
sudo mv composer.phar /usr/local/bin/composer

# Install Node.js 20
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs

# Install PM2 (optional, for Node.js processes)
sudo npm install -g pm2

# Configure firewall
sudo ufw allow 'Nginx Full'
sudo ufw allow OpenSSH
sudo ufw enable
```

### 8.2 Nginx Configuration

```nginx
# /etc/nginx/sites-available/sipera

server {
    listen 80;
    listen [::]:80;
    server_name sipera.baliprov.go.id;
    
    # Redirect HTTP to HTTPS
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name sipera.baliprov.go.id;
    
    root /var/www/sipera/public;
    index index.php index.html;
    
    # SSL Configuration
    ssl_certificate /etc/letsencrypt/live/sipera.baliprov.go.id/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/sipera.baliprov.go.id/privkey.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_prefer_server_ciphers on;
    ssl_ciphers ECDHE-RSA-AES256-GCM-SHA512:DHE-RSA-AES256-GCM-SHA512:ECDHE-RSA-AES256-GCM-SHA384:DHE-RSA-AES256-GCM-SHA384;
    
    # Security Headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "no-referrer-when-downgrade" always;
    add_header Content-Security-Policy "default-src 'self' https: data: 'unsafe-inline' 'unsafe-eval';" always;
    
    # Rate limiting
    limit_req_zone $binary_remote_addr zone=api:10m rate=60r/m;
    
    # Gzip compression
    gzip on;
    gzip_vary on;
    gzip_min_length 1024;
    gzip_types text/plain text/css text/xml text/javascript application/x-javascript application/xml+rss application/json;
    
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    
    location /api {
        limit_req zone=api burst=10 nodelay;
        try_files $uri $uri/ /index.php?$query_string;
    }
    
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }
    
    location /storage/private {
        internal;
        alias /var/www/sipera/storage/app/private;
    }
    
    location ~ /\.(?!well-known).* {
        deny all;
    }
    
    # Cache static assets
    location ~* \.(jpg|jpeg|png|gif|ico|css|js|woff2)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }
}
```

### 8.3 Deployment Script

```bash
#!/bin/bash
# deploy.sh

set -e

echo "🚀 Starting deployment..."

# Navigate to project directory
cd /var/www/sipera

# Backup database
echo "📦 Backing up database..."
pg_dump -U sipera_user -h localhost sipera > /backup/sipera_$(date +%Y%m%d_%H%M%S).sql

# Pull latest code
echo "📥 Pulling latest code..."
git pull origin main

# Backend deployment
echo "🔧 Deploying backend..."
cd backend
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan queue:restart

# Frontend deployment
echo "🎨 Deploying frontend..."
cd ../frontend
npm install --production
npm run build
rm -rf /var/www/sipera/public/assets
cp -r dist/* /var/www/sipera/public/

# Set permissions
echo "🔐 Setting permissions..."
cd /var/www/sipera
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache

# Reload services
echo "🔄 Reloading services..."
sudo systemctl reload nginx
sudo systemctl reload php8.2-fpm

# Clear cache
echo "🧹 Clearing cache..."
php artisan cache:clear
redis-cli FLUSHDB

echo "✅ Deployment completed successfully!"
echo "🌐 Visit: https://sipera.baliprov.go.id"
```

### 8.4 Environment Variables

```bash
# Backend .env
APP_NAME=SIPERA
APP_ENV=production
APP_KEY=base64:GENERATED_KEY
APP_DEBUG=false
APP_URL=https://sipera.baliprov.go.id

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=sipera
DB_USERNAME=sipera_user
DB_PASSWORD=SECURE_PASSWORD

CACHE_DRIVER=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=noreply@baliprov.go.id
MAIL_PASSWORD=MAIL_PASSWORD
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@baliprov.go.id
MAIL_FROM_NAME="${APP_NAME}"

# Frontend .env
VITE_API_BASE_URL=https://sipera.baliprov.go.id/api
VITE_APP_NAME=SIPERA
```

---

## 9. MONITORING & LOGGING

### 9.1 Application Monitoring

```php
// Sentry Error Tracking
// config/sentry.php
'dsn' => env('SENTRY_LARAVEL_DSN'),
'environment' => env('APP_ENV'),
'release' => env('APP_VERSION'),

// Capture exceptions
try {
    // risky code
} catch (Exception $e) {
    \Sentry\captureException($e);
    throw $e;
}

// Laravel Telescope (Development)
// View at: https://sipera.baliprov.go.id/telescope
php artisan telescope:install
```

### 9.2 Log Configuration

```php
// config/logging.php
'channels' => [
    'stack' => [
        'driver' => 'stack',
        'channels' => ['daily', 'slack'],
    ],
    
    'daily' => [
        'driver' => 'daily',
        'path' => storage_path('logs/laravel.log'),
        'level' => env('LOG_LEVEL', 'debug'),
        'days' => 14,
    ],
    
    'slack' => [
        'driver' => 'slack',
        'url' => env('LOG_SLACK_WEBHOOK_URL'),
        'username' => 'SIPERA Bot',
        'emoji' => ':boom:',
        'level' => 'critical',
    ],
],

// Usage
Log::info('User logged in', ['user_id' => $user->id]);
Log::warning('High realization deviation', ['item_id' => $item->id]);
Log::error('Failed to generate report', ['error' => $e->getMessage()]);
```

### 9.3 Performance Monitoring

```bash
# Install New Relic PHP Agent (optional)
wget -O - https://download.newrelic.com/548C16BF.gpg | sudo apt-key add -
echo "deb http://apt.newrelic.com/debian/ newrelic non-free" | sudo tee /etc/apt/sources.list.d/newrelic.list
sudo apt update
sudo apt install newrelic-php5
sudo newrelic-install install

# Configure New Relic
sudo vi /etc/php/8.2/fpm/conf.d/newrelic.ini
# Set:
# newrelic.appname = "SIPERA Production"
# newrelic.license = "YOUR_LICENSE_KEY"

sudo systemctl restart php8.2-fpm
```

---

**Document Status:** Complete  
**Last Updated:** 8 Januari 2026  
**Version:** 1.0

---

_This technical specification serves as the authoritative guide for implementing SIPERA system._
