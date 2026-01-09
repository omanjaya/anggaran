# Non-Functional Requirements
## SIPERA - Sistem Informasi Perencanaan dan Realisasi Anggaran

**Version:** 1.0
**Date:** 8 Januari 2026

---

## 1. PERFORMANCE REQUIREMENTS

### 1.1 Response Time
| Operation | Target | Measurement |
|-----------|--------|-------------|
| Page Load (initial) | < 2 detik | Lighthouse |
| Page Load (subsequent) | < 1 detik | Cached |
| API Response (simple query) | < 200ms | p95 |
| API Response (complex query) | < 500ms | p95 |
| Report Generation | < 30 detik | For monthly report |
| File Upload (5MB) | < 5 detik | 4G connection |
| Search Autocomplete | < 300ms | p95 |

### 1.2 Throughput
| Metric | Target |
|--------|--------|
| Concurrent Users | 50 users minimum |
| Requests per Second | 100 RPS |
| Database Connections | Pool size 50 |
| File Uploads per Minute | 30 files |

### 1.3 Resource Usage
| Resource | Limit |
|----------|-------|
| Memory per Request | < 128MB |
| CPU Usage (normal) | < 60% |
| Database Query Time | < 100ms |
| Cache Hit Rate | > 80% |

---

## 2. SCALABILITY REQUIREMENTS

### 2.1 Data Volume
| Entity | Projected Volume (Year 1) | Growth Rate |
|--------|---------------------------|-------------|
| Users | 50 | 10% per year |
| Budget Items | 500 | 20% per year |
| Monthly Realizations | 6,000 | 20% per year |
| Documents | 30,000 | 30% per year |
| Audit Logs | 500,000 | 50% per year |

### 2.2 Storage Requirements
| Type | Projected Size (Year 1) | Year 3 |
|------|-------------------------|--------|
| Database | 5 GB | 20 GB |
| Document Storage | 50 GB | 200 GB |
| Backup Storage | 100 GB | 400 GB |
| Total | 155 GB | 620 GB |

### 2.3 Scaling Strategy
- **Horizontal Scaling:** Add more app servers behind load balancer
- **Database Scaling:** Read replicas for read-heavy operations
- **Cache Scaling:** Redis cluster for high availability
- **Storage Scaling:** Object storage (MinIO/S3) for documents

---

## 3. RELIABILITY REQUIREMENTS

### 3.1 Availability
| Metric | Target | Calculation |
|--------|--------|-------------|
| Uptime | 99.5% | Max 3.6 hours downtime/month |
| Planned Maintenance Window | 4 hours/month | Sunday 00:00-04:00 WIB |
| MTTR (Mean Time to Recover) | < 1 hour | For critical issues |
| MTBF (Mean Time Between Failures) | > 720 hours | 30 days |

### 3.2 Backup & Recovery
| Type | Frequency | Retention | RTO | RPO |
|------|-----------|-----------|-----|-----|
| Full Database Backup | Daily (02:00) | 30 days | 4 hours | 24 hours |
| Incremental Backup | Hourly | 7 days | 1 hour | 1 hour |
| Document Backup | Daily | 90 days | 4 hours | 24 hours |
| Configuration Backup | On change | 30 versions | 30 min | 0 |

**RTO (Recovery Time Objective):** Max time to restore service
**RPO (Recovery Point Objective):** Max acceptable data loss

### 3.3 Disaster Recovery
- **Primary Site:** Data Center Bali
- **DR Site:** Cloud backup (ready for activation)
- **Failover Time:** < 4 hours
- **DR Test:** Quarterly

### 3.4 Graceful Degradation
Jika komponen down, sistem tetap berjalan dengan fitur terbatas:

| Component Down | Degraded Behavior |
|----------------|-------------------|
| Redis | Fall back to database session, slower but working |
| Email Service | Queue emails, retry when service up |
| File Storage | Disable upload, show cached thumbnails |
| Report Service | Show "generating" status, process when available |

---

## 4. SECURITY REQUIREMENTS

### 4.1 Authentication
| Requirement | Implementation |
|-------------|----------------|
| Password Policy | Min 8 chars, uppercase, lowercase, number, symbol |
| Password Hashing | bcrypt with cost factor 12 |
| Session Duration | 8 hours, with refresh token |
| Failed Login Handling | Lock after 5 attempts for 30 minutes |
| 2FA | Optional, via email OTP |

### 4.2 Authorization
| Requirement | Implementation |
|-------------|----------------|
| Access Control Model | Role-Based Access Control (RBAC) |
| Permission Granularity | Feature-level + Data-level |
| Session Validation | JWT token validation on each request |
| Token Storage | HttpOnly cookies (SameSite=Strict) |

### 4.3 Data Protection
| Requirement | Implementation |
|-------------|----------------|
| Transport Encryption | HTTPS only (TLS 1.3) |
| Data at Rest | AES-256 for sensitive fields |
| Database Encryption | PostgreSQL TDE (optional) |
| Backup Encryption | AES-256 |
| Key Management | Environment variables / Secret manager |

### 4.4 Input Validation & Sanitization
| Attack Vector | Prevention |
|---------------|------------|
| SQL Injection | Parameterized queries (Eloquent ORM) |
| XSS | Input sanitization, output encoding, CSP headers |
| CSRF | Token-based protection |
| File Upload | MIME type validation, magic bytes check, size limit |
| Path Traversal | Sanitize file paths, use random filenames |

### 4.5 Rate Limiting
| Endpoint | Limit |
|----------|-------|
| Login | 5 attempts per 5 minutes per IP |
| API (authenticated) | 60 requests per minute per user |
| API (unauthenticated) | 20 requests per minute per IP |
| File Upload | 10 uploads per minute per user |
| Report Generation | 5 reports per minute per user |

### 4.6 Audit & Compliance
| Requirement | Implementation |
|-------------|----------------|
| Audit Log | Log all data modifications |
| Log Retention | 1 year minimum |
| Log Content | User ID, action, timestamp, IP, old/new values |
| PII Handling | Mask sensitive data in logs |
| Compliance | Follow government data security guidelines |

---

## 5. USABILITY REQUIREMENTS

### 5.1 User Interface
| Requirement | Target |
|-------------|--------|
| Maximum Clicks to Action | 3 clicks |
| Form Validation | Real-time inline validation |
| Error Messages | Clear, actionable guidance |
| Loading Indicators | Show for operations > 500ms |
| Keyboard Navigation | Full support for power users |

### 5.2 Accessibility (WCAG 2.1 Level AA)
| Requirement | Implementation |
|-------------|----------------|
| Color Contrast | Minimum 4.5:1 ratio |
| Font Size | Minimum 14px, scalable |
| Focus Indicators | Visible focus states |
| Screen Reader | ARIA labels, semantic HTML |
| Alternative Text | For all images and icons |

### 5.3 Responsive Design
| Breakpoint | Target Devices |
|------------|----------------|
| Desktop | >= 1280px |
| Tablet | 768px - 1279px |
| Mobile | < 768px |

**Touch Target Size:** Minimum 44x44px for mobile

### 5.4 Browser Support
| Browser | Minimum Version |
|---------|-----------------|
| Chrome | 90+ |
| Firefox | 88+ |
| Safari | 14+ |
| Edge | 90+ |
| Mobile Safari | iOS 14+ |
| Chrome Mobile | Android 10+ |

### 5.5 Internationalization
| Requirement | Current Scope |
|-------------|---------------|
| Language | Bahasa Indonesia (primary) |
| Date Format | DD/MM/YYYY, DD MMMM YYYY |
| Number Format | Indonesian (1.234.567,89) |
| Currency | IDR (Rp) |
| Timezone | Asia/Makassar (WITA) |

---

## 6. MAINTAINABILITY REQUIREMENTS

### 6.1 Code Quality
| Metric | Target |
|--------|--------|
| Test Coverage | > 80% (unit tests) |
| Code Documentation | PHPDoc/JSDoc for public methods |
| Code Style | PSR-12 (PHP), Airbnb (JavaScript) |
| Static Analysis | PHPStan level 6, ESLint strict |
| Cyclomatic Complexity | < 10 per function |

### 6.2 Technical Debt
| Metric | Target |
|--------|--------|
| Code Smells | < 50 per 10K lines |
| Duplicated Code | < 3% |
| Dependency Updates | Monthly review |
| Security Patches | Within 7 days of release |

### 6.3 Documentation
| Type | Required |
|------|----------|
| API Documentation | OpenAPI/Swagger spec |
| Database Schema | ERD diagram + table descriptions |
| Architecture | System architecture diagram |
| Deployment Guide | Step-by-step deployment procedures |
| User Manual | End-user documentation (Bahasa Indonesia) |

### 6.4 Monitoring & Observability
| Type | Tool/Implementation |
|------|---------------------|
| Application Logs | Structured JSON logging |
| Error Tracking | Sentry or similar |
| Performance Metrics | Response time, throughput |
| Health Checks | /health endpoint |
| Uptime Monitoring | External monitoring service |

---

## 7. COMPATIBILITY REQUIREMENTS

### 7.1 Technology Stack Versions
| Component | Version | Support Until |
|-----------|---------|---------------|
| PHP | 8.2+ | Dec 2026 |
| Laravel | 11.x | Jan 2027 |
| PostgreSQL | 16.x | Nov 2028 |
| Redis | 7.x | Ongoing |
| Node.js | 20.x LTS | Apr 2026 |
| Vue.js | 3.x | Ongoing |

### 7.2 Integration Capabilities
| Requirement | Implementation |
|-------------|----------------|
| API Style | RESTful JSON API |
| API Documentation | OpenAPI 3.0 specification |
| Authentication | OAuth2 / JWT for third-party |
| Webhooks | Support for event notifications |
| Export Formats | JSON, CSV, Excel, PDF |

### 7.3 Future Integrations (Planned)
| System | Priority | Target Phase |
|--------|----------|--------------|
| SIPKD | High | Phase 2 |
| e-Procurement | Medium | Phase 3 |
| E-Budgeting | Medium | Phase 3 |
| SSO Pemprov | High | Phase 2 |

---

## 8. DEPLOYMENT REQUIREMENTS

### 8.1 Environment Requirements
| Environment | Purpose | Spec |
|-------------|---------|------|
| Development | Local dev | Docker |
| Staging | UAT & QA | Mirror of production (scaled down) |
| Production | Live system | Full spec |

### 8.2 Production Server Specifications
**Minimum:**
- CPU: 4 cores (Intel Xeon / AMD EPYC)
- RAM: 16 GB
- Storage: 200 GB SSD
- Network: 100 Mbps dedicated

**Recommended:**
- CPU: 8 cores
- RAM: 32 GB
- Storage: 500 GB NVMe SSD
- Network: 1 Gbps
- Backup: 1 TB external storage

### 8.3 CI/CD Requirements
| Stage | Requirement |
|-------|-------------|
| Build | Automated on every commit |
| Test | Unit + integration tests must pass |
| Security | Dependency vulnerability scan |
| Deploy Staging | Automated on merge to staging branch |
| Deploy Production | Manual approval required |
| Rollback | One-click rollback capability |

---

## 9. LEGAL & COMPLIANCE

### 9.1 Data Governance
| Requirement | Implementation |
|-------------|----------------|
| Data Ownership | Dinas Kominfo Provinsi Bali |
| Data Classification | Internal - Confidential |
| Data Retention | 5 years minimum (per regulations) |
| Data Location | Indonesia (data sovereignty) |

### 9.2 Regulatory Compliance
| Regulation | Requirement |
|------------|-------------|
| Permendagri 77/2020 | Budget management guidelines |
| Permendagri 90/2019 | Budget classification codes |
| UU 14/2008 | Public information transparency |
| PP 71/2019 | Electronic system security |

### 9.3 Audit Requirements
| Type | Frequency |
|------|-----------|
| Internal Audit | Annual |
| External Audit | As required by BPK |
| Security Assessment | Annual penetration testing |
| Compliance Review | Annual |

---

## 10. SUMMARY MATRIX

| Category | Priority | Validation Method |
|----------|----------|-------------------|
| Performance | High | Load testing, monitoring |
| Scalability | Medium | Capacity planning, load testing |
| Reliability | High | Uptime monitoring, DR drills |
| Security | Critical | Security audit, pen testing |
| Usability | High | User testing, analytics |
| Maintainability | Medium | Code review, static analysis |
| Compatibility | Medium | Integration testing |
| Deployment | High | CI/CD pipeline validation |
| Compliance | Critical | Audit reports |

---

**Document Status:** Complete
**Last Updated:** 8 Januari 2026
