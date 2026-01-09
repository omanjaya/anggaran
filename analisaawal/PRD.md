# PRODUCT REQUIREMENTS DOCUMENT (PRD)
## SIPERA - Sistem Informasi Perencanaan dan Realisasi Anggaran

**Version:** 1.0  
**Date:** 8 Januari 2026  
**Status:** Draft  
**Owner:** Dinas Kominfo Provinsi Bali

---

## 1. EXECUTIVE SUMMARY

### 1.1 Project Background
Dinas Kominfo Provinsi Bali saat ini menggunakan multiple Excel files untuk monitoring realisasi anggaran. Proses manual ini memiliki beberapa kelemahan:
- Rawan human error
- Time-consuming (4-6 jam untuk generate laporan bulanan)
- Sulit kolaborasi real-time
- Tidak ada audit trail
- Reporting manual dan repetitif

### 1.2 Solution
Mengembangkan web-based application yang mengotomasi seluruh proses dari perencanaan hingga pelaporan, dengan fitur real-time collaboration, approval workflow, dan automated reporting.

### 1.3 Success Criteria
- Report generation time < 5 menit
- Data entry error rate < 1%
- User adoption > 80% dalam 3 bulan
- Approval cycle time < 2 hari
- System uptime > 99.5%

---

## 2. USER PERSONAS

### 2.1 Kadis (Kepala Dinas)
**Profile:**
- Usia: 45-55 tahun
- Tech-savvy: Medium
- Main device: Desktop + Mobile

**Goals:**
- Monitor penyerapan anggaran real-time
- Approve realisasi dengan cepat
- Get executive summary untuk rapat

**Pain Points:**
- Terlalu banyak approval manual
- Informasi tidak real-time
- Susah tracking deviasi

**Needs:**
- Executive dashboard dengan visual yang jelas
- Mobile-friendly untuk approval on-the-go
- Alert otomatis untuk deviasi besar

---

### 2.2 Tim Perencanaan
**Profile:**
- Usia: 30-40 tahun
- Tech-savvy: High
- Main device: Desktop

**Goals:**
- Buat perencanaan anggaran yang akurat
- Generate PLGK dan ROK OP dengan efisien
- Monitor progress vs plan

**Pain Points:**
- Excel formula sering error
- Breakdown manual per bulan repetitif
- Revisi anggaran susah tracking

**Needs:**
- Form wizard untuk input DPA
- Auto-generate PLGK dari DPA
- Template untuk ROK OP
- Version control untuk revisi

---

### 2.3 Tim Pelaksana
**Profile:**
- Usia: 25-40 tahun
- Tech-savvy: Medium-High
- Main device: Desktop + Tablet

**Goals:**
- Input realisasi dengan cepat
- Upload bukti dengan mudah
- Track status approval

**Pain Points:**
- Form Excel banyak yang harus diisi manual
- Upload bukti via email susah tracking
- Tidak tahu status approval

**Needs:**
- Form input yang simpel
- Drag-drop file upload
- Notification untuk approval status
- Mobile-friendly

---

### 2.4 Bendahara
**Profile:**
- Usia: 35-50 tahun
- Tech-savvy: Medium
- Main device: Desktop

**Goals:**
- Verify realisasi keuangan dengan akurat
- Cek kelengkapan dokumen pendukung
- Rekonsiliasi dengan SIPKD

**Pain Points:**
- Dokumen pendukung tidak lengkap
- Susah tracking yang sudah verified vs belum
- Harus buka banyak file Excel

**Needs:**
- Queue approval yang jelas
- Preview dokumen pendukung dalam 1 screen
- Checklist untuk verification
- Integration dengan SIPKD (future)

---

### 2.5 Monev (Monitoring & Evaluasi)
**Profile:**
- Usia: 30-45 tahun
- Tech-savvy: High
- Main device: Desktop

**Goals:**
- Generate laporan cepat dan akurat
- Analisis deviasi dan tren
- Presentasi ke Kadis/Stakeholder

**Pain Points:**
- Compile data dari banyak Excel manual
- Chart harus dibuat manual
- Format laporan berbeda-beda

**Needs:**
- One-click report generation
- Auto-generate charts
- Export ke multiple format (PDF, Excel, PPT)
- Custom report builder

---

## 3. FUNCTIONAL REQUIREMENTS

### 3.1 Authentication & Authorization

#### FR-AUTH-001: User Login
**Priority:** Must Have  
**User Story:** As a user, I want to login using my NIP and password so that I can access the system securely.

**Acceptance Criteria:**
- User can login with NIP (18 digits) and password
- Password must be minimum 8 characters with combination (letter + number + symbol)
- Failed login attempts are logged
- After 5 failed attempts, account is locked for 30 minutes
- User sees appropriate error message for invalid credentials
- Successful login redirects to dashboard

**Technical Notes:**
- Use JWT for token-based authentication
- Token expires in 8 hours
- Refresh token mechanism for extending session
- Implement rate limiting (5 attempts per 5 minutes)

---

#### FR-AUTH-002: Role-based Access Control
**Priority:** Must Have  
**User Story:** As an admin, I want to assign roles to users so that they only access features relevant to their job.

**Acceptance Criteria:**
- System supports 7 roles: Admin, Kadis, Tim Perencanaan, Tim Pelaksana, Bendahara, Monev, Viewer
- Each role has predefined permissions
- User can have multiple roles (e.g., Monev + Tim Pelaksana)
- Permissions can be customized per user
- Unauthorized access shows 403 error page

**Permission Matrix:**

| Feature | Admin | Kadis | Perencanaan | Pelaksana | Bendahara | Monev | Viewer |
|---------|-------|-------|-------------|-----------|-----------|-------|--------|
| View Dashboard | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Input DPA | ✅ | ❌ | ✅ | ❌ | ❌ | ❌ | ❌ |
| Input Realisasi | ✅ | ❌ | ❌ | ✅ | ❌ | ❌ | ❌ |
| Verify Realisasi | ✅ | ❌ | ❌ | ❌ | ✅ | ❌ | ❌ |
| Approve Realisasi | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |
| Generate Report | ✅ | ✅ | ✅ | ❌ | ❌ | ✅ | ✅ |
| User Management | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |

---

### 3.2 Master Data Management

#### FR-MASTER-001: Manage Program
**Priority:** Must Have  
**User Story:** As Tim Perencanaan, I want to create and manage programs so that I can organize budget planning.

**Acceptance Criteria:**
- Can create new program with: code, name, year, description
- Can edit existing program
- Can delete program (only if no related activities)
- Can search and filter programs
- Program code is unique per year

**Fields:**
- `code` (VARCHAR 20, required, unique per year)
- `name` (VARCHAR 255, required)
- `year` (INT, required, default current year)
- `description` (TEXT, optional)
- `status` (ENUM: active, archived)

---

#### FR-MASTER-002: Manage Kegiatan (Activities)
**Priority:** Must Have  
**User Story:** As Tim Perencanaan, I want to create activities under a program so that I can detail the budget structure.

**Acceptance Criteria:**
- Activity must belong to a program
- Can create, edit, delete activities
- Activity code is unique within program
- Can set location (all kabupaten or specific)

**Fields:**
- `program_id` (FK, required)
- `code` (VARCHAR 20, required)
- `name` (VARCHAR 255, required)
- `description` (TEXT, optional)
- `location` (VARCHAR 255, default "Semua Kabupaten/Kota")

---

#### FR-MASTER-003: Manage Sub-Kegiatan (Sub-Activities)
**Priority:** Must Have  
**User Story:** As Tim Perencanaan, I want to create sub-activities with categories so that I can organize different types of work.

**Acceptance Criteria:**
- Sub-activity belongs to an activity
- Has 5 predefined categories: ANALISIS, TATA_KELOLA, OPERASIONALISASI, LAYANAN, ELEK_NON_ELEK
- Can input budget for n-1, n, and n+1 years
- Category is unique within activity

**Fields:**
- `activity_id` (FK, required)
- `category` (ENUM, required)
- `name` (VARCHAR 255, required)
- `budget_prev_year` (DECIMAL 15,2, default 0)
- `budget_current_year` (DECIMAL 15,2, required)
- `budget_next_year` (DECIMAL 15,2, default 0)

---

#### FR-MASTER-004: Manage Account Codes (Kode Rekening)
**Priority:** Must Have  
**User Story:** As Admin, I want to manage account codes so that users can select from standardized chart of accounts.

**Acceptance Criteria:**
- Hierarchical structure (5 levels)
- Code format: 5.1.02.01.01.0025
- Can import from Excel
- Can search by code or description
- Inactive codes are hidden but not deleted

**Fields:**
- `code` (VARCHAR 50, unique, required)
- `description` (VARCHAR 500, required)
- `level` (INT 1-5, required)
- `parent_code` (VARCHAR 50, optional)
- `is_active` (BOOLEAN, default true)

---

### 3.3 Perencanaan (Planning) Module

#### FR-PLAN-001: Input DPA
**Priority:** Must Have  
**User Story:** As Tim Perencanaan, I want to input DPA so that I can document the approved budget plan.

**Acceptance Criteria:**
- Form wizard with 4 steps:
  1. Basic info (program, kegiatan, sub-kegiatan)
  2. Budget items (kode rekening, uraian, volume, harga)
  3. Indikator kinerja
  4. Review & submit
- Can save as draft at any step
- Can add multiple budget items
- Total calculated automatically
- Can upload supporting documents (PDF, max 10MB)
- Submit for approval to Kadis

**Validation Rules:**
- Total budget items must equal sub_activity.budget_current_year
- Volume must be > 0
- Unit price must be > 0
- Account code must be level 5 (most detailed)

---

#### FR-PLAN-002: Generate PLGK
**Priority:** Must Have  
**User Story:** As Tim Perencanaan, I want to generate PLGK from approved DPA so that I can plan monthly budget allocation.

**Acceptance Criteria:**
- Can only generate from approved DPA
- System creates 12 monthly plan records (Jan-Dec)
- User can choose allocation method:
  - Equal distribution (divide by 12)
  - Custom per month
  - Copy from previous year (if exists)
- Shows preview before save
- Can edit after generation
- Total monthly plans must equal DPA total

**Allocation Example (Equal):**
```
DPA Total: Rp 120.000.000
Per month: Rp 10.000.000 (120M / 12)

If item: Kertas & Cover = Rp 1.200.000
Monthly plan = Rp 100.000 (1.2M / 12)
```

---

#### FR-PLAN-003: Create ROK OP
**Priority:** Must Have  
**User Story:** As Tim Perencanaan, I want to create operational schedule so that the team knows when to execute activities.

**Acceptance Criteria:**
- Visual calendar interface (Gantt-chart style)
- Can drag-drop budget items to specific months
- Can assign PIC (user) per item
- Can set planned date
- Can add notes/description
- Export to PDF/Excel

**Fields per schedule:**
- `budget_item_id` (FK)
- `month` (1-12)
- `scheduled_date` (DATE)
- `pic_user_id` (FK to users)
- `description` (TEXT)
- `status` (ENUM: planned, in_progress, completed, cancelled)

---

### 3.4 Realisasi (Realization) Module

#### FR-REAL-001: Input Realisasi
**Priority:** Must Have  
**User Story:** As Tim Pelaksana, I want to input realization data so that I can report actual expenditure.

**Acceptance Criteria:**
- Select month and budget item
- System shows planned data (read-only reference)
- Input fields:
  - Realization volume
  - Actual unit price
  - Total (auto-calculated)
- Upload supporting documents:
  - Receipt
  - Invoice
  - Photo
  - Other (max 5 files, each max 5MB)
- File types: PDF, JPG, PNG, JPEG
- Save as draft OR submit for verification
- Can edit draft but not after submitted

**Validation:**
- Realization volume warning if > 110% of planned (allow override with reason)
- Unit price warning if deviation > ±10% from plan (allow override)
- At least 1 supporting document required for submit

---

#### FR-REAL-002: Bulk Upload Realisasi
**Priority:** Should Have  
**User Story:** As Tim Pelaksana, I want to upload multiple realization data via Excel so that I can input faster.

**Acceptance Criteria:**
- Download Excel template with format
- Upload filled Excel file
- System validates all rows
- Shows validation errors clearly
- Preview data before save
- Can fix errors and re-upload
- Success rate shown (e.g., 45/50 rows success)

**Template Columns:**
- Budget Item Code
- Month
- Realization Volume
- Unit Price
- Notes

---

#### FR-REAL-003: Verification by Bendahara
**Priority:** Must Have  
**User Story:** As Bendahara, I want to verify realization data so that I can ensure financial accuracy.

**Acceptance Criteria:**
- Queue of submitted realizations
- Can filter by: month, category, submitter
- Detail view shows:
  - Planned vs Realization comparison
  - Supporting documents (inline preview)
  - Verification checklist
- Actions: Approve OR Reject with reason
- Email notification to submitter
- Can add internal notes (not visible to submitter)

**Verification Checklist:**
- [ ] Document lengkap dan sesuai
- [ ] Harga satuan wajar
- [ ] Volume sesuai dengan kontrak/purchase order
- [ ] Perhitungan total benar

---

#### FR-REAL-004: Approval by Kadis
**Priority:** Must Have  
**User Story:** As Kadis, I want to approve verified realizations so that the data becomes official.

**Acceptance Criteria:**
- Queue of verified realizations
- Summary view with key metrics
- Batch approval (multiple items at once)
- Mobile-friendly interface
- Can add approval notes
- Email notification to all parties
- Approved data locked (cannot edit)

---

### 3.5 Monitoring & Dashboard

#### FR-DASH-001: Executive Dashboard (Kadis)
**Priority:** Must Have  
**User Story:** As Kadis, I want to see budget absorption summary so that I can make strategic decisions.

**Acceptance Criteria:**
- Shows:
  - Total budget vs realized (with percentage)
  - Monthly absorption trend (line chart)
  - Per-category absorption (bar chart)
  - Top 5 largest items
  - Alert count (deviations, pending approvals)
- Auto-refresh every 5 minutes
- Date range filter
- Drill-down capability (click chart → detail)
- Export dashboard to PDF

---

#### FR-DASH-002: Operational Dashboard (Tim Pelaksana)
**Priority:** Must Have  
**User Story:** As Tim Pelaksana, I want to see my tasks so that I know what needs to be done.

**Acceptance Criteria:**
- Shows:
  - Tasks for current month
  - Overdue tasks (highlighted in red)
  - Completed tasks (with checkmark)
  - Upcoming deadlines (next 7 days)
- Quick input form
- Notification badge count
- Calendar view of scheduled activities

---

#### FR-DASH-003: Deviation Alert System
**Priority:** Should Have  
**User Story:** As Monev, I want automatic alerts for deviations so that I can take corrective action.

**Acceptance Criteria:**
- Auto-generate alerts when:
  - Realization < 70% of plan (warning)
  - Realization > 110% of plan (over-budget)
  - Item not realized within planned month
  - Approaching deadline (H-7, H-3, H-day)
- Alert shown in dashboard
- Email notification to relevant users
- Can dismiss alert with reason
- Alert history logged

---

### 3.6 Reporting Module

#### FR-REPORT-001: Generate Laporan Bulanan
**Priority:** Must Have  
**User Story:** As Monev, I want to generate monthly report so that I can submit to stakeholders.

**Acceptance Criteria:**
- Select month and category (or all)
- Format sama dengan Excel existing
- Shows:
  - Header (SKPD, month, year)
  - Table rencana vs realisasi
  - Summary statistics
  - Signature area (Kadis)
- Export to PDF and Excel
- PDF includes cover page and table of contents
- Excel is editable for further analysis

**Report Structure:**
```
1. Cover Page
   - Logo Pemprov Bali
   - Title: Laporan Realisasi Fisik dan Keuangan
   - Month & Year
   - SKPD

2. Table of Contents

3. Executive Summary
   - Total budget
   - Absorption rate
   - Key highlights

4. Detailed Table
   - Kode rekening
   - Uraian
   - Rencana (volume, amount)
   - Realisasi (volume, unit price, amount)
   - Deviation (%)

5. Charts
   - Monthly trend
   - Per-category comparison

6. Signature
   - Kadis
   - Date
```

---

#### FR-REPORT-002: Custom Report Builder
**Priority:** Should Have  
**User Story:** As Monev, I want to build custom reports so that I can analyze specific data.

**Acceptance Criteria:**
- Step-by-step wizard:
  1. Select data source (category, month range)
  2. Choose columns to display
  3. Apply filters
  4. Select grouping
  5. Preview & export
- Can save report template for reuse
- Export to Excel, PDF, CSV
- Schedule automated email (daily, weekly, monthly)

---

#### FR-REPORT-003: Laporan Triwulanan
**Priority:** Must Have  
**User Story:** As Monev, I want to generate quarterly report so that I can present to leadership.

**Acceptance Criteria:**
- Aggregates 3 months data
- Shows trend analysis
- Includes:
  - Achievement rate vs target
  - Variance analysis
  - Recommendations
  - Action items (if deviation exists)
- Executive-friendly format (more visual, less table)

---

### 3.7 Notification System

#### FR-NOTIF-001: In-app Notification
**Priority:** Must Have  
**User Story:** As a user, I want to receive notifications so that I stay informed of important events.

**Acceptance Criteria:**
- Notification bell icon in header (with badge count)
- Dropdown list of recent notifications
- Types:
  - Approval request
  - Approval result (approved/rejected)
  - Deadline reminder
  - Deviation alert
  - System announcement
- Click notification navigates to relevant page
- Mark as read/unread
- Mark all as read button
- Notification retention: 30 days

---

#### FR-NOTIF-002: Email Notification
**Priority:** Must Have  
**User Story:** As a user, I want to receive email notifications so that I don't miss important updates.

**Acceptance Criteria:**
- Email sent for:
  - New approval request
  - Approval decision (approved/rejected with reason)
  - Deadline approaching (H-3, H-day)
  - Monthly report ready
- Email template consistent with Pemprov Bali branding
- Unsubscribe option in email footer
- User can configure email preferences in settings

---

#### FR-NOTIF-003: WhatsApp Notification (Future)
**Priority:** Nice to Have  
**User Story:** As Kadis, I want to receive WhatsApp notification for urgent approvals so that I can respond quickly.

**Acceptance Criteria:**
- Only for high-priority notifications:
  - Approval request for Kadis
  - Critical deviation alert
- Message format:
  ```
  [SIPERA] Approval Request
  Item: Belanja ATK
  Amount: Rp 1.500.000
  Submitted by: John Doe
  
  Approve: [link]
  View Detail: [link]
  ```
- Requires WhatsApp Business API integration

---

## 4. NON-FUNCTIONAL REQUIREMENTS

### 4.1 Performance
- Page load time < 2 seconds (on 4G connection)
- API response time < 500ms (95th percentile)
- Support 50 concurrent users
- Database query time < 100ms
- File upload time < 5 seconds for 5MB file

### 4.2 Scalability
- Handle up to 100,000 transactions per year
- Support up to 200 users
- Database size up to 100GB
- Horizontal scaling capability (add more app servers)

### 4.3 Reliability
- System uptime > 99.5% (max 3.6 hours downtime per month)
- Automated daily backup (retain 30 days)
- Disaster recovery plan (RTO < 4 hours, RPO < 1 hour)
- Graceful degradation (system still usable if cache/queue down)

### 4.4 Security
- HTTPS only (TLS 1.3)
- Password hashing (bcrypt, cost factor 12)
- SQL injection prevention (parameterized queries)
- XSS prevention (input sanitization, output encoding)
- CSRF protection (token-based)
- Rate limiting (100 requests per minute per IP)
- File upload validation (magic bytes check, not just extension)
- Audit log for all data modifications

### 4.5 Usability
- Responsive design (mobile, tablet, desktop)
- Accessibility WCAG 2.1 Level AA
- Support browsers: Chrome 90+, Firefox 88+, Safari 14+, Edge 90+
- Maximum 3 clicks to reach any feature
- Consistent UI patterns across modules
- Helpful error messages with actionable guidance

### 4.6 Maintainability
- Code coverage > 80% (unit tests)
- Automated deployment (CI/CD)
- Centralized logging (ELK stack or similar)
- Monitoring & alerting (Prometheus + Grafana)
- Documentation: API docs, database schema, deployment guide
- Code follows PSR-12 (PHP) and Airbnb style guide (JavaScript)

### 4.7 Compatibility
- Backend: PHP 8.2+, PostgreSQL 16+, Redis 7+
- Frontend: Modern browsers with ES6+ support
- Mobile: iOS 14+, Android 10+
- Integration: RESTful API for third-party integration

---

## 5. USER INTERFACE MOCKUPS

### 5.1 Login Page
```
┌─────────────────────────────────────────────────────────┐
│                                                         │
│              [Logo Pemprov Bali]                        │
│                                                         │
│                    SIPERA                               │
│        Sistem Informasi Perencanaan dan                │
│            Realisasi Anggaran                           │
│                                                         │
│  ┌───────────────────────────────────────────────┐     │
│  │  NIP (18 digit)                               │     │
│  │  [_________________________________]          │     │
│  │                                               │     │
│  │  Password                                     │     │
│  │  [_________________________________]          │     │
│  │                                [🔓 Show]       │     │
│  │                                               │     │
│  │  [ ] Ingat saya                               │     │
│  │                                               │     │
│  │  [         LOGIN         ]                    │     │
│  │                                               │     │
│  │  Lupa password?                               │     │
│  └───────────────────────────────────────────────┘     │
│                                                         │
│         © 2026 Dinas Kominfo Provinsi Bali             │
│                                                         │
└─────────────────────────────────────────────────────────┘
```

### 5.2 Dashboard Kadis
```
┌─────────────────────────────────────────────────────────────────┐
│ [☰] SIPERA        [🔔 5]  [👤 Kadis - I Made Oka]  [Logout]    │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  📊 DASHBOARD - PROGRAM PERSANDIAN 2026                        │
│                                                                 │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐           │
│  │ Total Budget│  │  Terserap   │  │    Sisa     │           │
│  │ 1,38 M      │  │  856 M      │  │  529 M      │           │
│  │             │  │  61.8%      │  │  38.2%      │           │
│  └─────────────┘  └─────────────┘  └─────────────┘           │
│                                                                 │
│  ┌───────────────────────────────────────────────────────┐     │
│  │  📈 PENYERAPAN BULANAN                                │     │
│  │                                                        │     │
│  │  [Line chart showing plan vs realization]             │     │
│  └───────────────────────────────────────────────────────┘     │
│                                                                 │
│  ┌─────────────────────────────┐  ┌────────────────────────┐  │
│  │ 📊 PER KATEGORI             │  │ 🔔 ALERTS & APPROVAL   │  │
│  │                              │  │                        │  │
│  │ LAYANAN      ████████ 66%  │  │ 🔴 Deviasi >20%: 3    │  │
│  │ ELEK         ███ 15%       │  │ 🟡 Pending: 5         │  │
│  │ ...                         │  │ 🟢 On Track: 12       │  │
│  └─────────────────────────────┘  └────────────────────────┘  │
│                                                                 │
│  [Lihat Detail] [Generate Laporan]                            │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

### 5.3 Input Realisasi Form
```
┌─────────────────────────────────────────────────────────────────┐
│ ← Kembali                                                       │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  INPUT REALISASI                                               │
│                                                                 │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │  Kategori: LAYANAN                                      │   │
│  │  Bulan: [November 2026         ▼]                      │   │
│  │  Item: [Belanja Kertas & Cover ▼]                      │   │
│  └─────────────────────────────────────────────────────────┘   │
│                                                                 │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │  RENCANA (Informasi)                                    │   │
│  │  Volume: 10 rim                                         │   │
│  │  Harga Satuan: Rp 33.940                                │   │
│  │  Total: Rp 339.400                                      │   │
│  └─────────────────────────────────────────────────────────┘   │
│                                                                 │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │  REALISASI (Input)                                      │   │
│  │                                                          │   │
│  │  Volume Realisasi: [____] rim                          │   │
│  │  Harga Satuan: [Rp __________]                         │   │
│  │  Total: Rp 0 (auto-calculated)                          │   │
│  │                                                          │   │
│  │  Catatan (optional):                                    │   │
│  │  [_________________________________]                    │   │
│  │                                                          │   │
│  │  Upload Bukti: (min 1 file)                            │   │
│  │  [📎 Drop files here or click to browse]               │   │
│  │                                                          │   │
│  │  Uploaded:                                              │   │
│  │  📄 Kwitansi.pdf (2.1 MB) [X]                          │   │
│  │  📷 Foto_Barang.jpg (1.8 MB) [X]                       │   │
│  │                                                          │   │
│  └─────────────────────────────────────────────────────────┘   │
│                                                                 │
│  [Simpan Draft]  [Submit untuk Verifikasi]                    │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

---

## 6. API ENDPOINTS SPECIFICATION

### 6.1 Authentication Endpoints

```
POST /api/auth/login
Request:
{
  "nip": "199001012020121001",
  "password": "password123"
}

Response (200):
{
  "success": true,
  "data": {
    "user": {
      "id": 1,
      "nip": "199001012020121001",
      "name": "I Made Oka",
      "email": "oka@baliprov.go.id",
      "role": "KADIS"
    },
    "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
    "refresh_token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
    "expires_in": 28800
  }
}

Response (401):
{
  "success": false,
  "message": "NIP atau password salah"
}
```

---

### 6.2 Dashboard Endpoints

```
GET /api/dashboard/summary
Headers:
  Authorization: Bearer {token}

Query Params:
  - start_date (optional): 2026-01-01
  - end_date (optional): 2026-11-30
  - category (optional): LAYANAN

Response (200):
{
  "success": true,
  "data": {
    "total_budget": 1385437875,
    "total_realized": 856000000,
    "absorption_rate": 61.8,
    "remaining": 529437875,
    "by_category": [
      {
        "category": "LAYANAN",
        "budget": 918886900,
        "realized": 600000000,
        "rate": 65.3
      },
      ...
    ],
    "monthly_trend": [
      {
        "month": 1,
        "planned": 100000000,
        "realized": 95000000
      },
      ...
    ],
    "alerts": {
      "critical": 3,
      "warning": 5,
      "info": 12
    },
    "pending_approvals": 5
  }
}
```

---

### 6.3 Realization Endpoints

```
POST /api/realizations
Headers:
  Authorization: Bearer {token}
  Content-Type: multipart/form-data

Request:
{
  "budget_item_id": 123,
  "month": 11,
  "year": 2026,
  "realization_volume": 10,
  "realization_unit_price": 34000,
  "notes": "Pembelian sesuai rencana",
  "documents": [File, File, ...] // max 5 files
}

Response (201):
{
  "success": true,
  "data": {
    "id": 456,
    "budget_item_id": 123,
    "month": 11,
    "year": 2026,
    "status": "DRAFT",
    "realization_volume": 10,
    "realization_unit_price": 34000,
    "realization_amount": 340000,
    "documents": [
      {
        "id": 789,
        "file_name": "kwitansi.pdf",
        "file_url": "/storage/documents/789_kwitansi.pdf",
        "file_size": 2100000
      }
    ],
    "created_at": "2026-11-05T10:30:00Z"
  },
  "message": "Realisasi berhasil disimpan sebagai draft"
}
```

---

## 7. DATABASE SCHEMA (ERD)

```sql
-- Core Tables

CREATE TABLE programs (
    id SERIAL PRIMARY KEY,
    code VARCHAR(20) UNIQUE NOT NULL,
    name VARCHAR(255) NOT NULL,
    year INT NOT NULL,
    description TEXT,
    status VARCHAR(20) DEFAULT 'active',
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE activities (
    id SERIAL PRIMARY KEY,
    program_id INT REFERENCES programs(id) ON DELETE CASCADE,
    code VARCHAR(20) NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    location VARCHAR(255) DEFAULT 'Semua Kabupaten/Kota',
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW(),
    UNIQUE(program_id, code)
);

CREATE TABLE sub_activities (
    id SERIAL PRIMARY KEY,
    activity_id INT REFERENCES activities(id) ON DELETE CASCADE,
    category VARCHAR(50) NOT NULL,
    name VARCHAR(255) NOT NULL,
    budget_prev_year DECIMAL(15,2) DEFAULT 0,
    budget_current_year DECIMAL(15,2) NOT NULL,
    budget_next_year DECIMAL(15,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW(),
    CONSTRAINT chk_category CHECK (category IN ('ANALISIS', 'TATA_KELOLA', 'OPERASIONALISASI', 'LAYANAN', 'ELEK_NON_ELEK')),
    UNIQUE(activity_id, category)
);

CREATE TABLE account_codes (
    id SERIAL PRIMARY KEY,
    code VARCHAR(50) UNIQUE NOT NULL,
    description VARCHAR(500) NOT NULL,
    level INT NOT NULL,
    parent_code VARCHAR(50),
    is_active BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE budget_items (
    id SERIAL PRIMARY KEY,
    sub_activity_id INT REFERENCES sub_activities(id) ON DELETE CASCADE,
    account_code VARCHAR(50) REFERENCES account_codes(code),
    description TEXT NOT NULL,
    unit VARCHAR(50) NOT NULL,
    unit_price DECIMAL(15,2) NOT NULL,
    total_volume DECIMAL(10,2) NOT NULL,
    total_amount DECIMAL(15,2) NOT NULL,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE monthly_plans (
    id SERIAL PRIMARY KEY,
    budget_item_id INT REFERENCES budget_items(id) ON DELETE CASCADE,
    month INT NOT NULL CHECK (month BETWEEN 1 AND 12),
    year INT NOT NULL,
    planned_volume DECIMAL(10,2) NOT NULL,
    planned_amount DECIMAL(15,2) NOT NULL,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW(),
    UNIQUE(budget_item_id, month, year)
);

CREATE TABLE monthly_realizations (
    id SERIAL PRIMARY KEY,
    budget_item_id INT REFERENCES budget_items(id) ON DELETE CASCADE,
    month INT NOT NULL CHECK (month BETWEEN 1 AND 12),
    year INT NOT NULL,
    realization_volume DECIMAL(10,2) NOT NULL,
    realization_unit_price DECIMAL(15,2) NOT NULL,
    realization_amount DECIMAL(15,2) NOT NULL,
    status VARCHAR(20) DEFAULT 'DRAFT',
    notes TEXT,
    input_by INT REFERENCES users(id),
    input_date TIMESTAMP DEFAULT NOW(),
    verified_by INT REFERENCES users(id),
    verified_date TIMESTAMP,
    approved_by INT REFERENCES users(id),
    approved_date TIMESTAMP,
    rejection_reason TEXT,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW(),
    CONSTRAINT chk_status CHECK (status IN ('DRAFT', 'SUBMITTED', 'VERIFIED', 'APPROVED', 'REJECTED')),
    UNIQUE(budget_item_id, month, year)
);

CREATE TABLE realization_documents (
    id SERIAL PRIMARY KEY,
    realization_id INT REFERENCES monthly_realizations(id) ON DELETE CASCADE,
    document_type VARCHAR(50) NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size INT NOT NULL,
    uploaded_by INT REFERENCES users(id),
    uploaded_at TIMESTAMP DEFAULT NOW(),
    CONSTRAINT chk_doc_type CHECK (document_type IN ('RECEIPT', 'INVOICE', 'PHOTO', 'OTHER'))
);

CREATE TABLE users (
    id SERIAL PRIMARY KEY,
    nip VARCHAR(20) UNIQUE NOT NULL,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    role VARCHAR(50) NOT NULL,
    sub_activity_id INT REFERENCES sub_activities(id),
    is_active BOOLEAN DEFAULT true,
    last_login TIMESTAMP,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW(),
    CONSTRAINT chk_role CHECK (role IN ('ADMIN', 'KADIS', 'TIM_PERENCANAAN', 'TIM_PELAKSANA', 'BENDAHARA', 'MONEV', 'VIEWER'))
);

CREATE TABLE notifications (
    id SERIAL PRIMARY KEY,
    user_id INT REFERENCES users(id) ON DELETE CASCADE,
    type VARCHAR(50) NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    link VARCHAR(500),
    is_read BOOLEAN DEFAULT false,
    created_at TIMESTAMP DEFAULT NOW(),
    read_at TIMESTAMP,
    CONSTRAINT chk_notif_type CHECK (type IN ('DEADLINE', 'APPROVAL_REQUEST', 'APPROVAL_RESULT', 'DEVIATION_ALERT', 'SYSTEM'))
);

CREATE TABLE audit_logs (
    id SERIAL PRIMARY KEY,
    user_id INT REFERENCES users(id),
    action VARCHAR(100) NOT NULL,
    table_name VARCHAR(50) NOT NULL,
    record_id INT,
    old_values JSONB,
    new_values JSONB,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT NOW()
);

-- Indexes for performance
CREATE INDEX idx_monthly_realizations_status ON monthly_realizations(status);
CREATE INDEX idx_monthly_realizations_month_year ON monthly_realizations(month, year);
CREATE INDEX idx_notifications_user_unread ON notifications(user_id, is_read);
CREATE INDEX idx_audit_logs_created_at ON audit_logs(created_at);
```

---

## 8. TESTING REQUIREMENTS

### 8.1 Unit Testing
- Coverage target: 80%
- Test all business logic in services/repositories
- Test validation rules
- Test calculation formulas
- Use PHPUnit (backend), Vitest (frontend)

### 8.2 Integration Testing
- Test API endpoints
- Test database transactions
- Test file upload/download
- Test email sending
- Test notification system

### 8.3 E2E Testing
- Test complete user workflows
- Test approval workflow end-to-end
- Test report generation
- Use Cypress or Playwright

### 8.4 Performance Testing
- Load testing with 50 concurrent users
- Stress testing with 100 concurrent users
- Database query optimization
- Use k6 or Apache JMeter

### 8.5 Security Testing
- Penetration testing
- SQL injection testing
- XSS testing
- Authentication/authorization testing
- File upload security testing

### 8.6 UAT (User Acceptance Testing)
- 5 users per role (total 35 users)
- 2 weeks testing period
- Test scenarios covering all features
- Bug tracking and prioritization
- Sign-off from key stakeholders

---

## 9. DEPLOYMENT PLAN

### 9.1 Environments

**Development:**
- Local machine with Docker
- Continuous integration on every commit

**Staging:**
- Cloud/on-premise server
- Mirror of production
- For UAT and final testing

**Production:**
- High-availability setup
- Daily backup
- 24/7 monitoring

### 9.2 Deployment Process

```bash
# 1. Code Review & Merge
git checkout develop
git pull origin develop
git checkout -b feature/new-feature
# ... development ...
git commit -m "Add new feature"
git push origin feature/new-feature
# Create Pull Request → Review → Merge to develop

# 2. Deploy to Staging
git checkout staging
git merge develop
git push origin staging
# CI/CD automatically deploys to staging server

# 3. UAT on Staging
# Testers test features on staging
# Log bugs in issue tracker

# 4. Fix Bugs & Merge
# Fix bugs in develop → merge to staging → re-test

# 5. Deploy to Production
git checkout main
git merge staging
git tag v1.0.0
git push origin main --tags
# CI/CD automatically deploys to production server

# 6. Post-deployment
# Monitor logs for errors
# Check performance metrics
# Notify users of deployment
```

---

## 10. MAINTENANCE & SUPPORT

### 10.1 Warranty Period
- 3 months free support after go-live
- Bug fixes within 24 hours (critical), 3 days (non-critical)
- Monthly check-in meeting

### 10.2 Training
- 2-day workshop for all users
- Video tutorials (Bahasa Indonesia)
- User manual (PDF + online)
- FAQ page in system

### 10.3 Handover
- Source code repository access
- Database backup
- Deployment scripts
- Technical documentation
- Admin credentials

### 10.4 SLA (Service Level Agreement)

| Priority | Response Time | Resolution Time |
|----------|---------------|-----------------|
| Critical (system down) | 1 hour | 4 hours |
| High (major function broken) | 4 hours | 24 hours |
| Medium (minor function issue) | 8 hours | 3 days |
| Low (enhancement request) | 3 days | Next release |

---

## 11. APPENDIX

### 11.1 Glossary

- **DPA:** Dokumen Pelaksanaan Anggaran
- **PLGK:** Perencanaan Fisik dan Keuangan
- **ROK OP:** Rencana Operasional Kegiatan - Output
- **Kadis:** Kepala Dinas
- **Monev:** Monitoring dan Evaluasi
- **SKPD:** Satuan Kerja Perangkat Daerah
- **APBD:** Anggaran Pendapatan dan Belanja Daerah
- **SIPKD:** Sistem Informasi Pengelolaan Keuangan Daerah

### 11.2 References

- Permendagri No. 77 Tahun 2020 tentang Pedoman Teknis Pengelolaan Keuangan Daerah
- Permendagri No. 90 Tahun 2019 tentang Klasifikasi, Kodefikasi, dan Nomenklatur Perencanaan Pembangunan dan Keuangan Daerah
- Laravel Documentation: https://laravel.com/docs
- Vue.js Documentation: https://vuejs.org/guide/
- PostgreSQL Documentation: https://www.postgresql.org/docs/

---

**Document Status:** Ready for Review  
**Next Review Date:** 15 Januari 2026  
**Approved By:** [Pending]

---

_This PRD is a living document and will be updated as requirements evolve._
