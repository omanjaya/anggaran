# Database Schema Design
## SIPERA - Sistem Informasi Perencanaan dan Realisasi Anggaran

**Version:** 1.0
**Date:** 8 Januari 2026
**Database:** PostgreSQL 16

---

## 1. ENTITY RELATIONSHIP DIAGRAM (ERD)

```
┌─────────────────────────────────────────────────────────────────────────────────────────┐
│                                         ERD                                              │
├─────────────────────────────────────────────────────────────────────────────────────────┤
│                                                                                          │
│   ┌──────────────┐         ┌──────────────┐         ┌──────────────┐                    │
│   │   programs   │────1:N──│  activities  │────1:N──│sub_activities│                    │
│   │──────────────│         │──────────────│         │──────────────│                    │
│   │ id (PK)      │         │ id (PK)      │         │ id (PK)      │                    │
│   │ code         │         │ program_id   │         │ activity_id  │                    │
│   │ name         │         │ code         │         │ category     │                    │
│   │ year         │         │ name         │         │ name         │                    │
│   │ description  │         │ location     │         │ budget_*     │                    │
│   │ status       │         └──────────────┘         └───────┬──────┘                    │
│   └──────────────┘                                          │                           │
│                                                             │ 1:N                       │
│   ┌──────────────┐                                          │                           │
│   │ account_codes│                                          ▼                           │
│   │──────────────│                                  ┌──────────────┐                    │
│   │ id (PK)      │                                  │ budget_items │                    │
│   │ code         │──────────────────────────────────│──────────────│                    │
│   │ description  │                                  │ id (PK)      │                    │
│   │ level        │                                  │sub_activity_id                    │
│   │ parent_code  │                                  │ account_code │                    │
│   │ is_active    │                                  │ description  │                    │
│   └──────────────┘                                  │ unit         │                    │
│                                                     │ unit_price   │                    │
│                                                     │ total_volume │                    │
│                                                     │ total_amount │                    │
│                                                     └───────┬──────┘                    │
│                                                             │                           │
│                                           ┌─────────────────┼─────────────────┐         │
│                                           │ 1:N             │ 1:N             │ 1:N     │
│                                           ▼                 ▼                 ▼         │
│                                   ┌──────────────┐  ┌──────────────┐  ┌────────────────┐│
│                                   │monthly_plans │  │monthly_      │  │operational_    ││
│                                   │──────────────│  │realizations  │  │schedules       ││
│                                   │ id (PK)      │  │──────────────│  │────────────────││
│                                   │budget_item_id│  │ id (PK)      │  │ id (PK)        ││
│                                   │ month        │  │budget_item_id│  │ budget_item_id ││
│                                   │ year         │  │ month, year  │  │ month, year    ││
│                                   │planned_volume│  │ status       │  │ scheduled_date ││
│                                   │planned_amount│  │ volume       │  │ pic_user_id    ││
│                                   └──────────────┘  │ unit_price   │  │ status         ││
│                                                     │ amount       │  └────────────────┘│
│                                                     │ input_by     │                    │
│                                                     │verified_by   │                    │
│                                                     │approved_by   │                    │
│                                                     └───────┬──────┘                    │
│                                                             │ 1:N                       │
│   ┌──────────────┐                                          ▼                           │
│   │    users     │                                  ┌──────────────┐                    │
│   │──────────────│                                  │ realization_ │                    │
│   │ id (PK)      │──────────────────────────────────│  documents   │                    │
│   │ nip          │                                  │──────────────│                    │
│   │ name         │                                  │ id (PK)      │                    │
│   │ email        │                                  │realization_id│                    │
│   │ password_hash│                                  │document_type │                    │
│   │ role         │                                  │ file_name    │                    │
│   │sub_activity_id                                  │ file_path    │                    │
│   │ is_active    │                                  │ file_size    │                    │
│   └───────┬──────┘                                  │ uploaded_by  │                    │
│           │                                         └──────────────┘                    │
│           │ 1:N                                                                         │
│           ▼                                                                             │
│   ┌──────────────┐         ┌──────────────┐                                             │
│   │notifications │         │ audit_logs   │                                             │
│   │──────────────│         │──────────────│                                             │
│   │ id (PK)      │         │ id (PK)      │                                             │
│   │ user_id      │         │ user_id      │                                             │
│   │ type         │         │ action       │                                             │
│   │ title        │         │ table_name   │                                             │
│   │ message      │         │ record_id    │                                             │
│   │ link         │         │ old_values   │                                             │
│   │ is_read      │         │ new_values   │                                             │
│   └──────────────┘         │ ip_address   │                                             │
│                            └──────────────┘                                             │
│                                                                                          │
└─────────────────────────────────────────────────────────────────────────────────────────┘
```

---

## 2. TABLE DEFINITIONS

### 2.1 Master Tables

#### programs
```sql
CREATE TABLE programs (
    id SERIAL PRIMARY KEY,
    code VARCHAR(20) NOT NULL,
    name VARCHAR(255) NOT NULL,
    year INT NOT NULL DEFAULT EXTRACT(YEAR FROM CURRENT_DATE),
    description TEXT,
    status VARCHAR(20) DEFAULT 'active' CHECK (status IN ('active', 'archived')),
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW(),
    deleted_at TIMESTAMP,

    CONSTRAINT uk_programs_code_year UNIQUE (code, year)
);

COMMENT ON TABLE programs IS 'Master data program anggaran';
COMMENT ON COLUMN programs.code IS 'Kode program (e.g., 2.21.02)';
COMMENT ON COLUMN programs.status IS 'Status: active, archived';
```

#### activities
```sql
CREATE TABLE activities (
    id SERIAL PRIMARY KEY,
    program_id INT NOT NULL REFERENCES programs(id) ON DELETE CASCADE,
    code VARCHAR(20) NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    location VARCHAR(255) DEFAULT 'Semua Kabupaten/Kota di Provinsi Bali',
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW(),
    deleted_at TIMESTAMP,

    CONSTRAINT uk_activities_program_code UNIQUE (program_id, code)
);

CREATE INDEX idx_activities_program_id ON activities(program_id);

COMMENT ON TABLE activities IS 'Master data kegiatan dalam program';
COMMENT ON COLUMN activities.code IS 'Kode kegiatan (e.g., 2.21.02.1.01)';
```

#### sub_activities
```sql
CREATE TABLE sub_activities (
    id SERIAL PRIMARY KEY,
    activity_id INT NOT NULL REFERENCES activities(id) ON DELETE CASCADE,
    category VARCHAR(50) NOT NULL,
    name VARCHAR(255) NOT NULL,
    budget_prev_year DECIMAL(15,2) DEFAULT 0,
    budget_current_year DECIMAL(15,2) NOT NULL,
    budget_next_year DECIMAL(15,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW(),
    deleted_at TIMESTAMP,

    CONSTRAINT chk_sub_activities_category CHECK (
        category IN ('ANALISIS', 'TATA_KELOLA', 'OPERASIONALISASI', 'LAYANAN', 'ELEK_NON_ELEK')
    ),
    CONSTRAINT uk_sub_activities_activity_category UNIQUE (activity_id, category)
);

CREATE INDEX idx_sub_activities_activity_id ON sub_activities(activity_id);
CREATE INDEX idx_sub_activities_category ON sub_activities(category);

COMMENT ON TABLE sub_activities IS 'Master data sub-kegiatan (5 kategori)';
COMMENT ON COLUMN sub_activities.category IS '5 kategori: ANALISIS, TATA_KELOLA, OPERASIONALISASI, LAYANAN, ELEK_NON_ELEK';
```

#### account_codes
```sql
CREATE TABLE account_codes (
    id SERIAL PRIMARY KEY,
    code VARCHAR(50) UNIQUE NOT NULL,
    description VARCHAR(500) NOT NULL,
    level INT NOT NULL CHECK (level BETWEEN 1 AND 5),
    parent_code VARCHAR(50) REFERENCES account_codes(code),
    is_active BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

CREATE INDEX idx_account_codes_code ON account_codes(code);
CREATE INDEX idx_account_codes_parent_code ON account_codes(parent_code);
CREATE INDEX idx_account_codes_level ON account_codes(level);
CREATE INDEX idx_account_codes_description_gin ON account_codes
    USING gin(to_tsvector('indonesian', description));

COMMENT ON TABLE account_codes IS 'Master kode rekening (Chart of Accounts)';
COMMENT ON COLUMN account_codes.code IS 'Format: 5.1.02.01.01.0025 (5 level)';
COMMENT ON COLUMN account_codes.level IS 'Level hierarki: 1-5';
```

---

### 2.2 Planning Tables

#### budget_items
```sql
CREATE TABLE budget_items (
    id SERIAL PRIMARY KEY,
    sub_activity_id INT NOT NULL REFERENCES sub_activities(id) ON DELETE CASCADE,
    account_code VARCHAR(50) NOT NULL REFERENCES account_codes(code),
    description TEXT NOT NULL,
    unit VARCHAR(50) NOT NULL,
    unit_price DECIMAL(15,2) NOT NULL CHECK (unit_price >= 0),
    total_volume DECIMAL(10,2) NOT NULL CHECK (total_volume > 0),
    total_amount DECIMAL(15,2) NOT NULL CHECK (total_amount >= 0),
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW(),
    deleted_at TIMESTAMP
);

CREATE INDEX idx_budget_items_sub_activity_id ON budget_items(sub_activity_id);
CREATE INDEX idx_budget_items_account_code ON budget_items(account_code);
CREATE INDEX idx_budget_items_description_gin ON budget_items
    USING gin(to_tsvector('indonesian', description));

COMMENT ON TABLE budget_items IS 'Detail item belanja per DPA';
COMMENT ON COLUMN budget_items.unit IS 'Satuan: rim, buah, paket, OH, OJ, dll';
COMMENT ON COLUMN budget_items.total_amount IS 'Auto-calculated: unit_price * total_volume';
```

#### monthly_plans
```sql
CREATE TABLE monthly_plans (
    id SERIAL PRIMARY KEY,
    budget_item_id INT NOT NULL REFERENCES budget_items(id) ON DELETE CASCADE,
    month INT NOT NULL CHECK (month BETWEEN 1 AND 12),
    year INT NOT NULL,
    planned_volume DECIMAL(10,2) NOT NULL DEFAULT 0,
    planned_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW(),

    CONSTRAINT uk_monthly_plans_item_month_year UNIQUE (budget_item_id, month, year)
);

CREATE INDEX idx_monthly_plans_budget_item_id ON monthly_plans(budget_item_id);
CREATE INDEX idx_monthly_plans_month_year ON monthly_plans(month, year);

COMMENT ON TABLE monthly_plans IS 'Rencana bulanan per item (PLGK)';
```

#### operational_schedules
```sql
CREATE TABLE operational_schedules (
    id SERIAL PRIMARY KEY,
    budget_item_id INT NOT NULL REFERENCES budget_items(id) ON DELETE CASCADE,
    month INT NOT NULL CHECK (month BETWEEN 1 AND 12),
    year INT NOT NULL,
    scheduled_date DATE,
    description TEXT,
    pic_user_id INT REFERENCES users(id),
    status VARCHAR(20) DEFAULT 'planned',
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW(),

    CONSTRAINT chk_operational_schedules_status CHECK (
        status IN ('planned', 'in_progress', 'completed', 'cancelled')
    )
);

CREATE INDEX idx_operational_schedules_budget_item_id ON operational_schedules(budget_item_id);
CREATE INDEX idx_operational_schedules_month_year ON operational_schedules(month, year);
CREATE INDEX idx_operational_schedules_pic_user_id ON operational_schedules(pic_user_id);
CREATE INDEX idx_operational_schedules_status ON operational_schedules(status);

COMMENT ON TABLE operational_schedules IS 'Jadwal operasional kegiatan (ROK OP)';
```

---

### 2.3 Realization Tables

#### monthly_realizations
```sql
CREATE TABLE monthly_realizations (
    id SERIAL PRIMARY KEY,
    budget_item_id INT NOT NULL REFERENCES budget_items(id) ON DELETE CASCADE,
    month INT NOT NULL CHECK (month BETWEEN 1 AND 12),
    year INT NOT NULL,
    realization_volume DECIMAL(10,2) NOT NULL CHECK (realization_volume >= 0),
    realization_unit_price DECIMAL(15,2) NOT NULL CHECK (realization_unit_price >= 0),
    realization_amount DECIMAL(15,2) NOT NULL CHECK (realization_amount >= 0),
    status VARCHAR(20) DEFAULT 'DRAFT',
    notes TEXT,
    deviation_reason TEXT,

    -- Input tracking
    input_by INT REFERENCES users(id),
    input_date TIMESTAMP DEFAULT NOW(),

    -- Verification tracking (Bendahara)
    verified_by INT REFERENCES users(id),
    verified_date TIMESTAMP,
    verification_notes TEXT,

    -- Approval tracking (Kadis)
    approved_by INT REFERENCES users(id),
    approved_date TIMESTAMP,
    approval_notes TEXT,

    -- Rejection tracking
    rejected_by INT REFERENCES users(id),
    rejected_date TIMESTAMP,
    rejection_reason TEXT,

    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW(),

    CONSTRAINT chk_monthly_realizations_status CHECK (
        status IN ('DRAFT', 'SUBMITTED', 'VERIFIED', 'APPROVED', 'REJECTED')
    ),
    CONSTRAINT uk_monthly_realizations_item_month_year UNIQUE (budget_item_id, month, year)
);

CREATE INDEX idx_monthly_realizations_budget_item_id ON monthly_realizations(budget_item_id);
CREATE INDEX idx_monthly_realizations_month_year ON monthly_realizations(month, year);
CREATE INDEX idx_monthly_realizations_status ON monthly_realizations(status);
CREATE INDEX idx_monthly_realizations_input_by ON monthly_realizations(input_by);
CREATE INDEX idx_monthly_realizations_verified_by ON monthly_realizations(verified_by);
CREATE INDEX idx_monthly_realizations_approved_by ON monthly_realizations(approved_by);

COMMENT ON TABLE monthly_realizations IS 'Data realisasi anggaran bulanan';
COMMENT ON COLUMN monthly_realizations.status IS 'DRAFT, SUBMITTED, VERIFIED, APPROVED, REJECTED';
COMMENT ON COLUMN monthly_realizations.deviation_reason IS 'Alasan jika realisasi >110% dari rencana';
```

#### realization_documents
```sql
CREATE TABLE realization_documents (
    id SERIAL PRIMARY KEY,
    realization_id INT NOT NULL REFERENCES monthly_realizations(id) ON DELETE CASCADE,
    document_type VARCHAR(50) NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size INT NOT NULL,
    mime_type VARCHAR(100),
    uploaded_by INT REFERENCES users(id),
    uploaded_at TIMESTAMP DEFAULT NOW(),

    CONSTRAINT chk_realization_documents_type CHECK (
        document_type IN ('RECEIPT', 'INVOICE', 'PHOTO', 'CONTRACT', 'OTHER')
    )
);

CREATE INDEX idx_realization_documents_realization_id ON realization_documents(realization_id);
CREATE INDEX idx_realization_documents_document_type ON realization_documents(document_type);

COMMENT ON TABLE realization_documents IS 'Dokumen pendukung realisasi';
COMMENT ON COLUMN realization_documents.document_type IS 'RECEIPT, INVOICE, PHOTO, CONTRACT, OTHER';
COMMENT ON COLUMN realization_documents.file_size IS 'Ukuran file dalam bytes';
```

---

### 2.4 User & System Tables

#### users
```sql
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
    email_verified_at TIMESTAMP,
    last_login TIMESTAMP,
    remember_token VARCHAR(100),
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW(),
    deleted_at TIMESTAMP,

    CONSTRAINT chk_users_role CHECK (
        role IN ('ADMIN', 'KADIS', 'TIM_PERENCANAAN', 'TIM_PELAKSANA', 'BENDAHARA', 'MONEV', 'VIEWER')
    )
);

CREATE INDEX idx_users_nip ON users(nip);
CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_users_role ON users(role);
CREATE INDEX idx_users_sub_activity_id ON users(sub_activity_id);
CREATE INDEX idx_users_is_active ON users(id) WHERE is_active = true;

COMMENT ON TABLE users IS 'Master data pengguna sistem';
COMMENT ON COLUMN users.nip IS 'Nomor Induk Pegawai (18 digit)';
COMMENT ON COLUMN users.role IS 'Role: ADMIN, KADIS, TIM_PERENCANAAN, TIM_PELAKSANA, BENDAHARA, MONEV, VIEWER';
COMMENT ON COLUMN users.sub_activity_id IS 'Kategori yang di-assign (untuk TIM_PELAKSANA)';
```

#### user_permissions (Optional - for fine-grained permissions)
```sql
CREATE TABLE user_permissions (
    id SERIAL PRIMARY KEY,
    user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    sub_activity_id INT NOT NULL REFERENCES sub_activities(id) ON DELETE CASCADE,
    can_view BOOLEAN DEFAULT true,
    can_input BOOLEAN DEFAULT false,
    can_verify BOOLEAN DEFAULT false,
    can_approve BOOLEAN DEFAULT false,
    created_at TIMESTAMP DEFAULT NOW(),

    CONSTRAINT uk_user_permissions_user_sub_activity UNIQUE (user_id, sub_activity_id)
);

CREATE INDEX idx_user_permissions_user_id ON user_permissions(user_id);
CREATE INDEX idx_user_permissions_sub_activity_id ON user_permissions(sub_activity_id);

COMMENT ON TABLE user_permissions IS 'Mapping permission user ke sub-kegiatan';
```

#### notifications
```sql
CREATE TABLE notifications (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    type VARCHAR(50) NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    data JSONB,
    link VARCHAR(500),
    is_read BOOLEAN DEFAULT false,
    read_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT NOW(),

    CONSTRAINT chk_notifications_type CHECK (
        type IN ('DEADLINE', 'APPROVAL_REQUEST', 'APPROVAL_RESULT', 'DEVIATION_ALERT', 'SYSTEM')
    )
);

CREATE INDEX idx_notifications_user_id ON notifications(user_id);
CREATE INDEX idx_notifications_user_unread ON notifications(user_id, created_at) WHERE is_read = false;
CREATE INDEX idx_notifications_created_at ON notifications(created_at);

COMMENT ON TABLE notifications IS 'Notifikasi untuk user';
COMMENT ON COLUMN notifications.data IS 'Additional data dalam format JSON';
```

#### audit_logs
```sql
CREATE TABLE audit_logs (
    id BIGSERIAL PRIMARY KEY,
    user_id INT REFERENCES users(id),
    action VARCHAR(100) NOT NULL,
    table_name VARCHAR(50) NOT NULL,
    record_id INT,
    old_values JSONB,
    new_values JSONB,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT NOW()
) PARTITION BY RANGE (created_at);

-- Create partitions for 2026
CREATE TABLE audit_logs_2026_q1 PARTITION OF audit_logs
    FOR VALUES FROM ('2026-01-01') TO ('2026-04-01');
CREATE TABLE audit_logs_2026_q2 PARTITION OF audit_logs
    FOR VALUES FROM ('2026-04-01') TO ('2026-07-01');
CREATE TABLE audit_logs_2026_q3 PARTITION OF audit_logs
    FOR VALUES FROM ('2026-07-01') TO ('2026-10-01');
CREATE TABLE audit_logs_2026_q4 PARTITION OF audit_logs
    FOR VALUES FROM ('2026-10-01') TO ('2027-01-01');

CREATE INDEX idx_audit_logs_user_id ON audit_logs(user_id);
CREATE INDEX idx_audit_logs_table_name ON audit_logs(table_name);
CREATE INDEX idx_audit_logs_created_at ON audit_logs(created_at);

COMMENT ON TABLE audit_logs IS 'Log audit semua perubahan data (partitioned by quarter)';
COMMENT ON COLUMN audit_logs.action IS 'CREATE, UPDATE, DELETE, APPROVE, REJECT, etc.';
```

---

## 3. VIEWS

### 3.1 Budget Summary View
```sql
CREATE OR REPLACE VIEW v_budget_summary AS
SELECT
    sa.id AS sub_activity_id,
    sa.category,
    sa.name AS sub_activity_name,
    sa.budget_current_year,
    COALESCE(SUM(bi.total_amount), 0) AS total_planned,
    COALESCE(SUM(mr.realization_amount), 0) AS total_realized,
    CASE
        WHEN sa.budget_current_year > 0
        THEN ROUND((COALESCE(SUM(mr.realization_amount), 0) / sa.budget_current_year * 100)::numeric, 2)
        ELSE 0
    END AS absorption_percentage
FROM sub_activities sa
LEFT JOIN budget_items bi ON bi.sub_activity_id = sa.id AND bi.deleted_at IS NULL
LEFT JOIN monthly_realizations mr ON mr.budget_item_id = bi.id AND mr.status = 'APPROVED'
WHERE sa.deleted_at IS NULL
GROUP BY sa.id, sa.category, sa.name, sa.budget_current_year;

COMMENT ON VIEW v_budget_summary IS 'Ringkasan anggaran per sub-kegiatan';
```

### 3.2 Monthly Progress View
```sql
CREATE OR REPLACE VIEW v_monthly_progress AS
SELECT
    sa.category,
    mp.month,
    mp.year,
    SUM(mp.planned_amount) AS monthly_plan,
    COALESCE(SUM(mr.realization_amount), 0) AS monthly_realization,
    CASE
        WHEN SUM(mp.planned_amount) > 0
        THEN ROUND((COALESCE(SUM(mr.realization_amount), 0) / SUM(mp.planned_amount) * 100)::numeric, 2)
        ELSE 0
    END AS achievement_percentage
FROM sub_activities sa
JOIN budget_items bi ON bi.sub_activity_id = sa.id AND bi.deleted_at IS NULL
JOIN monthly_plans mp ON mp.budget_item_id = bi.id
LEFT JOIN monthly_realizations mr ON mr.budget_item_id = bi.id
    AND mr.month = mp.month
    AND mr.year = mp.year
    AND mr.status = 'APPROVED'
WHERE sa.deleted_at IS NULL
GROUP BY sa.category, mp.month, mp.year
ORDER BY mp.year, mp.month, sa.category;

COMMENT ON VIEW v_monthly_progress IS 'Progress bulanan per kategori';
```

### 3.3 Pending Approvals View
```sql
CREATE OR REPLACE VIEW v_pending_approvals AS
SELECT
    mr.id AS realization_id,
    mr.status,
    mr.month,
    mr.year,
    mr.realization_amount,
    bi.description AS item_description,
    sa.category,
    u.name AS submitted_by,
    mr.input_date,
    CASE
        WHEN mr.status = 'SUBMITTED' THEN 'Menunggu Verifikasi Bendahara'
        WHEN mr.status = 'VERIFIED' THEN 'Menunggu Approval Kadis'
        ELSE mr.status
    END AS status_description
FROM monthly_realizations mr
JOIN budget_items bi ON bi.id = mr.budget_item_id
JOIN sub_activities sa ON sa.id = bi.sub_activity_id
LEFT JOIN users u ON u.id = mr.input_by
WHERE mr.status IN ('SUBMITTED', 'VERIFIED')
ORDER BY mr.input_date ASC;

COMMENT ON VIEW v_pending_approvals IS 'Daftar realisasi yang menunggu approval';
```

---

## 4. TRIGGERS

### 4.1 Auto-calculate Total Amount
```sql
CREATE OR REPLACE FUNCTION fn_calculate_total_amount()
RETURNS TRIGGER AS $$
BEGIN
    NEW.total_amount := NEW.unit_price * NEW.total_volume;
    NEW.updated_at := NOW();
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_budget_items_calculate_total
    BEFORE INSERT OR UPDATE ON budget_items
    FOR EACH ROW
    EXECUTE FUNCTION fn_calculate_total_amount();
```

### 4.2 Auto-calculate Realization Amount
```sql
CREATE OR REPLACE FUNCTION fn_calculate_realization_amount()
RETURNS TRIGGER AS $$
BEGIN
    NEW.realization_amount := NEW.realization_unit_price * NEW.realization_volume;
    NEW.updated_at := NOW();
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_realizations_calculate_amount
    BEFORE INSERT OR UPDATE ON monthly_realizations
    FOR EACH ROW
    EXECUTE FUNCTION fn_calculate_realization_amount();
```

### 4.3 Audit Log Trigger
```sql
CREATE OR REPLACE FUNCTION fn_audit_log()
RETURNS TRIGGER AS $$
BEGIN
    IF TG_OP = 'INSERT' THEN
        INSERT INTO audit_logs (user_id, action, table_name, record_id, new_values, created_at)
        VALUES (current_setting('app.current_user_id', true)::int, 'CREATE', TG_TABLE_NAME, NEW.id, to_jsonb(NEW), NOW());
    ELSIF TG_OP = 'UPDATE' THEN
        INSERT INTO audit_logs (user_id, action, table_name, record_id, old_values, new_values, created_at)
        VALUES (current_setting('app.current_user_id', true)::int, 'UPDATE', TG_TABLE_NAME, NEW.id, to_jsonb(OLD), to_jsonb(NEW), NOW());
    ELSIF TG_OP = 'DELETE' THEN
        INSERT INTO audit_logs (user_id, action, table_name, record_id, old_values, created_at)
        VALUES (current_setting('app.current_user_id', true)::int, 'DELETE', TG_TABLE_NAME, OLD.id, to_jsonb(OLD), NOW());
    END IF;
    RETURN NULL;
END;
$$ LANGUAGE plpgsql;

-- Apply to main tables
CREATE TRIGGER trg_audit_monthly_realizations
    AFTER INSERT OR UPDATE OR DELETE ON monthly_realizations
    FOR EACH ROW EXECUTE FUNCTION fn_audit_log();

CREATE TRIGGER trg_audit_budget_items
    AFTER INSERT OR UPDATE OR DELETE ON budget_items
    FOR EACH ROW EXECUTE FUNCTION fn_audit_log();
```

---

## 5. MIGRATION ORDER

Urutan migrasi berdasarkan dependensi:

1. `account_codes` (no dependencies)
2. `programs` (no dependencies)
3. `activities` (depends on: programs)
4. `sub_activities` (depends on: activities)
5. `users` (depends on: sub_activities - optional)
6. `user_permissions` (depends on: users, sub_activities)
7. `budget_items` (depends on: sub_activities, account_codes)
8. `monthly_plans` (depends on: budget_items)
9. `operational_schedules` (depends on: budget_items, users)
10. `monthly_realizations` (depends on: budget_items, users)
11. `realization_documents` (depends on: monthly_realizations, users)
12. `notifications` (depends on: users)
13. `audit_logs` (depends on: users)

---

## 6. SAMPLE DATA

```sql
-- Sample Program
INSERT INTO programs (code, name, year, description, status) VALUES
('2.21.02', 'Program Penyelenggaraan Persandian untuk Pengamanan Informasi', 2026,
 'Program penyelenggaraan persandian di lingkungan Pemerintah Provinsi Bali', 'active');

-- Sample Activity
INSERT INTO activities (program_id, code, name, location) VALUES
(1, '2.21.02.1.01', 'Kegiatan Persandian dan Pengamanan Informasi',
 'Semua Kabupaten/Kota di Provinsi Bali');

-- Sample Sub-Activities (5 categories)
INSERT INTO sub_activities (activity_id, category, name, budget_current_year) VALUES
(1, 'ANALISIS', 'Analisis Keamanan Informasi', 104571400),
(1, 'TATA_KELOLA', 'Tata Kelola Keamanan Informasi', 53683500),
(1, 'OPERASIONALISASI', 'Operasionalisasi Keamanan Informasi', 94371800),
(1, 'LAYANAN', 'Layanan Keamanan Informasi', 918886900),
(1, 'ELEK_NON_ELEK', 'Peralatan Elektronik dan Non-Elektronik', 213924275);
```

---

**Document Status:** Complete
**Last Updated:** 8 Januari 2026
