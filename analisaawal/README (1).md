# SIPERA - Sistem Informasi Perencanaan dan Realisasi Anggaran

> **Web-based Budget Planning and Monitoring System for Dinas Kominfo Provinsi Bali**

---

## 📋 Project Overview

SIPERA adalah sistem informasi berbasis web untuk monitoring dan pelaporan realisasi anggaran di lingkungan Dinas Komunikasi, Informatika dan Statistik Provinsi Bali. Sistem ini menggantikan workflow manual berbasis Excel dengan web application yang terintegrasi, real-time, dan user-friendly.

### 🎯 Tujuan Project

1. **Digitalisasi** proses perencanaan dan monitoring anggaran
2. **Otomasi** pembuatan laporan bulanan/triwulanan/tahunan
3. **Transparansi** dan akuntabilitas pengelolaan anggaran
4. **Efisiensi** waktu dan mengurangi human error
5. **Kolaborasi** real-time antar stakeholder

### 📊 Scope

**Program:** Penyelenggaraan Persandian untuk Pengamanan Informasi  
**Total Anggaran:** Rp 1.385.437.875 (Tahun 2026)  
**Kategori Kegiatan:** 5 Sub-kegiatan
- ANALISIS (Rp 104.571.400)
- TATA KELOLA (Rp 53.683.500)
- OPERASIONALISASI (Rp 94.371.800)
- LAYANAN (Rp 918.886.900)
- ELEKTRONIK & NON-ELEKTRONIK (Rp 213.924.275)

---

## 🏗️ Tech Stack

### Backend
- **Framework:** Laravel 11 (PHP 8.2+)
- **Database:** PostgreSQL 16
- **Cache:** Redis 7
- **Queue:** Laravel Queue (Redis driver)
- **Storage:** MinIO / Local Storage

### Frontend
- **Framework:** Vue.js 3 + Vite
- **UI Library:** PrimeVue / Ant Design Vue
- **State Management:** Pinia
- **Charts:** Apache ECharts
- **HTTP Client:** Axios

### DevOps
- **Server:** Ubuntu 22.04 LTS
- **Web Server:** Nginx
- **Container:** Docker + Docker Compose (optional)
- **CI/CD:** GitHub Actions / GitLab CI
- **Version Control:** Git

---

## 🚀 Key Features

### 1. Perencanaan (Planning)
- ✅ Input DPA (Dokumen Pelaksanaan Anggaran)
- ✅ Generate PLGK (Perencanaan Fisik & Keuangan)
- ✅ Create ROK OP (Rencana Operasional Kegiatan)
- ✅ Budget allocation per bulan

### 2. Realisasi (Execution)
- ✅ Input realisasi belanja per item
- ✅ Upload bukti pendukung (receipt, invoice, photo)
- ✅ Approval workflow (3-level)
- ✅ Bulk input via Excel upload

### 3. Monitoring
- ✅ Real-time dashboard
- ✅ Progress tracking per kategori
- ✅ Deviation alerts
- ✅ Performance scorecard

### 4. Reporting
- ✅ Laporan bulanan (Jan-Des)
- ✅ Laporan triwulanan
- ✅ Laporan tahunan
- ✅ Export to PDF & Excel
- ✅ Custom report builder

### 5. User Management
- ✅ Role-based access control
- ✅ Multi-category access
- ✅ Audit trail
- ✅ Notification system

---

## 👥 User Roles

| Role | Access Level | Main Functions |
|------|--------------|----------------|
| **Admin** | Full access | System configuration, user management |
| **Kadis** | Read all, Approve all | Final approval, strategic monitoring |
| **Tim Perencanaan** | Read all, Input planning | DPA, PLGK, ROK OP creation |
| **Tim Pelaksana** | Read own, Input realization | Input realisasi belanja |
| **Bendahara** | Read all, Verify finance | Verify keuangan, validate receipts |
| **Monev** | Read all, Generate reports | Monitoring, evaluation, reporting |
| **Viewer** | Read only | External auditor, stakeholder |

---

## 📁 Project Structure

```
sipera/
├── backend/                    # Laravel Backend
│   ├── app/
│   │   ├── Http/Controllers/
│   │   ├── Models/
│   │   ├── Services/
│   │   └── Repositories/
│   ├── database/
│   │   ├── migrations/
│   │   └── seeders/
│   ├── routes/
│   │   ├── api.php
│   │   └── web.php
│   ├── tests/
│   └── storage/
│
├── frontend/                   # Vue.js Frontend
│   ├── src/
│   │   ├── components/
│   │   ├── views/
│   │   ├── stores/
│   │   ├── router/
│   │   ├── services/
│   │   └── utils/
│   ├── public/
│   └── package.json
│
├── docs/                       # Documentation
│   ├── api/
│   ├── database/
│   ├── deployment/
│   └── user-guide/
│
├── docker/                     # Docker configuration
│   ├── nginx/
│   ├── php/
│   └── postgres/
│
├── scripts/                    # Utility scripts
│   ├── migration/
│   └── backup/
│
├── docker-compose.yml
├── .env.example
└── README.md
```

---

## 🗄️ Database Schema (High-Level)

### Core Tables
- `programs` - Master program
- `activities` - Master kegiatan
- `sub_activities` - Sub-kegiatan (5 kategori)
- `budget_items` - Detail item belanja
- `account_codes` - Master kode rekening

### Planning Tables
- `monthly_plans` - Rencana per bulan
- `operational_schedules` - ROK OP

### Realization Tables
- `monthly_realizations` - Realisasi per bulan
- `realization_documents` - Dokumen pendukung

### System Tables
- `users` - User accounts
- `user_permissions` - Access control
- `notifications` - Notification queue
- `audit_logs` - Audit trail

---

## 🔄 Workflow

```
1. PERENCANAAN
   Tim Perencanaan → Input DPA → Generate PLGK → Create ROK OP
                           ↓
                      Approval Kadis
                           ↓
                      Baseline Ready

2. PELAKSANAAN
   Tim Pelaksana → Input Realisasi → Upload Bukti
                           ↓
                   Bendahara Verify
                           ↓
                    Kadis Approve
                           ↓
                   Data Tersimpan

3. MONITORING
   Monev → Generate Dashboard → Analisis Deviasi → Report
                           ↓
                    Kadis Review
                           ↓
              Tindak Lanjut (jika perlu)

4. REPORTING
   Monev → Generate Laporan → Export PDF/Excel
                           ↓
                  Submit ke Stakeholder
```

---

## 🎨 Design Principles

### UI/UX
- **Simple & Clean** - Minimal 3-click to action
- **Responsive** - Mobile, tablet, desktop friendly
- **Consistent** - Same pattern across modules
- **Accessible** - WCAG 2.1 Level AA

### Code Quality
- **SOLID Principles**
- **DRY (Don't Repeat Yourself)**
- **Clean Code**
- **Test-Driven Development (TDD)**

### Security
- **HTTPS Only**
- **JWT Authentication**
- **Role-based Authorization**
- **Input Validation & Sanitization**
- **SQL Injection Prevention**
- **XSS Protection**

---

## 📈 Development Phases

### Phase 1: Foundation (Month 1-2)
- Database design & implementation
- Authentication & authorization
- Master data CRUD
- Basic API structure

### Phase 2: Core Features (Month 3-4)
- DPA entry module
- PLGK generator
- Realisasi input
- Approval workflow

### Phase 3: Reporting (Month 5)
- Dashboard implementation
- Report generator
- Chart & visualization
- Export functionality

### Phase 4: Advanced Features (Month 6)
- Notification system
- Deviation alerts
- Custom report builder
- Performance analytics

### Phase 5: Testing & Training (Month 7)
- UAT with real users
- Bug fixing
- Documentation
- User training

### Phase 6: Deployment (Month 8)
- Production deployment
- Data migration
- Go-live support
- Handover

---

## 🚦 Getting Started

### Prerequisites
```bash
# Backend
PHP >= 8.2
Composer 2.x
PostgreSQL >= 16
Redis >= 7

# Frontend
Node.js >= 20.x
npm >= 10.x
```

### Installation

```bash
# Clone repository
git clone https://github.com/diskominfo-bali/sipera.git
cd sipera

# Backend setup
cd backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve

# Frontend setup (new terminal)
cd frontend
npm install
npm run dev
```

### Docker Installation (Alternative)

```bash
# Clone and start
git clone https://github.com/diskominfo-bali/sipera.git
cd sipera
docker-compose up -d

# Access
# Frontend: http://localhost:8080
# Backend API: http://localhost:8000
```

---

## 📚 Documentation

- [API Documentation](docs/api/README.md)
- [Database Schema](docs/database/schema.md)
- [Deployment Guide](docs/deployment/README.md)
- [User Manual](docs/user-guide/README.md)
- [Contributing Guidelines](CONTRIBUTING.md)

---

## 🧪 Testing

```bash
# Backend tests
cd backend
php artisan test

# Frontend tests
cd frontend
npm run test

# E2E tests
npm run test:e2e
```

---

## 📊 Performance Targets

| Metric | Target |
|--------|--------|
| Page Load Time | < 2 seconds |
| API Response Time | < 500ms |
| Uptime | > 99.5% |
| Concurrent Users | 50+ users |
| Database Query Time | < 100ms |

---

## 🔐 Security

- SSL/TLS encryption
- JWT token with refresh mechanism
- Password hashing (bcrypt)
- Rate limiting on API
- CSRF protection
- Input validation on all endpoints
- Regular security audits

---

## 🤝 Contributing

Contributions are welcome! Please read [CONTRIBUTING.md](CONTRIBUTING.md) for details on our code of conduct and the process for submitting pull requests.

---

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

---

## 👨‍💻 Development Team

**Project Owner:** Dinas Kominfo Provinsi Bali  
**Project Manager:** [Name]  
**Lead Developer:** [Name]  
**UI/UX Designer:** [Name]  

---

## 📞 Support

For support, email support@sipera.baliprov.go.id or join our Slack channel.

---

## 🗺️ Roadmap

- ✅ Phase 1: Core system (Q1 2026)
- ⏳ Phase 2: Mobile app (Q2 2026)
- ⏳ Phase 3: AI Analytics (Q3 2026)
- ⏳ Phase 4: Multi-SKPD rollout (Q4 2026)

---

**Last Updated:** 8 Januari 2026  
**Version:** 1.0.0
