# Functional Requirements Specification
## SIPERA - Sistem Informasi Perencanaan dan Realisasi Anggaran

**Version:** 1.0
**Date:** 8 Januari 2026

---

## 1. AUTHENTICATION & AUTHORIZATION

### FR-AUTH-001: User Login
**Priority:** Must Have

**User Story:** Sebagai user, saya ingin login menggunakan NIP dan password agar dapat mengakses sistem secara aman.

**Acceptance Criteria:**
- User dapat login dengan NIP (18 digit) dan password
- Password minimum 8 karakter dengan kombinasi (huruf + angka + simbol)
- Failed login attempts di-log
- Setelah 5 failed attempts, account locked selama 30 menit
- User melihat error message yang sesuai untuk invalid credentials
- Successful login redirect ke dashboard

**Technical Notes:**
- JWT untuk token-based authentication
- Token expires dalam 8 jam
- Refresh token mechanism
- Rate limiting (5 attempts per 5 menit)

---

### FR-AUTH-002: Role-based Access Control
**Priority:** Must Have

**User Story:** Sebagai admin, saya ingin assign roles ke user agar mereka hanya akses fitur sesuai job mereka.

**Roles:**
1. **ADMIN** - Full access semua fitur
2. **KADIS** - Read all, Approve all
3. **TIM_PERENCANAAN** - Read all, Input DPA/PLGK/ROK
4. **TIM_PELAKSANA** - Read own category, Input realisasi
5. **BENDAHARA** - Read all, Verify keuangan
6. **MONEV** - Read all, Generate reports
7. **VIEWER** - Read only

**Permission Matrix:**

| Feature | Admin | Kadis | Perencanaan | Pelaksana | Bendahara | Monev | Viewer |
|---------|-------|-------|-------------|-----------|-----------|-------|--------|
| View Dashboard | Y | Y | Y | Y | Y | Y | Y |
| Input DPA | Y | - | Y | - | - | - | - |
| Input Realisasi | Y | - | - | Y | - | - | - |
| Verify Realisasi | Y | - | - | - | Y | - | - |
| Approve Realisasi | Y | Y | - | - | - | - | - |
| Generate Report | Y | Y | Y | - | - | Y | Y |
| User Management | Y | - | - | - | - | - | - |
| Master Data | Y | - | Y | - | - | - | - |

---

## 2. MASTER DATA MANAGEMENT

### FR-MASTER-001: Manage Program
**Priority:** Must Have

**Acceptance Criteria:**
- CRUD program dengan: code, name, year, description
- Program code unique per tahun
- Soft delete (tidak benar-benar hapus, hanya archived)

**Fields:**
```
- code: VARCHAR(20), required, unique per year
- name: VARCHAR(255), required
- year: INT, required, default current year
- description: TEXT, optional
- status: ENUM(active, archived)
```

---

### FR-MASTER-002: Manage Kegiatan (Activities)
**Priority:** Must Have

**Acceptance Criteria:**
- Activity harus belong to a program
- CRUD activities
- Activity code unique within program

**Fields:**
```
- program_id: FK, required
- code: VARCHAR(20), required
- name: VARCHAR(255), required
- description: TEXT, optional
- location: VARCHAR(255), default "Semua Kabupaten/Kota"
```

---

### FR-MASTER-003: Manage Sub-Kegiatan
**Priority:** Must Have

**Acceptance Criteria:**
- Sub-activity belongs to an activity
- 5 predefined categories: ANALISIS, TATA_KELOLA, OPERASIONALISASI, LAYANAN, ELEK_NON_ELEK
- Input budget untuk n-1, n, dan n+1 years
- Category unique within activity

**Fields:**
```
- activity_id: FK, required
- category: ENUM, required
- name: VARCHAR(255), required
- budget_prev_year: DECIMAL(15,2), default 0
- budget_current_year: DECIMAL(15,2), required
- budget_next_year: DECIMAL(15,2), default 0
```

---

### FR-MASTER-004: Manage Account Codes (Kode Rekening)
**Priority:** Must Have

**Acceptance Criteria:**
- Hierarchical structure (5 levels)
- Code format: 5.1.02.01.01.0025
- Import dari Excel
- Search by code atau description
- Inactive codes hidden tapi tidak deleted

**Fields:**
```
- code: VARCHAR(50), unique, required
- description: VARCHAR(500), required
- level: INT(1-5), required
- parent_code: VARCHAR(50), optional
- is_active: BOOLEAN, default true
```

---

## 3. PERENCANAAN (PLANNING) MODULE

### FR-PLAN-001: Input DPA
**Priority:** Must Have

**User Story:** Sebagai Tim Perencanaan, saya ingin input DPA agar dokumen anggaran yang disetujui tercatat dalam sistem.

**Acceptance Criteria:**
- Form wizard 4 steps:
  1. Basic info (program, kegiatan, sub-kegiatan)
  2. Budget items (kode rekening, uraian, volume, harga)
  3. Indikator kinerja
  4. Review & submit
- Save as draft di setiap step
- Multiple budget items
- Total auto-calculated
- Upload supporting documents (PDF, max 10MB)
- Submit for approval ke Kadis

**Validation Rules:**
- Total budget items = sub_activity.budget_current_year
- Volume > 0
- Unit price > 0
- Account code harus level 5 (most detailed)

---

### FR-PLAN-002: Generate PLGK
**Priority:** Must Have

**User Story:** Sebagai Tim Perencanaan, saya ingin generate PLGK dari DPA yang approved agar dapat merencanakan alokasi budget bulanan.

**Acceptance Criteria:**
- Hanya generate dari approved DPA
- System creates 12 monthly plan records (Jan-Dec)
- User pilih allocation method:
  - Equal distribution (divide by 12)
  - Custom per month
  - Copy from previous year (if exists)
- Preview before save
- Edit setelah generation
- Total monthly plans = DPA total

**Contoh Alokasi Equal:**
```
DPA Total: Rp 120.000.000
Per bulan: Rp 10.000.000 (120M / 12)

Item: Kertas & Cover = Rp 1.200.000
Monthly plan = Rp 100.000 (1.2M / 12)
```

---

### FR-PLAN-003: Create ROK OP
**Priority:** Must Have

**User Story:** Sebagai Tim Perencanaan, saya ingin buat jadwal operasional agar tim tahu kapan harus execute activities.

**Acceptance Criteria:**
- Visual calendar interface (Gantt-chart style)
- Drag-drop budget items ke specific months
- Assign PIC (user) per item
- Set planned date
- Add notes/description
- Export ke PDF/Excel

**Fields per Schedule:**
```
- budget_item_id: FK
- month: 1-12
- scheduled_date: DATE
- pic_user_id: FK to users
- description: TEXT
- status: ENUM(planned, in_progress, completed, cancelled)
```

---

## 4. REALISASI (REALIZATION) MODULE

### FR-REAL-001: Input Realisasi
**Priority:** Must Have

**User Story:** Sebagai Tim Pelaksana, saya ingin input data realisasi agar actual expenditure tercatat.

**Acceptance Criteria:**
- Select month dan budget item
- System shows planned data (read-only reference)
- Input fields:
  - Realization volume
  - Actual unit price
  - Total (auto-calculated)
- Upload supporting documents:
  - Receipt, Invoice, Photo, Other
  - Max 5 files, each max 5MB
  - File types: PDF, JPG, PNG, JPEG
- Save as draft ATAU submit for verification
- Edit draft, tapi tidak setelah submitted

**Validation:**
- Realization volume warning jika > 110% of planned (allow override with reason)
- Unit price warning jika deviation > +/-10% dari plan
- Min 1 supporting document required untuk submit

---

### FR-REAL-002: Bulk Upload Realisasi
**Priority:** Should Have

**User Story:** Sebagai Tim Pelaksana, saya ingin upload multiple realization data via Excel agar input lebih cepat.

**Acceptance Criteria:**
- Download Excel template dengan format
- Upload filled Excel file
- System validates all rows
- Show validation errors dengan jelas
- Preview data before save
- Fix errors dan re-upload
- Success rate shown (e.g., 45/50 rows success)

**Template Columns:**
- Budget Item Code
- Month
- Realization Volume
- Unit Price
- Notes

---

### FR-REAL-003: Verification by Bendahara
**Priority:** Must Have

**User Story:** Sebagai Bendahara, saya ingin verify data realisasi agar akurasi keuangan terjamin.

**Acceptance Criteria:**
- Queue of submitted realizations
- Filter by: month, category, submitter
- Detail view shows:
  - Planned vs Realization comparison
  - Supporting documents (inline preview)
  - Verification checklist
- Actions: Approve ATAU Reject with reason
- Email notification ke submitter
- Internal notes (not visible ke submitter)

**Verification Checklist:**
- [ ] Dokumen lengkap dan sesuai
- [ ] Harga satuan wajar
- [ ] Volume sesuai dengan kontrak/PO
- [ ] Perhitungan total benar

---

### FR-REAL-004: Approval by Kadis
**Priority:** Must Have

**User Story:** Sebagai Kadis, saya ingin approve verified realizations agar data menjadi official.

**Acceptance Criteria:**
- Queue of verified realizations
- Summary view dengan key metrics
- Batch approval (multiple items sekaligus)
- Mobile-friendly interface
- Add approval notes
- Email notification ke all parties
- Approved data locked (tidak bisa edit)

---

## 5. MONITORING & DASHBOARD

### FR-DASH-001: Executive Dashboard (Kadis)
**Priority:** Must Have

**Shows:**
- Total budget vs realized (with percentage)
- Monthly absorption trend (line chart)
- Per-category absorption (bar chart)
- Top 5 largest items
- Alert count (deviations, pending approvals)

**Features:**
- Auto-refresh setiap 5 menit
- Date range filter
- Drill-down capability (click chart -> detail)
- Export dashboard ke PDF

---

### FR-DASH-002: Operational Dashboard (Tim Pelaksana)
**Priority:** Must Have

**Shows:**
- Tasks untuk current month
- Overdue tasks (highlighted red)
- Completed tasks (dengan checkmark)
- Upcoming deadlines (next 7 days)

**Features:**
- Quick input form
- Notification badge count
- Calendar view of scheduled activities

---

### FR-DASH-003: Deviation Alert System
**Priority:** Should Have

**Auto-generate alerts ketika:**
- Realization < 70% of plan (warning)
- Realization > 110% of plan (over-budget)
- Item tidak terealisasi dalam planned month
- Approaching deadline (H-7, H-3, H-day)

**Features:**
- Alert shown di dashboard
- Email notification ke relevant users
- Dismiss alert with reason
- Alert history logged

---

## 6. REPORTING MODULE

### FR-REPORT-001: Generate Laporan Bulanan
**Priority:** Must Have

**Acceptance Criteria:**
- Select month dan category (or all)
- Format sama dengan Excel existing
- Shows:
  - Header (SKPD, month, year)
  - Table rencana vs realisasi
  - Summary statistics
  - Signature area (Kadis)
- Export ke PDF dan Excel
- PDF includes cover page dan table of contents
- Excel editable untuk analisis lanjutan

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

### FR-REPORT-002: Custom Report Builder
**Priority:** Should Have

**Acceptance Criteria:**
- Step-by-step wizard:
  1. Select data source (category, month range)
  2. Choose columns to display
  3. Apply filters
  4. Select grouping
  5. Preview & export
- Save report template untuk reuse
- Export ke Excel, PDF, CSV
- Schedule automated email (daily, weekly, monthly)

---

### FR-REPORT-003: Laporan Triwulanan
**Priority:** Must Have

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

## 7. NOTIFICATION SYSTEM

### FR-NOTIF-001: In-app Notification
**Priority:** Must Have

**Types:**
- Approval request
- Approval result (approved/rejected)
- Deadline reminder
- Deviation alert
- System announcement

**Features:**
- Notification bell icon di header (with badge count)
- Dropdown list recent notifications
- Click notification navigates ke relevant page
- Mark as read/unread
- Mark all as read
- Notification retention: 30 days

---

### FR-NOTIF-002: Email Notification
**Priority:** Must Have

**Email sent untuk:**
- New approval request
- Approval decision (approved/rejected with reason)
- Deadline approaching (H-3, H-day)
- Monthly report ready

**Features:**
- Email template consistent dengan branding Pemprov Bali
- Unsubscribe option di email footer
- User configure email preferences di settings

---

## 8. PRIORITY MATRIX

| ID | Feature | Priority | Effort | Phase |
|----|---------|----------|--------|-------|
| FR-AUTH-001 | User Login | Must Have | M | 1 |
| FR-AUTH-002 | RBAC | Must Have | L | 1 |
| FR-MASTER-001 | Manage Program | Must Have | S | 1 |
| FR-MASTER-002 | Manage Kegiatan | Must Have | S | 1 |
| FR-MASTER-003 | Manage Sub-Kegiatan | Must Have | S | 1 |
| FR-MASTER-004 | Account Codes | Must Have | M | 1 |
| FR-PLAN-001 | Input DPA | Must Have | L | 2 |
| FR-PLAN-002 | Generate PLGK | Must Have | M | 2 |
| FR-PLAN-003 | Create ROK OP | Must Have | M | 2 |
| FR-REAL-001 | Input Realisasi | Must Have | L | 2 |
| FR-REAL-002 | Bulk Upload | Should Have | M | 3 |
| FR-REAL-003 | Verification | Must Have | M | 2 |
| FR-REAL-004 | Approval | Must Have | M | 2 |
| FR-DASH-001 | Executive Dashboard | Must Have | L | 3 |
| FR-DASH-002 | Operational Dashboard | Must Have | M | 3 |
| FR-DASH-003 | Deviation Alerts | Should Have | M | 3 |
| FR-REPORT-001 | Monthly Report | Must Have | L | 3 |
| FR-REPORT-002 | Custom Report | Should Have | L | 4 |
| FR-REPORT-003 | Quarterly Report | Must Have | M | 3 |
| FR-NOTIF-001 | In-app Notification | Must Have | M | 3 |
| FR-NOTIF-002 | Email Notification | Must Have | M | 3 |

**Effort Legend:**
- S = Small (< 1 week)
- M = Medium (1-2 weeks)
- L = Large (2-4 weeks)

---

**Document Status:** Complete
**Last Updated:** 8 Januari 2026
