# API Specification
## SIPERA - Sistem Informasi Perencanaan dan Realisasi Anggaran

**Version:** 1.0
**Date:** 8 Januari 2026
**Base URL:** `https://sipera.baliprov.go.id/api`

---

## 1. API CONVENTIONS

### 1.1 Request/Response Format
- Content-Type: `application/json`
- Accept: `application/json`
- All timestamps in ISO 8601 format: `2026-01-08T10:30:00Z`
- All amounts in Indonesian Rupiah (integer, no decimal)

### 1.2 Authentication
- Method: Bearer Token (JWT)
- Header: `Authorization: Bearer {token}`
- Token expiry: 8 hours
- Refresh token expiry: 30 days

### 1.3 Standard Response Format

**Success Response (2xx)**
```json
{
  "success": true,
  "data": { ... },
  "message": "Optional success message"
}
```

**Error Response (4xx, 5xx)**
```json
{
  "success": false,
  "message": "Error description",
  "errors": {
    "field_name": ["Validation error 1", "Validation error 2"]
  }
}
```

### 1.4 Pagination
Query parameters:
- `page` - Page number (default: 1)
- `per_page` - Items per page (default: 15, max: 100)
- `sort` - Sort field (default: id)
- `order` - Sort direction: asc/desc (default: asc)

Response:
```json
{
  "success": true,
  "data": [ ... ],
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
```

### 1.5 Filtering
Query parameter: `filter[field]=value`
Example: `/api/realizations?filter[status]=APPROVED&filter[month]=11`

### 1.6 HTTP Status Codes
| Code | Description |
|------|-------------|
| 200 | OK - Request successful |
| 201 | Created - Resource created |
| 204 | No Content - Delete successful |
| 400 | Bad Request - Invalid request |
| 401 | Unauthorized - Invalid/missing token |
| 403 | Forbidden - No permission |
| 404 | Not Found - Resource not found |
| 422 | Unprocessable Entity - Validation error |
| 429 | Too Many Requests - Rate limit exceeded |
| 500 | Internal Server Error |

---

## 2. AUTHENTICATION ENDPOINTS

### POST /auth/login
Login dan mendapatkan token.

**Request:**
```json
{
  "nip": "199001012020121001",
  "password": "SecureP@ss123"
}
```

**Response (200):**
```json
{
  "success": true,
  "data": {
    "user": {
      "id": 1,
      "nip": "199001012020121001",
      "name": "I Made Oka",
      "email": "oka@baliprov.go.id",
      "role": "KADIS",
      "sub_activity_id": null
    },
    "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
    "refresh_token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
    "token_type": "Bearer",
    "expires_in": 28800
  },
  "message": "Login berhasil"
}
```

**Response (401):**
```json
{
  "success": false,
  "message": "NIP atau password salah"
}
```

**Response (429):**
```json
{
  "success": false,
  "message": "Terlalu banyak percobaan login. Silakan coba lagi dalam 30 menit."
}
```

---

### POST /auth/logout
Logout dan invalidate token.

**Headers:** `Authorization: Bearer {token}`

**Response (200):**
```json
{
  "success": true,
  "message": "Logout berhasil"
}
```

---

### POST /auth/refresh
Refresh access token.

**Request:**
```json
{
  "refresh_token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9..."
}
```

**Response (200):**
```json
{
  "success": true,
  "data": {
    "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
    "refresh_token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
    "expires_in": 28800
  }
}
```

---

### GET /auth/me
Get current user profile.

**Headers:** `Authorization: Bearer {token}`

**Response (200):**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "nip": "199001012020121001",
    "name": "I Made Oka",
    "email": "oka@baliprov.go.id",
    "phone": "081234567890",
    "role": "KADIS",
    "permissions": ["view_dashboard", "approve_realization", "generate_report"],
    "sub_activity": null
  }
}
```

---

## 3. DASHBOARD ENDPOINTS

### GET /dashboard/summary
Get dashboard summary untuk current user.

**Headers:** `Authorization: Bearer {token}`

**Query Parameters:**
- `year` - Tahun (default: current year)

**Response (200):**
```json
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
      {
        "category": "ELEK_NON_ELEK",
        "budget": 213924275,
        "realized": 150000000,
        "rate": 70.1
      }
    ],
    "monthly_trend": [
      { "month": 1, "planned": 100000000, "realized": 95000000 },
      { "month": 2, "planned": 105000000, "realized": 102000000 }
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

### GET /dashboard/alerts
Get active alerts.

**Headers:** `Authorization: Bearer {token}`

**Response (200):**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "type": "DEVIATION",
      "severity": "critical",
      "title": "Deviasi Besar",
      "message": "Realisasi Perjalanan Dinas melebihi 20% dari rencana",
      "link": "/realizations/123",
      "created_at": "2026-01-08T10:00:00Z"
    }
  ]
}
```

---

## 4. MASTER DATA ENDPOINTS

### Programs

#### GET /programs
List all programs.

**Response (200):**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "code": "2.21.02",
      "name": "Program Penyelenggaraan Persandian",
      "year": 2026,
      "status": "active",
      "activities_count": 2
    }
  ]
}
```

#### POST /programs
Create new program.

**Request:**
```json
{
  "code": "2.21.02",
  "name": "Program Penyelenggaraan Persandian",
  "year": 2026,
  "description": "Program persandian untuk pengamanan informasi"
}
```

---

### Sub-Activities

#### GET /sub-activities
List all sub-activities.

**Query Parameters:**
- `filter[category]` - Filter by category
- `include` - Include relations: `budget_items,activity`

**Response (200):**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "activity_id": 1,
      "category": "LAYANAN",
      "name": "Layanan Keamanan Informasi",
      "budget_current_year": 918886900,
      "activity": {
        "id": 1,
        "code": "2.21.02.1.01",
        "name": "Kegiatan Persandian"
      }
    }
  ]
}
```

---

### Account Codes

#### GET /account-codes
List account codes.

**Query Parameters:**
- `filter[level]` - Filter by level (1-5)
- `search` - Search in code or description
- `is_active` - Filter active/inactive (default: true)

**Response (200):**
```json
{
  "success": true,
  "data": [
    {
      "code": "5.1.02.01.01.0025",
      "description": "Belanja Kertas dan Cover",
      "level": 5,
      "parent_code": "5.1.02.01.01",
      "is_active": true
    }
  ]
}
```

#### POST /account-codes/import
Import account codes from Excel.

**Headers:**
- `Content-Type: multipart/form-data`
- `Authorization: Bearer {token}`

**Request:**
- `file` - Excel file (.xlsx)

**Response (200):**
```json
{
  "success": true,
  "data": {
    "imported": 150,
    "skipped": 5,
    "errors": [
      { "row": 23, "message": "Duplicate code: 5.1.02.01.01.0025" }
    ]
  },
  "message": "Import selesai: 150 berhasil, 5 dilewati"
}
```

---

## 5. PLANNING ENDPOINTS

### Budget Items

#### GET /sub-activities/{id}/budget-items
Get budget items for a sub-activity.

**Response (200):**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "sub_activity_id": 1,
      "account_code": "5.1.02.01.01.0025",
      "description": "Belanja Kertas dan Cover",
      "unit": "rim",
      "unit_price": 33940,
      "total_volume": 10,
      "total_amount": 339400
    }
  ]
}
```

#### POST /sub-activities/{id}/budget-items
Create budget item.

**Request:**
```json
{
  "account_code": "5.1.02.01.01.0025",
  "description": "Belanja Kertas dan Cover",
  "unit": "rim",
  "unit_price": 33940,
  "total_volume": 10
}
```

**Response (201):**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "sub_activity_id": 1,
    "account_code": "5.1.02.01.01.0025",
    "description": "Belanja Kertas dan Cover",
    "unit": "rim",
    "unit_price": 33940,
    "total_volume": 10,
    "total_amount": 339400
  },
  "message": "Budget item berhasil dibuat"
}
```

---

### Monthly Plans (PLGK)

#### GET /sub-activities/{id}/monthly-plans
Get monthly plans for a sub-activity.

**Query Parameters:**
- `year` - Filter by year (default: current year)

**Response (200):**
```json
{
  "success": true,
  "data": {
    "sub_activity_id": 1,
    "category": "LAYANAN",
    "total_budget": 918886900,
    "monthly_plans": [
      {
        "month": 1,
        "items": [
          {
            "budget_item_id": 1,
            "description": "Belanja Kertas",
            "planned_volume": 0.83,
            "planned_amount": 28284
          }
        ],
        "total": 76573908
      }
    ]
  }
}
```

#### POST /sub-activities/{id}/monthly-plans/generate
Generate monthly plans dari DPA.

**Request:**
```json
{
  "year": 2026,
  "method": "equal",
  "custom_distribution": null
}
```

**method options:**
- `equal` - Divide equally by 12
- `custom` - Custom per month (requires `custom_distribution`)

**Response (201):**
```json
{
  "success": true,
  "data": {
    "generated_count": 144,
    "total_planned": 918886900
  },
  "message": "PLGK berhasil di-generate untuk 12 bulan"
}
```

---

## 6. REALIZATION ENDPOINTS

### GET /realizations
List realizations with filters.

**Query Parameters:**
- `filter[month]` - Filter by month (1-12)
- `filter[year]` - Filter by year
- `filter[status]` - Filter by status
- `filter[category]` - Filter by category
- `include` - Include relations: `budget_item,documents,submitter`

**Response (200):**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "budget_item_id": 1,
      "month": 11,
      "year": 2026,
      "realization_volume": 10,
      "realization_unit_price": 34000,
      "realization_amount": 340000,
      "status": "APPROVED",
      "notes": "Pembelian sesuai rencana",
      "budget_item": {
        "id": 1,
        "description": "Belanja Kertas",
        "unit": "rim",
        "unit_price": 33940
      },
      "documents": [
        {
          "id": 1,
          "document_type": "RECEIPT",
          "file_name": "kwitansi.pdf",
          "file_url": "/storage/documents/abc123_kwitansi.pdf"
        }
      ],
      "submitter": {
        "id": 5,
        "name": "John Doe"
      },
      "input_date": "2026-11-05T10:30:00Z"
    }
  ],
  "meta": { ... }
}
```

---

### POST /realizations
Create new realization.

**Headers:**
- `Content-Type: multipart/form-data`
- `Authorization: Bearer {token}`

**Request:**
```
budget_item_id: 1
month: 11
year: 2026
realization_volume: 10
realization_unit_price: 34000
notes: "Pembelian sesuai rencana"
documents[0]: (file) kwitansi.pdf
documents[1]: (file) foto_barang.jpg
```

**Response (201):**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "budget_item_id": 1,
    "month": 11,
    "year": 2026,
    "realization_volume": 10,
    "realization_unit_price": 34000,
    "realization_amount": 340000,
    "status": "DRAFT",
    "documents": [
      {
        "id": 1,
        "document_type": "RECEIPT",
        "file_name": "kwitansi.pdf"
      }
    ],
    "created_at": "2026-11-05T10:30:00Z"
  },
  "message": "Realisasi berhasil disimpan sebagai draft"
}
```

---

### POST /realizations/{id}/submit
Submit realization for verification.

**Response (200):**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "status": "SUBMITTED",
    "submitted_at": "2026-11-05T10:35:00Z"
  },
  "message": "Realisasi berhasil disubmit untuk verifikasi"
}
```

---

### POST /realizations/{id}/verify
Verify realization (Bendahara only).

**Request:**
```json
{
  "action": "approve",
  "notes": "Dokumen lengkap, harga wajar"
}
```

**action options:** `approve`, `reject`

**Response (200):**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "status": "VERIFIED",
    "verified_by": 3,
    "verified_at": "2026-11-05T14:00:00Z"
  },
  "message": "Realisasi berhasil diverifikasi"
}
```

---

### POST /realizations/{id}/approve
Approve realization (Kadis only).

**Request:**
```json
{
  "action": "approve",
  "notes": "Disetujui"
}
```

**Response (200):**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "status": "APPROVED",
    "approved_by": 1,
    "approved_at": "2026-11-06T09:00:00Z"
  },
  "message": "Realisasi berhasil diapprove"
}
```

---

### GET /realizations/pending-verification
Get realizations pending verification (Bendahara).

**Response (200):**
```json
{
  "success": true,
  "data": [
    {
      "id": 2,
      "item_description": "ATK",
      "category": "LAYANAN",
      "month": 11,
      "realization_amount": 500000,
      "deviation_percent": 5.0,
      "submitter": "John Doe",
      "submitted_at": "2026-11-05T11:00:00Z"
    }
  ],
  "meta": {
    "total": 5
  }
}
```

---

### GET /realizations/pending-approval
Get realizations pending approval (Kadis).

**Response (200):**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "item_description": "Kertas & Cover",
      "category": "LAYANAN",
      "month": 11,
      "realization_amount": 340000,
      "verified_by": "Bendahara - I Wayan",
      "verified_at": "2026-11-05T14:00:00Z"
    }
  ],
  "meta": {
    "total": 3
  }
}
```

---

### POST /realizations/bulk-approve
Batch approve multiple realizations (Kadis).

**Request:**
```json
{
  "realization_ids": [1, 2, 3],
  "notes": "Batch approval untuk bulan November"
}
```

**Response (200):**
```json
{
  "success": true,
  "data": {
    "approved": 3,
    "failed": 0
  },
  "message": "3 realisasi berhasil diapprove"
}
```

---

## 7. REPORTING ENDPOINTS

### POST /reports/monthly
Generate monthly report.

**Request:**
```json
{
  "month": 11,
  "year": 2026,
  "category": null,
  "format": "preview"
}
```

**format options:** `preview`, `pdf`, `excel`

**Response (200) - Preview:**
```json
{
  "success": true,
  "data": {
    "report_title": "Laporan Realisasi Fisik dan Keuangan - November 2026",
    "period": "November 2026",
    "summary": {
      "total_budget": 1385437875,
      "total_realized": 856000000,
      "absorption_rate": 61.8
    },
    "details": [
      {
        "code": "5.1.02.01.01.0025",
        "description": "Belanja Kertas",
        "planned_volume": 10,
        "planned_amount": 339400,
        "realized_volume": 10,
        "realized_amount": 340000,
        "deviation_percent": 0.2
      }
    ],
    "charts": {
      "monthly_trend": { ... },
      "category_breakdown": { ... }
    }
  }
}
```

**Response (200) - PDF/Excel:**
```json
{
  "success": true,
  "data": {
    "file_url": "/storage/reports/monthly_nov_2026_abc123.pdf",
    "file_name": "Laporan_Bulanan_November_2026.pdf",
    "file_size": 524288,
    "expires_at": "2026-11-08T10:30:00Z"
  },
  "message": "Laporan berhasil di-generate"
}
```

---

### POST /reports/custom
Generate custom report.

**Request:**
```json
{
  "title": "Laporan Custom",
  "date_from": "2026-01-01",
  "date_to": "2026-11-30",
  "categories": ["LAYANAN", "ANALISIS"],
  "columns": ["code", "description", "planned", "realized", "deviation"],
  "group_by": "category",
  "format": "excel"
}
```

---

## 8. NOTIFICATION ENDPOINTS

### GET /notifications
Get user notifications.

**Query Parameters:**
- `filter[is_read]` - Filter read/unread (boolean)
- `filter[type]` - Filter by type

**Response (200):**
```json
{
  "success": true,
  "data": [
    {
      "id": "550e8400-e29b-41d4-a716-446655440000",
      "type": "APPROVAL_REQUEST",
      "title": "Permohonan Approval",
      "message": "Realisasi Kertas & Cover menunggu approval Anda",
      "link": "/realizations/1",
      "is_read": false,
      "created_at": "2026-11-05T14:00:00Z"
    }
  ],
  "meta": {
    "total": 10,
    "unread": 5
  }
}
```

### POST /notifications/{id}/read
Mark notification as read.

**Response (200):**
```json
{
  "success": true,
  "message": "Notifikasi ditandai sudah dibaca"
}
```

### POST /notifications/mark-all-read
Mark all notifications as read.

**Response (200):**
```json
{
  "success": true,
  "data": {
    "marked_count": 5
  },
  "message": "5 notifikasi ditandai sudah dibaca"
}
```

---

## 9. ERROR CODES

| Code | Message | Description |
|------|---------|-------------|
| AUTH001 | Token tidak valid | JWT token invalid atau expired |
| AUTH002 | Token tidak ditemukan | Missing Authorization header |
| AUTH003 | Akun terkunci | Account locked after 5 failed attempts |
| PERM001 | Tidak memiliki akses | User doesn't have permission |
| PERM002 | Hanya bisa akses data sendiri | Tim Pelaksana accessing other category |
| VAL001 | Validasi gagal | Request validation failed |
| VAL002 | File terlalu besar | File exceeds 5MB limit |
| VAL003 | Format file tidak didukung | Invalid file type |
| DATA001 | Data tidak ditemukan | Resource not found |
| DATA002 | Data sudah terkunci | Trying to edit approved data |
| FLOW001 | Status tidak valid | Invalid status transition |
| FLOW002 | Dokumen tidak lengkap | Missing required documents |

---

**Document Status:** Complete
**Last Updated:** 8 Januari 2026
