# Business Requirements Document
## SIPERA - Sistem Informasi Perencanaan dan Realisasi Anggaran

**Version:** 1.0
**Date:** 8 Januari 2026
**Status:** Validated

---

## 1. LATAR BELAKANG

### 1.1 Kondisi Saat Ini
Dinas Komunikasi, Informatika dan Statistik Provinsi Bali saat ini menggunakan **5 file Excel terpisah** untuk monitoring realisasi anggaran Program Penyelenggaraan Persandian untuk Pengamanan Informasi.

**Masalah Utama:**
- Rawan human error pada formula Excel
- Time-consuming (4-6 jam untuk generate laporan bulanan)
- Sulit kolaborasi real-time antar tim
- Tidak ada audit trail perubahan data
- Reporting manual dan repetitif
- Data tersebar di multiple files

### 1.2 Kebutuhan Bisnis
Diperlukan sistem web-based yang dapat:
1. Menggabungkan 5 kategori anggaran dalam satu platform
2. Otomasi proses perencanaan hingga pelaporan
3. Real-time collaboration antar stakeholder
4. Workflow approval yang jelas
5. Audit trail untuk compliance

---

## 2. SCOPE PROJECT

### 2.1 In Scope
| Item | Deskripsi |
|------|-----------|
| Program | Penyelenggaraan Persandian untuk Pengamanan Informasi |
| Total Anggaran | Rp 1.385.437.875 (Tahun 2026) |
| Kategori | 5 sub-kegiatan |
| User | ~30-50 user internal |
| Platform | Web application (responsive) |

### 2.2 Kategori Anggaran (Validated dari Excel)

| No | Kategori | Kode Kegiatan | Anggaran 2026 | % Total |
|----|----------|---------------|---------------|---------|
| 1 | ANALISIS | 2.21.02.1.01 | Rp 104.571.400 | 7.55% |
| 2 | TATA KELOLA | 2.21.02.1.01 | Rp 53.683.500 | 3.88% |
| 3 | OPERASIONALISASI | 2.21.02.1.02 | Rp 94.371.800 | 6.81% |
| 4 | LAYANAN | 2.21.02.1.01 | Rp 918.886.900 | 66.33% |
| 5 | ELEK & NON-ELEK | 2.21.02.1.01 | Rp 213.924.275 | 15.44% |
| | **TOTAL** | | **Rp 1.385.437.875** | **100%** |

### 2.3 Out of Scope (Phase 1)
- Integrasi dengan SIPKD (future phase)
- Mobile native application
- WhatsApp notification
- Multi-SKPD support
- AI Analytics

---

## 3. USER ANALYSIS

### 3.1 User Personas

#### Persona 1: Kadis (Kepala Dinas)
**Profil:**
- Usia: 45-55 tahun
- Tech-savvy: Medium
- Device: Desktop + Mobile

**Goals:**
- Monitor penyerapan anggaran real-time
- Approve realisasi dengan cepat
- Get executive summary untuk rapat

**Pain Points:**
- Terlalu banyak approval manual via email/WA
- Informasi tidak real-time
- Susah tracking deviasi budget

**Kebutuhan:**
- Executive dashboard dengan visual yang jelas
- Mobile-friendly untuk approval on-the-go
- Alert otomatis untuk deviasi besar

---

#### Persona 2: Tim Perencanaan
**Profil:**
- Usia: 30-40 tahun
- Tech-savvy: High
- Device: Desktop

**Goals:**
- Buat perencanaan anggaran yang akurat
- Generate PLGK dan ROK OP dengan efisien
- Monitor progress vs plan

**Pain Points:**
- Excel formula sering error
- Breakdown manual per bulan repetitif
- Revisi anggaran susah tracking

**Kebutuhan:**
- Form wizard untuk input DPA
- Auto-generate PLGK dari DPA
- Template untuk ROK OP
- Version control untuk revisi

---

#### Persona 3: Tim Pelaksana
**Profil:**
- Usia: 25-40 tahun
- Tech-savvy: Medium-High
- Device: Desktop + Tablet

**Goals:**
- Input realisasi dengan cepat
- Upload bukti dengan mudah
- Track status approval

**Pain Points:**
- Form Excel banyak yang harus diisi manual
- Upload bukti via email susah tracking
- Tidak tahu status approval

**Kebutuhan:**
- Form input yang simpel
- Drag-drop file upload
- Notification untuk approval status

---

#### Persona 4: Bendahara
**Profil:**
- Usia: 35-50 tahun
- Tech-savvy: Medium
- Device: Desktop

**Goals:**
- Verify realisasi keuangan dengan akurat
- Cek kelengkapan dokumen pendukung
- Rekonsiliasi dengan SIPKD

**Pain Points:**
- Dokumen pendukung tidak lengkap
- Susah tracking yang sudah verified vs belum
- Harus buka banyak file Excel

**Kebutuhan:**
- Queue approval yang jelas
- Preview dokumen pendukung dalam 1 screen
- Checklist untuk verification

---

#### Persona 5: Monev (Monitoring & Evaluasi)
**Profil:**
- Usia: 30-45 tahun
- Tech-savvy: High
- Device: Desktop

**Goals:**
- Generate laporan cepat dan akurat
- Analisis deviasi dan tren
- Presentasi ke Kadis/Stakeholder

**Pain Points:**
- Compile data dari banyak Excel manual
- Chart harus dibuat manual
- Format laporan berbeda-beda

**Kebutuhan:**
- One-click report generation
- Auto-generate charts
- Export ke multiple format (PDF, Excel)
- Custom report builder

---

## 4. SUCCESS CRITERIA

### 4.1 Business KPI

| Metric | Current (Excel) | Target (Web App) | Improvement |
|--------|----------------|------------------|-------------|
| Time to Generate Monthly Report | 4-6 jam | < 5 menit | 96% faster |
| Data Entry Error Rate | ~10% | < 1% | 90% reduction |
| Approval Cycle Time | 5-7 hari | 1-2 hari | 70% faster |
| User Adoption | N/A | > 80% dalam 3 bulan | - |
| System Uptime | N/A | > 99.5% | - |

### 4.2 Technical KPI

| Metric | Target |
|--------|--------|
| Page Load Time | < 2 detik |
| API Response Time | < 500ms (p95) |
| Concurrent Users | 50 users |
| Database Query Time | < 100ms |
| File Upload Time | < 5 detik (5MB file) |

---

## 5. CONSTRAINTS & ASSUMPTIONS

### 5.1 Constraints
1. **Budget:** Mengikuti alokasi APBD yang tersedia
2. **Timeline:** Go-live sebelum Q2 2026
3. **Infrastructure:** Menggunakan server existing atau cloud pemerintah
4. **Compliance:** Harus comply dengan regulasi keuangan daerah
5. **Security:** Data harus dienkripsi, akses berbasis role

### 5.2 Assumptions
1. User memiliki akses internet yang stabil
2. User memiliki device (PC/laptop) untuk akses sistem
3. Data Excel existing dapat dimigrasikan
4. Stakeholder akan berpartisipasi dalam UAT
5. IT support tersedia untuk maintenance

---

## 6. STAKEHOLDER SIGN-OFF

| Role | Nama | Tanggal | Tanda Tangan |
|------|------|---------|--------------|
| Project Sponsor (Kadis) | | | |
| Product Owner | | | |
| IT Manager | | | |
| Finance (Bendahara) | | | |

---

**Document Status:** Ready for Review
**Next Review:** [Date]
