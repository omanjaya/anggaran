<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\ProgramController;
use App\Http\Controllers\Api\ActivityController;
use App\Http\Controllers\Api\SubActivityController;
use App\Http\Controllers\Api\BudgetItemController;
use App\Http\Controllers\Api\MonthlyPlanController;
use App\Http\Controllers\Api\RealizationController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\OperationalScheduleController;
use App\Http\Controllers\Api\DeviationAlertController;
use App\Http\Controllers\Api\CustomReportController;
use App\Http\Controllers\Api\ImportController;
use App\Http\Controllers\Api\SkpdController;
use App\Http\Controllers\Api\AccountCodeController;
use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\PlgkController;
use App\Http\Controllers\Api\DpaImportController;
use Illuminate\Support\Facades\Route;

// Public routes
Route::post('/auth/login', [AuthController::class, 'login']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    // Auth
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);

    // Dashboard
    Route::prefix('dashboard')->group(function () {
        Route::get('/stats', [DashboardController::class, 'stats']);
        Route::get('/monthly-trend', [DashboardController::class, 'monthlyTrend']);
        Route::get('/program-stats', [DashboardController::class, 'programStats']);
        Route::get('/recent-activities', [DashboardController::class, 'recentActivities']);
    });

    // Reports
    Route::prefix('reports')->group(function () {
        Route::get('/monthly', [ReportController::class, 'monthly']);
        Route::get('/quarterly', [ReportController::class, 'quarterly']);
        Route::get('/yearly', [ReportController::class, 'yearly']);
        Route::get('/by-category', [ReportController::class, 'byCategory']);
        Route::get('/realisasi', [ReportController::class, 'realisasi']);
        Route::get('/export/monthly/pdf', [ReportController::class, 'exportMonthlyPdf']);
        Route::get('/export/monthly/excel', [ReportController::class, 'exportMonthlyExcel']);
        Route::get('/export/yearly/pdf', [ReportController::class, 'exportYearlyPdf']);
        Route::get('/export/yearly/excel', [ReportController::class, 'exportYearlyExcel']);
        // Realisasi exports - Format Pak Kadis
        Route::get('/export/realisasi/pdf', [ReportController::class, 'exportRealisasiPdf']);
        Route::get('/export/realisasi/excel', [ReportController::class, 'exportRealisasiExcel']);
    });

    // Users
    Route::get('/users/roles', [UserController::class, 'roles']);
    Route::apiResource('users', UserController::class);

    // Master Data - Programs
    Route::get('/programs/categories', [ProgramController::class, 'categories']);
    Route::apiResource('programs', ProgramController::class);

    // Master Data - Activities
    Route::apiResource('activities', ActivityController::class);

    // Master Data - Sub Activities
    Route::apiResource('sub-activities', SubActivityController::class);

    // Master Data - Budget Items
    Route::apiResource('budget-items', BudgetItemController::class);

    // Monthly Plans
    Route::get('/monthly-plans/by-budget-item/{budgetItemId}', [MonthlyPlanController::class, 'byBudgetItem']);
    Route::post('/monthly-plans/batch', [MonthlyPlanController::class, 'batch']);
    Route::apiResource('monthly-plans', MonthlyPlanController::class);

    // Realizations
    Route::get('/realizations/pending-verification', [RealizationController::class, 'pendingVerification']);
    Route::get('/realizations/pending-approval', [RealizationController::class, 'pendingApproval']);
    Route::post('/realizations/batch', [RealizationController::class, 'batch']);
    Route::post('/realizations/batch-verify', [RealizationController::class, 'batchVerify']);
    Route::post('/realizations/batch-approve', [RealizationController::class, 'batchApprove']);
    Route::post('/realizations/{realization}/submit', [RealizationController::class, 'submit']);
    Route::post('/realizations/{realization}/verify', [RealizationController::class, 'verify']);
    Route::post('/realizations/{realization}/approve', [RealizationController::class, 'approve']);
    Route::post('/realizations/{realization}/lock', [RealizationController::class, 'lock']);
    Route::post('/realizations/{realization}/unlock', [RealizationController::class, 'unlock']);
    Route::get('/realizations/{realization}/documents', [RealizationController::class, 'getDocuments']);
    Route::post('/realizations/{realization}/documents', [RealizationController::class, 'uploadDocument']);
    Route::get('/realizations/{realization}/documents/{document}/download', [RealizationController::class, 'downloadDocument']);
    Route::delete('/realizations/{realization}/documents/{document}', [RealizationController::class, 'deleteDocument']);
    Route::apiResource('realizations', RealizationController::class)->except(['destroy']);

    // Notifications
    Route::prefix('notifications')->group(function () {
        Route::get('/', [NotificationController::class, 'index']);
        Route::get('/unread', [NotificationController::class, 'unread']);
        Route::get('/count', [NotificationController::class, 'count']);
        Route::post('/{id}/read', [NotificationController::class, 'markAsRead']);
        Route::post('/read-all', [NotificationController::class, 'markAllAsRead']);
        Route::delete('/{id}', [NotificationController::class, 'destroy']);
    });

    // Operational Schedules (ROK OP)
    Route::prefix('operational-schedules')->group(function () {
        Route::get('/calendar', [OperationalScheduleController::class, 'calendar']);
        Route::get('/gantt', [OperationalScheduleController::class, 'gantt']);
        Route::post('/generate-from-plgk', [OperationalScheduleController::class, 'generateFromPlgk']);
        Route::post('/{operationalSchedule}/status', [OperationalScheduleController::class, 'updateStatus']);
        Route::post('/{operationalSchedule}/assign-pic', [OperationalScheduleController::class, 'assignPic']);
    });
    Route::apiResource('operational-schedules', OperationalScheduleController::class);

    // Deviation Alerts
    Route::prefix('deviation-alerts')->group(function () {
        Route::get('/', [DeviationAlertController::class, 'index']);
        Route::get('/dashboard', [DeviationAlertController::class, 'dashboard']);
        Route::get('/check', [DeviationAlertController::class, 'check']);
        Route::post('/{id}/acknowledge', [DeviationAlertController::class, 'acknowledge']);
        Route::post('/{id}/resolve', [DeviationAlertController::class, 'resolve']);
        Route::delete('/{id}', [DeviationAlertController::class, 'destroy']);
    });

    // Custom Reports
    Route::prefix('custom-reports')->group(function () {
        Route::get('/templates', [CustomReportController::class, 'templates']);
        Route::post('/templates', [CustomReportController::class, 'storeTemplate']);
        Route::delete('/templates/{id}', [CustomReportController::class, 'destroyTemplate']);
        Route::post('/generate', [CustomReportController::class, 'generate']);
        Route::post('/export/pdf', [CustomReportController::class, 'exportPdf']);
        Route::post('/export/excel', [CustomReportController::class, 'exportExcel']);
    });

    // Import (DPA from Excel)
    Route::prefix('import')->group(function () {
        Route::post('/dpa', [ImportController::class, 'importDpa']);
        Route::post('/plgk', [ImportController::class, 'importPlgk']);
        Route::post('/preview', [ImportController::class, 'preview']);
        Route::get('/templates', [ImportController::class, 'downloadTemplate']);
        // DPA PDF Import
        Route::post('/dpa-pdf/preview', [DpaImportController::class, 'preview']);
        Route::post('/dpa-pdf', [DpaImportController::class, 'import']);
        Route::post('/dpa-pdf/batch', [DpaImportController::class, 'importBatch']);
        Route::delete('/dpa-pdf/clear-all', [DpaImportController::class, 'clearAll']);
    });

    // SKPD Management
    Route::apiResource('skpd', SkpdController::class);

    // Account Codes (Kode Rekening)
    Route::prefix('account-codes')->group(function () {
        Route::get('/tree', [AccountCodeController::class, 'tree']);
        Route::get('/leaf-nodes', [AccountCodeController::class, 'leafNodes']);
        Route::get('/levels', [AccountCodeController::class, 'levels']);
        Route::post('/import', [AccountCodeController::class, 'import']);
    });
    Route::apiResource('account-codes', AccountCodeController::class)->parameters([
        'account-codes' => 'code'
    ]);

    // Audit Logs
    Route::prefix('audit-logs')->group(function () {
        Route::get('/', [AuditLogController::class, 'index']);
        Route::get('/actions', [AuditLogController::class, 'actions']);
        Route::get('/stats', [AuditLogController::class, 'stats']);
        Route::get('/for-model', [AuditLogController::class, 'forModel']);
        Route::get('/{auditLog}', [AuditLogController::class, 'show']);
    });

    // PLGK Generator
    Route::prefix('plgk')->group(function () {
        Route::get('/methods', [PlgkController::class, 'methods']);
        Route::get('/years', [PlgkController::class, 'years']);
        Route::get('/{subActivity}', [PlgkController::class, 'show']);
        Route::post('/{subActivity}/preview', [PlgkController::class, 'preview']);
        Route::post('/{subActivity}/generate', [PlgkController::class, 'generate']);
        Route::get('/{subActivity}/validate', [PlgkController::class, 'validate']);
    });
});
