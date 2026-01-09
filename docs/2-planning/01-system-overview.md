# System Overview
## SIPERA - Sistem Informasi Perencanaan dan Realisasi Anggaran

**Version:** 1.0
**Date:** 8 Januari 2026

---

## 1. SYSTEM CONTEXT DIAGRAM

```
                                    ┌─────────────────────────────┐
                                    │     EXTERNAL SYSTEMS        │
                                    │                             │
                                    │  ┌────────────┐             │
                                    │  │   SIPKD    │ (Future)    │
                                    │  └─────┬──────┘             │
                                    │        │                    │
                                    │  ┌─────▼──────┐             │
                                    │  │  E-Mail    │             │
                                    │  │  Service   │             │
                                    │  └────────────┘             │
                                    └─────────────────────────────┘
                                              │
                                              │ API/SMTP
                                              ▼
┌──────────────────────────────────────────────────────────────────────────────┐
│                                                                              │
│   ┌───────────┐     ┌───────────┐     ┌───────────┐     ┌───────────┐       │
│   │   Kadis   │     │    Tim    │     │    Tim    │     │ Bendahara │       │
│   │           │     │Perencanaan│     │ Pelaksana │     │           │       │
│   └─────┬─────┘     └─────┬─────┘     └─────┬─────┘     └─────┬─────┘       │
│         │                 │                 │                 │             │
│   ┌─────▼─────┐     ┌─────▼─────┐     ┌─────▼─────┐     ┌─────▼─────┐       │
│   │   Monev   │     │   Admin   │     │   Viewer  │     │           │       │
│   │           │     │           │     │           │     │           │       │
│   └─────┬─────┘     └─────┬─────┘     └─────┬─────┘     │           │       │
│         │                 │                 │           │           │       │
│         └─────────────────┴─────────────────┴───────────┘           │       │
│                                    │                                 │       │
│                                    │ HTTPS                          │       │
│                                    ▼                                 │       │
│                    ┌───────────────────────────────┐                │       │
│                    │                               │                │       │
│                    │           SIPERA              │                │       │
│                    │      Web Application          │                │       │
│                    │                               │                │       │
│                    │  ┌─────────────────────────┐  │                │       │
│                    │  │     Frontend (Vue.js)   │  │                │       │
│                    │  ├─────────────────────────┤  │                │       │
│                    │  │     Backend (Laravel)   │  │                │       │
│                    │  ├─────────────────────────┤  │                │       │
│                    │  │  PostgreSQL │ Redis     │  │                │       │
│                    │  └─────────────────────────┘  │                │       │
│                    │                               │                │       │
│                    └───────────────────────────────┘                │       │
│                                                                      │       │
│                              USERS                                   │       │
└──────────────────────────────────────────────────────────────────────────────┘
```

---

## 2. HIGH-LEVEL SYSTEM ARCHITECTURE

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                              PRESENTATION LAYER                              │
│  ┌───────────────────────────────────────────────────────────────────────┐  │
│  │                         Vue.js 3 Frontend                             │  │
│  │  ┌─────────────┐ ┌─────────────┐ ┌─────────────┐ ┌─────────────┐     │  │
│  │  │  Dashboard  │ │  Planning   │ │ Realization │ │  Reporting  │     │  │
│  │  │   Module    │ │   Module    │ │   Module    │ │   Module    │     │  │
│  │  └─────────────┘ └─────────────┘ └─────────────┘ └─────────────┘     │  │
│  │  ┌─────────────┐ ┌─────────────┐ ┌─────────────┐                     │  │
│  │  │    Auth     │ │   Master    │ │Notification │                     │  │
│  │  │   Module    │ │   Module    │ │   Module    │                     │  │
│  │  └─────────────┘ └─────────────┘ └─────────────┘                     │  │
│  └───────────────────────────────────────────────────────────────────────┘  │
└────────────────────────────────────────┬────────────────────────────────────┘
                                         │ REST API (JSON)
                                         ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                              APPLICATION LAYER                               │
│  ┌───────────────────────────────────────────────────────────────────────┐  │
│  │                       Laravel 11 Backend                              │  │
│  │                                                                       │  │
│  │  ┌─────────────────────────────────────────────────────────────────┐  │  │
│  │  │                        Controllers                              │  │  │
│  │  │  Auth │ Dashboard │ Planning │ Realization │ Report │ Master   │  │  │
│  │  └───────────────────────────────────────────────────────────────┬─┘  │  │
│  │                                                                   │    │  │
│  │  ┌─────────────────────────────────────────────────────────────┬─┘    │  │
│  │  │                         Services                            │      │  │
│  │  │  AuthService │ DpaService │ RealizationService │ ReportService    │  │
│  │  └───────────────────────────────────────────────────────────┬─┘      │  │
│  │                                                               │        │  │
│  │  ┌─────────────────────────────────────────────────────────┬─┘        │  │
│  │  │                       Repositories                      │          │  │
│  │  │  ProgramRepo │ BudgetRepo │ RealizationRepo │ UserRepo             │  │
│  │  └─────────────────────────────────────────────────────────┘          │  │
│  └───────────────────────────────────────────────────────────────────────┘  │
└────────────────────────────────────────┬────────────────────────────────────┘
                                         │
                                         ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                               DATA LAYER                                     │
│  ┌─────────────────────┐  ┌──────────────────┐  ┌─────────────────────┐    │
│  │    PostgreSQL 16    │  │     Redis 7      │  │    File Storage     │    │
│  │                     │  │                  │  │                     │    │
│  │  - Programs         │  │  - Sessions      │  │  - Documents        │    │
│  │  - Activities       │  │  - Cache         │  │  - Images           │    │
│  │  - Budget Items     │  │  - Job Queue     │  │  - Reports          │    │
│  │  - Realizations     │  │  - Rate Limits   │  │  - Backups          │    │
│  │  - Users            │  │                  │  │                     │    │
│  │  - Audit Logs       │  │                  │  │                     │    │
│  └─────────────────────┘  └──────────────────┘  └─────────────────────┘    │
└─────────────────────────────────────────────────────────────────────────────┘
```

---

## 3. MODULE BREAKDOWN

### 3.1 Authentication Module
**Purpose:** Mengelola identifikasi dan otorisasi user

**Components:**
- Login / Logout
- Password Reset
- Session Management
- Role & Permission Management

**Dependencies:** None (foundational)

---

### 3.2 Master Data Module
**Purpose:** Mengelola data master yang digunakan seluruh sistem

**Components:**
- Program Management
- Activity (Kegiatan) Management
- Sub-Activity (Sub-Kegiatan) Management
- Account Code (Kode Rekening) Management
- User Management

**Dependencies:** Authentication Module

---

### 3.3 Planning Module
**Purpose:** Mengelola proses perencanaan anggaran

**Components:**
- DPA Entry (Dokumen Pelaksanaan Anggaran)
- PLGK Generator (Perencanaan Fisik & Keuangan)
- ROK OP Creator (Rencana Operasional Kegiatan)
- Budget Item Management

**Dependencies:** Master Data Module, Authentication Module

---

### 3.4 Realization Module
**Purpose:** Mengelola proses input dan approval realisasi anggaran

**Components:**
- Realization Input Form
- Bulk Upload (Excel)
- Document Upload
- Verification Workflow (Bendahara)
- Approval Workflow (Kadis)

**Dependencies:** Planning Module, Authentication Module

---

### 3.5 Dashboard Module
**Purpose:** Menyajikan visualisasi dan monitoring data

**Components:**
- Executive Dashboard (Kadis)
- Operational Dashboard (Tim Pelaksana)
- Deviation Alert System
- KPI Scorecard

**Dependencies:** Realization Module, Planning Module

---

### 3.6 Reporting Module
**Purpose:** Menghasilkan laporan dalam berbagai format

**Components:**
- Monthly Report Generator
- Quarterly Report Generator
- Annual Report Generator
- Custom Report Builder
- Export Engine (PDF, Excel, CSV)

**Dependencies:** Realization Module, Planning Module

---

### 3.7 Notification Module
**Purpose:** Mengelola notifikasi ke user

**Components:**
- In-App Notifications
- Email Notifications
- Notification Preferences

**Dependencies:** All modules (cross-cutting concern)

---

## 4. INTERACTION MATRIX

### User-Module Access Matrix

| Module | Admin | Kadis | Perencanaan | Pelaksana | Bendahara | Monev | Viewer |
|--------|-------|-------|-------------|-----------|-----------|-------|--------|
| Auth (Login) | R | R | R | R | R | R | R |
| Master Data | CRUD | R | CRU | R | R | R | R |
| Planning - DPA | CRUD | R | CRUD | R | R | R | R |
| Planning - PLGK | CRUD | R | CRUD | R | R | R | R |
| Planning - ROK | CRUD | R | CRUD | R | R | R | R |
| Realization Input | CRUD | R | R | CRU | R | R | R |
| Realization Verify | CRUD | R | R | R | CRU | R | R |
| Realization Approve | CRUD | CRU | R | R | R | R | R |
| Dashboard - Exec | R | R | R | - | R | R | R |
| Dashboard - Ops | R | R | R | R | R | R | R |
| Reporting | CRUD | RU | RU | R | R | CRUD | R |
| Notification | CRUD | RU | RU | RU | RU | RU | RU |
| User Management | CRUD | R | - | - | - | - | - |
| Audit Logs | R | R | - | - | - | R | - |

**Legend:** C=Create, R=Read, U=Update, D=Delete, -=No Access

---

## 5. DATA FLOW OVERVIEW

### 5.1 Planning Data Flow
```
┌──────────┐     ┌──────────┐     ┌──────────┐     ┌──────────┐
│   DPA    │────▶│   PLGK   │────▶│  ROK OP  │────▶│  Budget  │
│  Entry   │     │Generator │     │ Creator  │     │  Items   │
└──────────┘     └──────────┘     └──────────┘     └──────────┘
     │                                                    │
     │              APPROVAL WORKFLOW                     │
     ▼                                                    ▼
┌──────────┐                                        ┌──────────┐
│  Kadis   │◀──────────────────────────────────────│ Monthly  │
│ Approval │                                        │  Plans   │
└──────────┘                                        └──────────┘
```

### 5.2 Realization Data Flow
```
┌──────────┐     ┌──────────┐     ┌──────────┐     ┌──────────┐
│  Input   │────▶│  Upload  │────▶│  Submit  │────▶│  Verify  │
│Realisasi │     │ Documents│     │for Review│     │(Bendahara)│
└──────────┘     └──────────┘     └──────────┘     └──────────┘
                                                        │
                                                        │ Approved/Rejected
                                                        ▼
┌──────────┐     ┌──────────┐     ┌──────────┐     ┌──────────┐
│   Done   │◀────│  Notify  │◀────│  Approve │◀────│  Review  │
│          │     │   All    │     │  (Kadis) │     │          │
└──────────┘     └──────────┘     └──────────┘     └──────────┘
```

### 5.3 Reporting Data Flow
```
┌──────────┐     ┌──────────┐     ┌──────────┐     ┌──────────┐
│  Select  │────▶│  Query   │────▶│ Generate │────▶│  Export  │
│ Criteria │     │   Data   │     │  Report  │     │ PDF/Excel│
└──────────┘     └──────────┘     └──────────┘     └──────────┘
     │
     │ Save Template
     ▼
┌──────────┐
│ Reusable │
│ Template │
└──────────┘
```

---

## 6. TECHNOLOGY DECISIONS

### 6.1 Backend: Laravel 11 (PHP 8.2+)
**Rationale:**
- Mature framework dengan ecosystem lengkap
- Built-in authentication & authorization
- Eloquent ORM untuk database operations
- Queue system untuk background jobs
- Large community support di Indonesia

### 6.2 Frontend: Vue.js 3 + Vite
**Rationale:**
- Learning curve lebih rendah dibanding React
- Official router & state management (Vue Router, Pinia)
- Composition API untuk better code organization
- Excellent documentation

### 6.3 Database: PostgreSQL 16
**Rationale:**
- ACID compliance untuk data integrity
- JSON/JSONB support untuk flexible data
- Better performance untuk complex queries
- Partitioning support untuk audit logs

### 6.4 Cache: Redis 7
**Rationale:**
- Session storage untuk distributed systems
- Query caching untuk performance
- Job queue untuk background processing
- Rate limiting implementation

### 6.5 UI Framework: PrimeVue atau Ant Design Vue
**Rationale:**
- Comprehensive component library
- Built-in themes
- Accessibility support
- Form components ready-to-use

---

## 7. KEY TECHNICAL DECISIONS

| Decision | Choice | Alternative | Rationale |
|----------|--------|-------------|-----------|
| Authentication | JWT (Sanctum) | Session-based | Stateless, scalable |
| Authorization | Spatie Permission | Custom RBAC | Battle-tested, flexible |
| API Style | RESTful | GraphQL | Simpler, well-understood |
| File Storage | MinIO/Local | AWS S3 | Data sovereignty, cost |
| PDF Generation | DomPDF | Puppeteer | Native PHP, no headless browser |
| Excel Export | Maatwebsite/Excel | PhpSpreadsheet | Laravel integration |
| Charts | Apache ECharts | Chart.js | More visualization options |

---

## 8. DEPLOYMENT TOPOLOGY

```
┌─────────────────────────────────────────────────────────────────┐
│                         PRODUCTION                               │
│                                                                  │
│  ┌─────────────┐     ┌─────────────────────────────────────┐   │
│  │    Users    │────▶│          Load Balancer              │   │
│  │  (Browser)  │     │            (Nginx)                   │   │
│  └─────────────┘     └─────────────┬───────────────────────┘   │
│                                     │                           │
│                    ┌────────────────┼────────────────┐          │
│                    │                │                │          │
│                    ▼                ▼                ▼          │
│               ┌─────────┐     ┌─────────┐     ┌─────────┐      │
│               │  App    │     │  App    │     │  App    │      │
│               │Server 1 │     │Server 2 │     │Server N │      │
│               │(Laravel)│     │(Laravel)│     │(Laravel)│      │
│               └────┬────┘     └────┬────┘     └────┬────┘      │
│                    │               │               │            │
│                    └───────────────┼───────────────┘            │
│                                    │                            │
│                    ┌───────────────┼───────────────┐            │
│                    ▼               ▼               ▼            │
│               ┌─────────┐     ┌─────────┐     ┌─────────┐      │
│               │PostgreSQL│     │  Redis  │     │  MinIO  │      │
│               │  Master  │     │ Cluster │     │ Storage │      │
│               └────┬────┘     └─────────┘     └─────────┘      │
│                    │                                            │
│                    ▼                                            │
│               ┌─────────┐                                       │
│               │PostgreSQL│                                       │
│               │  Replica │                                       │
│               └─────────┘                                       │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

---

**Document Status:** Complete
**Last Updated:** 8 Januari 2026
