<?php

use App\Enums\Permission;
use App\Http\Controllers\GatePassVerificationController;
use App\Http\Controllers\InvoicePdfController;
use App\Http\Controllers\ReceiptPdfController;
use App\Http\Controllers\SocietySwitchController;
use App\Livewire;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public
|--------------------------------------------------------------------------
|
| This is an internal tool, so the only unauthenticated surface is what has
| to be reachable from a printed document: receipt and gate-pass checks.
|
*/

Route::get('/verify/receipt/{uuid}', [ReceiptPdfController::class, 'verify'])->name('receipts.verify');
Route::get('/verify/pass/{token}', [GatePassVerificationController::class, 'show'])->name('gate-passes.verify');

/*
|--------------------------------------------------------------------------
| Guest
|--------------------------------------------------------------------------
*/

Route::middleware('guest')->group(function () {
    Route::get('/login', Livewire\Auth\Login::class)->name('login');
    Route::get('/forgot-password', Livewire\Auth\ForgotPassword::class)->name('password.request');
    Route::get('/reset-password/{token}', Livewire\Auth\ResetPassword::class)->name('password.reset');
});

Route::post('/logout', function () {
    auth()->logout();
    session()->invalidate();
    session()->regenerateToken();

    return redirect()->route('login');
})->middleware('auth')->name('logout');

/*
|--------------------------------------------------------------------------
| Authenticated
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->group(function () {
    Route::redirect('/', '/dashboard');
    Route::get('/dashboard', Livewire\Dashboard::class)->name('dashboard');

    Route::get('/profile', Livewire\Profile\Edit::class)->name('profile.edit');
    Route::post('/societies/{society}/switch', SocietySwitchController::class)->name('societies.switch');

    Route::get('/onboarding', Livewire\Onboarding\Wizard::class)
        ->middleware('permission:'.Permission::SOCIETY_SETTINGS)
        ->name('onboarding.index');

    // --- Money ----------------------------------------------------------

    Route::middleware('permission:'.Permission::BILLING_VIEW)->group(function () {
        Route::get('/invoices', Livewire\Billing\InvoiceIndex::class)->name('invoices.index');
        Route::get('/invoices/{invoice}', Livewire\Billing\InvoiceShow::class)->name('invoices.show');
        Route::get('/invoices/{invoice}/pdf', [InvoicePdfController::class, 'show'])->name('invoices.pdf');
    });

    Route::middleware('permission:'.Permission::PAYMENT_VIEW)->group(function () {
        Route::get('/payments', Livewire\Billing\PaymentIndex::class)->name('payments.index');
        Route::get('/receipts', Livewire\Billing\ReceiptIndex::class)->name('receipts.index');
        Route::get('/receipts/{receipt}/pdf', [ReceiptPdfController::class, 'show'])->name('receipts.pdf');
    });

    Route::middleware('permission:'.Permission::BILLING_MANAGE)->group(function () {
        Route::get('/charge-heads', Livewire\Billing\ChargeHeadIndex::class)->name('charge-heads.index');
        Route::get('/billing-plans', Livewire\Billing\BillingPlanIndex::class)->name('billing-plans.index');
    });

    Route::middleware('permission:'.Permission::EXPENSE_VIEW)
        ->get('/expenses', Livewire\Accounting\ExpenseIndex::class)->name('expenses.index');

    Route::middleware('permission:'.Permission::VENDOR_VIEW)
        ->get('/vendors', Livewire\Accounting\VendorIndex::class)->name('vendors.index');

    Route::middleware('permission:'.Permission::REPORT_VIEW)
        ->get('/reports', Livewire\Reporting\ReportIndex::class)->name('reports.index');

    // --- Property -------------------------------------------------------

    Route::middleware('permission:'.Permission::UNIT_VIEW)->group(function () {
        Route::get('/units', Livewire\Property\UnitIndex::class)->name('units.index');
        Route::get('/units/{unit}', Livewire\Property\UnitShow::class)->name('units.show');
        Route::get('/parking', Livewire\Property\ParkingIndex::class)->name('parking.index');
    });

    Route::middleware('permission:'.Permission::RESIDENT_VIEW)
        ->get('/residents', Livewire\Property\ResidentIndex::class)->name('residents.index');

    Route::middleware('permission:'.Permission::DIRECTORY_VIEW)
        ->get('/directory', Livewire\Property\DirectoryIndex::class)->name('directory.index');

    // --- Operations -----------------------------------------------------

    Route::middleware('permission:'.Permission::COMPLAINT_CREATE.','.Permission::COMPLAINT_VIEW_ALL)->group(function () {
        Route::get('/helpdesk', Livewire\Helpdesk\ComplaintIndex::class)->name('complaints.index');
        Route::get('/helpdesk/{complaint}', Livewire\Helpdesk\ComplaintShow::class)->name('complaints.show');
    });

    Route::middleware('permission:'.Permission::WORK_ORDER_VIEW)
        ->get('/work-orders', Livewire\Facilities\WorkOrderIndex::class)->name('work-orders.index');

    Route::middleware('permission:'.Permission::ASSET_VIEW)
        ->get('/assets', Livewire\Facilities\AssetIndex::class)->name('assets.index');

    Route::middleware('permission:'.Permission::AMENITY_VIEW)
        ->get('/amenities', Livewire\Facilities\AmenityIndex::class)->name('amenities.index');

    Route::middleware('permission:'.Permission::STAFF_VIEW)
        ->get('/staff', Livewire\People\StaffIndex::class)->name('staff.index');

    // --- Security -------------------------------------------------------

    Route::middleware('permission:'.Permission::GATE_OPERATE)
        ->get('/gate', Livewire\Security\GateConsole::class)->name('gate.index');

    Route::middleware('permission:'.Permission::VISITOR_MANAGE)->group(function () {
        Route::get('/visitors', Livewire\Security\VisitorIndex::class)->name('visitors.index');
        Route::get('/gate-passes', Livewire\Security\GatePassIndex::class)->name('gate-passes.index');
    });

    // --- Community ------------------------------------------------------

    Route::middleware('permission:'.Permission::NOTICE_VIEW)
        ->get('/notices', Livewire\Community\NoticeIndex::class)->name('notices.index');

    Route::middleware('permission:'.Permission::MEETING_VIEW)->group(function () {
        Route::get('/meetings', Livewire\Governance\MeetingIndex::class)->name('meetings.index');
        Route::get('/meetings/{meeting}', Livewire\Governance\MeetingShow::class)->name('meetings.show');
    });

    Route::middleware('permission:'.Permission::POLL_VIEW)->group(function () {
        Route::get('/polls', Livewire\Governance\PollIndex::class)->name('polls.index');
        Route::get('/polls/{poll}', Livewire\Governance\PollShow::class)->name('polls.show');
    });

    Route::middleware('permission:'.Permission::DOCUMENT_VIEW)
        ->get('/documents', Livewire\Community\DocumentIndex::class)->name('documents.index');

    // --- Administration -------------------------------------------------

    Route::middleware('permission:'.Permission::SOCIETY_SETTINGS)
        ->get('/settings', Livewire\Settings\SocietySettings::class)->name('settings.index');

    Route::middleware('permission:'.Permission::SOCIETY_MANAGE)
        ->get('/settings/roles', Livewire\Settings\RoleManager::class)->name('settings.roles');

    Route::middleware('permission:'.Permission::AUDIT_VIEW)
        ->get('/audit-log', Livewire\Settings\AuditIndex::class)->name('audit.index');
});
