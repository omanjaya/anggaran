-- ============================================
-- SIPERA Database Schema
-- PostgreSQL 16+
-- Created: 2026-01-08
-- ============================================

-- Create database
CREATE DATABASE sipera
    WITH 
    ENCODING = 'UTF8'
    LC_COLLATE = 'id_ID.UTF-8'
    LC_CTYPE = 'id_ID.UTF-8'
    TEMPLATE = template0;

\c sipera;

-- Enable extensions
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";
CREATE EXTENSION IF NOT EXISTS "pg_trgm";

-- ============================================
-- CORE TABLES
-- ============================================

-- Programs Table
CREATE TABLE programs (
    id SERIAL PRIMARY KEY,
    code VARCHAR(20) NOT NULL,
    name VARCHAR(255) NOT NULL,
    year INT NOT NULL,
    description TEXT,
    status VARCHAR(20) DEFAULT 'active' CHECK (status IN ('active', 'archived')),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(code, year)
);

COMMENT ON TABLE programs IS 'Master data program anggaran';
COMMENT ON COLUMN programs.code IS 'Kode program (contoh: 2.21.02)';
COMMENT ON COLUMN programs.year IS 'Tahun anggaran';

-- Activities Table
CREATE TABLE activities (
    id SERIAL PRIMARY KEY,
    program_id INT NOT NULL REFERENCES programs(id) ON DELETE CASCADE,
    code VARCHAR(20) NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    location VARCHAR(255) DEFAULT 'Semua Kabupaten/Kota, Semua Kecamatan, Semua Kelurahan',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(program_id, code)
);

COMMENT ON TABLE activities IS 'Master data kegiatan dalam program';
COMMENT ON COLUMN activities.code IS 'Kode kegiatan (contoh: 2.21.02.1.01)';

-- Sub-Activities Table
CREATE TABLE sub_activities (
    id SERIAL PRIMARY KEY,
    activity_id INT NOT NULL REFERENCES activities(id) ON DELETE CASCADE,
    category VARCHAR(50) NOT NULL CHECK (category IN ('ANALISIS', 'TATA_KELOLA', 'OPERASIONALISASI', 'LAYANAN', 'ELEK_NON_ELEK')),
    name VARCHAR(255) NOT NULL,
    budget_prev_year DECIMAL(15,2) DEFAULT 0,
    budget_current_year DECIMAL(15,2) NOT NULL,
    budget_next_year DECIMAL(15,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(activity_id, category)
);

COMMENT ON TABLE sub_activities IS 'Sub-kegiatan dengan kategori tertentu';
COMMENT ON COLUMN sub_activities.category IS 'Kategori sub-kegiatan (5 jenis)';

-- Account Codes Table (Kode Rekening)
CREATE TABLE account_codes (
    id SERIAL PRIMARY KEY,
    code VARCHAR(50) UNIQUE NOT NULL,
    description VARCHAR(500) NOT NULL,
    level INT NOT NULL CHECK (level BETWEEN 1 AND 5),
    parent_code VARCHAR(50) REFERENCES account_codes(code),
    is_active BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

COMMENT ON TABLE account_codes IS 'Master kode rekening belanja (hierarkis 5 level)';
COMMENT ON COLUMN account_codes.level IS 'Level hierarki: 1=Kelompok, 2=Jenis, 3=Objek, 4=Rincian Objek, 5=Sub Rincian Objek';

-- Budget Items Table
CREATE TABLE budget_items (
    id SERIAL PRIMARY KEY,
    sub_activity_id INT NOT NULL REFERENCES sub_activities(id) ON DELETE CASCADE,
    account_code VARCHAR(50) NOT NULL REFERENCES account_codes(code),
    description TEXT NOT NULL,
    unit VARCHAR(50) NOT NULL,
    unit_price DECIMAL(15,2) NOT NULL CHECK (unit_price >= 0),
    total_volume DECIMAL(10,2) NOT NULL CHECK (total_volume >= 0),
    total_amount DECIMAL(15,2) NOT NULL CHECK (total_amount >= 0),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

COMMENT ON TABLE budget_items IS 'Detail item belanja dalam DPA';
COMMENT ON COLUMN budget_items.unit IS 'Satuan (rim, buah, paket, orang, dll)';

-- ============================================
-- PLANNING TABLES
-- ============================================

-- Monthly Plans Table (PLGK)
CREATE TABLE monthly_plans (
    id SERIAL PRIMARY KEY,
    budget_item_id INT NOT NULL REFERENCES budget_items(id) ON DELETE CASCADE,
    month INT NOT NULL CHECK (month BETWEEN 1 AND 12),
    year INT NOT NULL CHECK (year BETWEEN 2020 AND 2100),
    planned_volume DECIMAL(10,2) NOT NULL CHECK (planned_volume >= 0),
    planned_amount DECIMAL(15,2) NOT NULL CHECK (planned_amount >= 0),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(budget_item_id, month, year)
);

COMMENT ON TABLE monthly_plans IS 'Rencana pelaksanaan per bulan (PLGK)';

-- Operational Schedules Table (ROK OP)
CREATE TABLE operational_schedules (
    id SERIAL PRIMARY KEY,
    budget_item_id INT NOT NULL REFERENCES budget_items(id) ON DELETE CASCADE,
    month INT NOT NULL CHECK (month BETWEEN 1 AND 12),
    year INT NOT NULL,
    scheduled_date DATE,
    description TEXT,
    pic_user_id INT, -- Will be linked to users table
    status VARCHAR(20) DEFAULT 'planned' CHECK (status IN ('planned', 'in_progress', 'completed', 'cancelled')),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

COMMENT ON TABLE operational_schedules IS 'Jadwal operasional pelaksanaan (ROK OP)';
COMMENT ON COLUMN operational_schedules.pic_user_id IS 'Penanggung jawab (PIC)';

-- ============================================
-- REALIZATION TABLES
-- ============================================

-- Monthly Realizations Table
CREATE TABLE monthly_realizations (
    id SERIAL PRIMARY KEY,
    budget_item_id INT NOT NULL REFERENCES budget_items(id) ON DELETE CASCADE,
    month INT NOT NULL CHECK (month BETWEEN 1 AND 12),
    year INT NOT NULL CHECK (year BETWEEN 2020 AND 2100),
    realization_volume DECIMAL(10,2) NOT NULL CHECK (realization_volume >= 0),
    realization_unit_price DECIMAL(15,2) NOT NULL CHECK (realization_unit_price >= 0),
    realization_amount DECIMAL(15,2) NOT NULL CHECK (realization_amount >= 0),
    status VARCHAR(20) DEFAULT 'DRAFT' CHECK (status IN ('DRAFT', 'SUBMITTED', 'VERIFIED', 'APPROVED', 'REJECTED')),
    notes TEXT,
    rejection_reason TEXT,
    
    -- Tracking
    input_by INT, -- Will be linked to users table
    input_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    verified_by INT, -- Will be linked to users table
    verified_date TIMESTAMP,
    approved_by INT, -- Will be linked to users table
    approved_date TIMESTAMP,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    UNIQUE(budget_item_id, month, year)
);

COMMENT ON TABLE monthly_realizations IS 'Data realisasi belanja per bulan';
COMMENT ON COLUMN monthly_realizations.status IS 'Status approval: DRAFT -> SUBMITTED -> VERIFIED -> APPROVED';

-- Realization Documents Table
CREATE TABLE realization_documents (
    id SERIAL PRIMARY KEY,
    realization_id INT NOT NULL REFERENCES monthly_realizations(id) ON DELETE CASCADE,
    document_type VARCHAR(50) NOT NULL CHECK (document_type IN ('RECEIPT', 'INVOICE', 'PHOTO', 'OTHER')),
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size INT NOT NULL,
    mime_type VARCHAR(100),
    uploaded_by INT, -- Will be linked to users table
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

COMMENT ON TABLE realization_documents IS 'Dokumen pendukung realisasi (kwitansi, invoice, foto)';

-- ============================================
-- USER MANAGEMENT TABLES
-- ============================================

-- Users Table
CREATE TABLE users (
    id SERIAL PRIMARY KEY,
    nip VARCHAR(20) UNIQUE NOT NULL,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    role VARCHAR(50) NOT NULL CHECK (role IN ('ADMIN', 'KADIS', 'TIM_PERENCANAAN', 'TIM_PELAKSANA', 'BENDAHARA', 'MONEV', 'VIEWER')),
    sub_activity_id INT REFERENCES sub_activities(id),
    is_active BOOLEAN DEFAULT true,
    last_login TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

COMMENT ON TABLE users IS 'Data pengguna sistem';
COMMENT ON COLUMN users.nip IS 'NIP (18 digit)';
COMMENT ON COLUMN users.role IS 'Role utama user';
COMMENT ON COLUMN users.sub_activity_id IS 'Sub-kegiatan yang dapat diakses (null = semua)';

-- User Permissions Table
CREATE TABLE user_permissions (
    id SERIAL PRIMARY KEY,
    user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    sub_activity_id INT NOT NULL REFERENCES sub_activities(id) ON DELETE CASCADE,
    can_view BOOLEAN DEFAULT true,
    can_input BOOLEAN DEFAULT false,
    can_verify BOOLEAN DEFAULT false,
    can_approve BOOLEAN DEFAULT false,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(user_id, sub_activity_id)
);

COMMENT ON TABLE user_permissions IS 'Granular permissions per user per sub-kegiatan';

-- Password Reset Tokens Table
CREATE TABLE password_reset_tokens (
    id SERIAL PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    token VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL
);

CREATE INDEX idx_password_reset_email ON password_reset_tokens(email);
CREATE INDEX idx_password_reset_token ON password_reset_tokens(token);

-- ============================================
-- NOTIFICATION & AUDIT TABLES
-- ============================================

-- Notifications Table
CREATE TABLE notifications (
    id SERIAL PRIMARY KEY,
    user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    type VARCHAR(50) NOT NULL CHECK (type IN ('DEADLINE', 'APPROVAL_REQUEST', 'APPROVAL_RESULT', 'DEVIATION_ALERT', 'SYSTEM')),
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    link VARCHAR(500),
    is_read BOOLEAN DEFAULT false,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    read_at TIMESTAMP
);

COMMENT ON TABLE notifications IS 'Notifikasi in-app untuk user';

-- Audit Logs Table
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
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

COMMENT ON TABLE audit_logs IS 'Log semua perubahan data untuk audit trail';

-- ============================================
-- ADD FOREIGN KEYS TO REALIZATIONS
-- ============================================

ALTER TABLE operational_schedules 
ADD CONSTRAINT fk_operational_schedules_pic 
FOREIGN KEY (pic_user_id) REFERENCES users(id);

ALTER TABLE monthly_realizations 
ADD CONSTRAINT fk_monthly_realizations_input_by 
FOREIGN KEY (input_by) REFERENCES users(id);

ALTER TABLE monthly_realizations 
ADD CONSTRAINT fk_monthly_realizations_verified_by 
FOREIGN KEY (verified_by) REFERENCES users(id);

ALTER TABLE monthly_realizations 
ADD CONSTRAINT fk_monthly_realizations_approved_by 
FOREIGN KEY (approved_by) REFERENCES users(id);

ALTER TABLE realization_documents 
ADD CONSTRAINT fk_realization_documents_uploaded_by 
FOREIGN KEY (uploaded_by) REFERENCES users(id);

-- ============================================
-- INDEXES FOR PERFORMANCE
-- ============================================

-- Primary key indexes (automatic)

-- Foreign key indexes
CREATE INDEX idx_activities_program_id ON activities(program_id);
CREATE INDEX idx_sub_activities_activity_id ON sub_activities(activity_id);
CREATE INDEX idx_budget_items_sub_activity_id ON budget_items(sub_activity_id);
CREATE INDEX idx_budget_items_account_code ON budget_items(account_code);
CREATE INDEX idx_monthly_plans_budget_item_id ON monthly_plans(budget_item_id);
CREATE INDEX idx_operational_schedules_budget_item_id ON operational_schedules(budget_item_id);
CREATE INDEX idx_monthly_realizations_budget_item_id ON monthly_realizations(budget_item_id);
CREATE INDEX idx_realization_documents_realization_id ON realization_documents(realization_id);
CREATE INDEX idx_user_permissions_user_id ON user_permissions(user_id);
CREATE INDEX idx_user_permissions_sub_activity_id ON user_permissions(sub_activity_id);
CREATE INDEX idx_notifications_user_id ON notifications(user_id);
CREATE INDEX idx_audit_logs_user_id ON audit_logs(user_id);

-- Composite indexes for common queries
CREATE INDEX idx_monthly_plans_item_month_year ON monthly_plans(budget_item_id, month, year);
CREATE INDEX idx_monthly_realizations_item_month_year ON monthly_realizations(budget_item_id, month, year);
CREATE INDEX idx_monthly_realizations_status ON monthly_realizations(status);
CREATE INDEX idx_monthly_realizations_month_year ON monthly_realizations(month, year);
CREATE INDEX idx_operational_schedules_month_year ON operational_schedules(month, year);

-- Partial indexes (for filtered queries)
CREATE INDEX idx_users_active ON users(id) WHERE is_active = true;
CREATE INDEX idx_notifications_unread ON notifications(user_id, created_at) WHERE is_read = false;
CREATE INDEX idx_account_codes_active ON account_codes(code) WHERE is_active = true;

-- Full-text search indexes
CREATE INDEX idx_budget_items_description_gin ON budget_items USING gin(to_tsvector('indonesian', description));
CREATE INDEX idx_account_codes_description_gin ON account_codes USING gin(to_tsvector('indonesian', description));

-- ============================================
-- VIEWS FOR REPORTING
-- ============================================

-- View: Budget Summary per Sub-Activity
CREATE OR REPLACE VIEW v_budget_summary AS
SELECT 
    sa.id AS sub_activity_id,
    sa.category,
    sa.name AS sub_activity_name,
    sa.budget_current_year AS allocated_budget,
    COALESCE(SUM(bi.total_amount), 0) AS total_planned,
    COALESCE(SUM(mr.realization_amount), 0) AS total_realized,
    CASE 
        WHEN sa.budget_current_year > 0 
        THEN ROUND((COALESCE(SUM(mr.realization_amount), 0) / sa.budget_current_year * 100)::numeric, 2)
        ELSE 0
    END AS absorption_percentage,
    sa.budget_current_year - COALESCE(SUM(mr.realization_amount), 0) AS remaining_budget
FROM sub_activities sa
LEFT JOIN budget_items bi ON bi.sub_activity_id = sa.id
LEFT JOIN monthly_realizations mr ON mr.budget_item_id = bi.id AND mr.status = 'APPROVED'
GROUP BY sa.id, sa.category, sa.name, sa.budget_current_year;

COMMENT ON VIEW v_budget_summary IS 'Summary anggaran per sub-kegiatan';

-- View: Monthly Progress
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
JOIN budget_items bi ON bi.sub_activity_id = sa.id
LEFT JOIN monthly_plans mp ON mp.budget_item_id = bi.id
LEFT JOIN monthly_realizations mr ON mr.budget_item_id = bi.id 
    AND mr.month = mp.month 
    AND mr.year = mp.year
    AND mr.status = 'APPROVED'
GROUP BY sa.category, mp.month, mp.year
ORDER BY mp.year, mp.month, sa.category;

COMMENT ON VIEW v_monthly_progress IS 'Progress realisasi per bulan per kategori';

-- View: Pending Approvals
CREATE OR REPLACE VIEW v_pending_approvals AS
SELECT 
    mr.id,
    mr.status,
    sa.category,
    bi.description AS item_description,
    mr.month,
    mr.year,
    mr.realization_amount,
    u_input.name AS submitted_by,
    mr.input_date AS submitted_at,
    CASE mr.status
        WHEN 'SUBMITTED' THEN 'Menunggu Verifikasi Bendahara'
        WHEN 'VERIFIED' THEN 'Menunggu Approval Kadis'
    END AS pending_action
FROM monthly_realizations mr
JOIN budget_items bi ON bi.id = mr.budget_item_id
JOIN sub_activities sa ON sa.id = bi.sub_activity_id
LEFT JOIN users u_input ON u_input.id = mr.input_by
WHERE mr.status IN ('SUBMITTED', 'VERIFIED')
ORDER BY mr.input_date ASC;

COMMENT ON VIEW v_pending_approvals IS 'Daftar realisasi yang menunggu approval';

-- View: Deviation Alerts
CREATE OR REPLACE VIEW v_deviation_alerts AS
SELECT 
    bi.id AS budget_item_id,
    sa.category,
    bi.description,
    mp.month,
    mp.year,
    mp.planned_amount,
    mr.realization_amount,
    ROUND(((mr.realization_amount - mp.planned_amount) / mp.planned_amount * 100)::numeric, 2) AS deviation_percentage,
    CASE 
        WHEN mr.realization_amount < mp.planned_amount * 0.7 THEN 'UNDER_REALIZATION'
        WHEN mr.realization_amount > mp.planned_amount * 1.1 THEN 'OVER_REALIZATION'
    END AS alert_type
FROM budget_items bi
JOIN sub_activities sa ON sa.id = bi.sub_activity_id
JOIN monthly_plans mp ON mp.budget_item_id = bi.id
LEFT JOIN monthly_realizations mr ON mr.budget_item_id = bi.id 
    AND mr.month = mp.month 
    AND mr.year = mp.year
    AND mr.status = 'APPROVED'
WHERE mr.realization_amount IS NOT NULL
    AND (
        mr.realization_amount < mp.planned_amount * 0.7 
        OR mr.realization_amount > mp.planned_amount * 1.1
    )
ORDER BY ABS(mr.realization_amount - mp.planned_amount) DESC;

COMMENT ON VIEW v_deviation_alerts IS 'Item dengan deviasi realisasi >30% dari rencana';

-- ============================================
-- TRIGGERS
-- ============================================

-- Function: Update updated_at timestamp
CREATE OR REPLACE FUNCTION update_updated_at_column()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

-- Apply trigger to all tables with updated_at
CREATE TRIGGER update_programs_updated_at BEFORE UPDATE ON programs
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_activities_updated_at BEFORE UPDATE ON activities
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_sub_activities_updated_at BEFORE UPDATE ON sub_activities
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_account_codes_updated_at BEFORE UPDATE ON account_codes
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_budget_items_updated_at BEFORE UPDATE ON budget_items
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_monthly_plans_updated_at BEFORE UPDATE ON monthly_plans
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_operational_schedules_updated_at BEFORE UPDATE ON operational_schedules
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_monthly_realizations_updated_at BEFORE UPDATE ON monthly_realizations
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_users_updated_at BEFORE UPDATE ON users
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- Function: Auto-calculate realization_amount
CREATE OR REPLACE FUNCTION calculate_realization_amount()
RETURNS TRIGGER AS $$
BEGIN
    NEW.realization_amount = NEW.realization_volume * NEW.realization_unit_price;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER auto_calculate_realization_amount 
BEFORE INSERT OR UPDATE ON monthly_realizations
FOR EACH ROW EXECUTE FUNCTION calculate_realization_amount();

-- ============================================
-- SAMPLE DATA SEEDER
-- ============================================

-- Insert sample program
INSERT INTO programs (code, name, year, description) VALUES
('2.21.02', 'PROGRAM PENYELENGGARAAN PERSANDIAN UNTUK PENGAMANAN INFORMASI', 2026, 'Program untuk pengamanan informasi melalui persandian');

-- Insert sample activity
INSERT INTO activities (program_id, code, name, description) VALUES
(1, '2.21.02.1.01', 'PENYELENGGARAAN PERSANDIAN UNTUK PENGAMANAN INFORMASI PEMERINTAH DAERAH PROVINSI', 'Kegiatan persandian');

-- Insert sample sub-activities
INSERT INTO sub_activities (activity_id, category, name, budget_current_year) VALUES
(1, 'ANALISIS', 'Sub-kegiatan Analisis Keamanan', 104571400),
(1, 'TATA_KELOLA', 'Sub-kegiatan Tata Kelola Persandian', 53683500),
(1, 'LAYANAN', 'Sub-kegiatan Layanan Persandian', 918886900),
(1, 'ELEK_NON_ELEK', 'Sub-kegiatan Elektronik dan Non-Elektronik', 213924275);

-- Insert sample account codes (hierarchical)
INSERT INTO account_codes (code, description, level, parent_code) VALUES
('5', 'BELANJA DAERAH', 1, NULL),
('5.1', 'BELANJA OPERASI', 2, '5'),
('5.1.02', 'Belanja Barang dan Jasa', 3, '5.1'),
('5.1.02.01', 'Belanja Bahan Pakai Habis', 4, '5.1.02'),
('5.1.02.01.01.0024', 'Belanja Alat/Bahan untuk Kegiatan Kantor-Alat Tulis Kantor', 5, '5.1.02.01'),
('5.1.02.01.01.0025', 'Belanja Alat/Bahan untuk Kegiatan Kantor- Kertas dan Cover', 5, '5.1.02.01'),
('5.1.02.02', 'Belanja Jasa', 4, '5.1.02'),
('5.1.02.04', 'Belanja Perjalanan Dinas', 4, '5.1.02'),
('5.1.02.04.01', 'Belanja Perjalanan Dinas Dalam Negeri', 5, '5.1.02.04'),
('5.1.02.04.01.0001', 'Belanja Perjalanan Dinas Biasa', 5, '5.1.02.04.01');

-- Insert sample admin user (password: password123)
INSERT INTO users (nip, name, email, password_hash, role, is_active) VALUES
('199001012020121001', 'Administrator', 'admin@baliprov.go.id', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'ADMIN', true);

-- ============================================
-- GRANT PERMISSIONS
-- ============================================

-- Create application user
CREATE USER sipera_app WITH PASSWORD 'secure_password_here';

-- Grant permissions
GRANT CONNECT ON DATABASE sipera TO sipera_app;
GRANT USAGE ON SCHEMA public TO sipera_app;
GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA public TO sipera_app;
GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA public TO sipera_app;
GRANT EXECUTE ON ALL FUNCTIONS IN SCHEMA public TO sipera_app;

-- Set default privileges for future tables
ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO sipera_app;
ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT USAGE, SELECT ON SEQUENCES TO sipera_app;

-- ============================================
-- MAINTENANCE PROCEDURES
-- ============================================

-- Procedure: Clean old audit logs (keep 90 days)
CREATE OR REPLACE PROCEDURE clean_old_audit_logs()
LANGUAGE plpgsql
AS $$
BEGIN
    DELETE FROM audit_logs 
    WHERE created_at < CURRENT_DATE - INTERVAL '90 days';
    
    RAISE NOTICE 'Old audit logs cleaned';
END;
$$;

-- Procedure: Clean old notifications (keep 30 days)
CREATE OR REPLACE PROCEDURE clean_old_notifications()
LANGUAGE plpgsql
AS $$
BEGIN
    DELETE FROM notifications 
    WHERE created_at < CURRENT_DATE - INTERVAL '30 days'
    AND is_read = true;
    
    RAISE NOTICE 'Old notifications cleaned';
END;
$$;

-- Procedure: Vacuum and analyze all tables
CREATE OR REPLACE PROCEDURE maintenance_vacuum_analyze()
LANGUAGE plpgsql
AS $$
BEGIN
    VACUUM ANALYZE;
    RAISE NOTICE 'Database vacuumed and analyzed';
END;
$$;

-- ============================================
-- COMPLETION
-- ============================================

SELECT 'Database schema created successfully!' AS message;
SELECT COUNT(*) AS total_tables FROM information_schema.tables WHERE table_schema = 'public' AND table_type = 'BASE TABLE';
SELECT COUNT(*) AS total_views FROM information_schema.views WHERE table_schema = 'public';
SELECT COUNT(*) AS total_indexes FROM pg_indexes WHERE schemaname = 'public';
