<?php

namespace App\Support;

use App\Enums\Permission;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/**
 * Builds the sidebar for the signed-in user.
 *
 * Items are declared once with the permission that unlocks them, so a guard
 * and a treasurer get genuinely different navigation rather than a full menu
 * full of links that lead to a 403.
 */
class Navigation
{
    /**
     * @return Collection<int, array{label: string, items: array<int, array<string, mixed>>}>
     */
    public static function sections(): Collection
    {
        return collect(self::definition())
            ->map(fn (array $section) => [
                'label' => $section['label'],
                'items' => collect($section['items'])
                    ->filter(fn (array $item) => self::allowed($item))
                    ->map(fn (array $item) => $item + ['active' => self::isActive($item['route'])])
                    ->values()
                    ->all(),
            ])
            ->filter(fn (array $section) => $section['items'] !== [])
            ->values();
    }

    /** The handful of destinations shown in the phone's bottom bar. */
    public static function primary(): Collection
    {
        return collect([
            ['label' => 'Home', 'route' => 'dashboard', 'icon' => 'home', 'permission' => null],
            ['label' => 'Bills', 'route' => 'invoices.index', 'icon' => 'receipt', 'permission' => Permission::BILLING_VIEW],
            ['label' => 'Helpdesk', 'route' => 'complaints.index', 'icon' => 'lifebuoy', 'permission' => Permission::COMPLAINT_CREATE],
            ['label' => 'Notices', 'route' => 'notices.index', 'icon' => 'megaphone', 'permission' => Permission::NOTICE_VIEW],
        ])
            ->filter(fn (array $item) => self::allowed($item))
            ->map(fn (array $item) => $item + ['active' => self::isActive($item['route'])])
            ->values();
    }

    /** @return array<int, array{label: string, items: array<int, array<string, mixed>>}> */
    private static function definition(): array
    {
        return [
            [
                'label' => 'Platform',
                'items' => [
                    ['label' => 'All societies', 'route' => 'platform.societies', 'icon' => 'building',
                        'permission' => null, 'super_admin_only' => true],
                ],
            ],
            [
                'label' => 'Overview',
                'items' => [
                    ['label' => 'Dashboard', 'route' => 'dashboard', 'icon' => 'home', 'permission' => null],
                ],
            ],
            [
                'label' => 'Money',
                'items' => [
                    ['label' => 'Invoices', 'route' => 'invoices.index', 'icon' => 'receipt', 'permission' => Permission::BILLING_VIEW],
                    ['label' => 'Payments', 'route' => 'payments.index', 'icon' => 'banknote', 'permission' => Permission::PAYMENT_VIEW],
                    ['label' => 'Receipts', 'route' => 'receipts.index', 'icon' => 'ticket', 'permission' => Permission::PAYMENT_VIEW],
                    ['label' => 'Charge heads', 'route' => 'charge-heads.index', 'icon' => 'tag', 'permission' => Permission::BILLING_MANAGE],
                    ['label' => 'Billing plans', 'route' => 'billing-plans.index', 'icon' => 'calendar-repeat', 'permission' => Permission::BILLING_MANAGE],
                    ['label' => 'Expenses', 'route' => 'expenses.index', 'icon' => 'wallet', 'permission' => Permission::EXPENSE_VIEW],
                    ['label' => 'Vendors', 'route' => 'vendors.index', 'icon' => 'truck', 'permission' => Permission::VENDOR_VIEW],
                    ['label' => 'Reports', 'route' => 'reports.index', 'icon' => 'chart', 'permission' => Permission::REPORT_VIEW],
                ],
            ],
            [
                'label' => 'Property',
                'items' => [
                    ['label' => 'Units', 'route' => 'units.index', 'icon' => 'building', 'permission' => Permission::UNIT_VIEW],
                    ['label' => 'Residents', 'route' => 'residents.index', 'icon' => 'users', 'permission' => Permission::RESIDENT_VIEW],
                    ['label' => 'Directory', 'route' => 'directory.index', 'icon' => 'book', 'permission' => Permission::DIRECTORY_VIEW],
                    ['label' => 'Parking', 'route' => 'parking.index', 'icon' => 'car', 'permission' => Permission::UNIT_VIEW],
                ],
            ],
            [
                'label' => 'Operations',
                'items' => [
                    ['label' => 'Helpdesk', 'route' => 'complaints.index', 'icon' => 'lifebuoy', 'permission' => Permission::COMPLAINT_CREATE],
                    ['label' => 'Work orders', 'route' => 'work-orders.index', 'icon' => 'wrench', 'permission' => Permission::WORK_ORDER_VIEW],
                    ['label' => 'Assets & AMC', 'route' => 'assets.index', 'icon' => 'cube', 'permission' => Permission::ASSET_VIEW],
                    ['label' => 'Amenities', 'route' => 'amenities.index', 'icon' => 'sparkles', 'permission' => Permission::AMENITY_VIEW],
                    ['label' => 'Staff', 'route' => 'staff.index', 'icon' => 'identification', 'permission' => Permission::STAFF_VIEW],
                ],
            ],
            [
                'label' => 'Security',
                'items' => [
                    ['label' => 'Gate', 'route' => 'gate.index', 'icon' => 'shield', 'permission' => Permission::GATE_OPERATE],
                    ['label' => 'Visitors', 'route' => 'visitors.index', 'icon' => 'user-plus', 'permission' => Permission::VISITOR_MANAGE],
                    ['label' => 'Gate passes', 'route' => 'gate-passes.index', 'icon' => 'qr', 'permission' => Permission::VISITOR_MANAGE],
                ],
            ],
            [
                'label' => 'Community',
                'items' => [
                    ['label' => 'Notices', 'route' => 'notices.index', 'icon' => 'megaphone', 'permission' => Permission::NOTICE_VIEW],
                    ['label' => 'Meetings', 'route' => 'meetings.index', 'icon' => 'calendar', 'permission' => Permission::MEETING_VIEW],
                    ['label' => 'Polls', 'route' => 'polls.index', 'icon' => 'check-badge', 'permission' => Permission::POLL_VIEW],
                    ['label' => 'Documents', 'route' => 'documents.index', 'icon' => 'folder', 'permission' => Permission::DOCUMENT_VIEW],
                ],
            ],
            [
                'label' => 'Administration',
                'items' => [
                    ['label' => 'Society settings', 'route' => 'settings.index', 'icon' => 'cog', 'permission' => Permission::SOCIETY_SETTINGS],
                    ['label' => 'Roles & access', 'route' => 'settings.roles', 'icon' => 'key', 'permission' => Permission::SOCIETY_MANAGE],
                    ['label' => 'Audit log', 'route' => 'audit.index', 'icon' => 'clipboard', 'permission' => Permission::AUDIT_VIEW],
                ],
            ],
        ];
    }

    private static function allowed(array $item): bool
    {
        // A route that has not been defined yet must not appear in the menu.
        if (! Route::has($item['route'])) {
            return false;
        }

        $user = Auth::user();

        if ($user === null) {
            return false;
        }

        // Platform-only entries are hidden from everyone else, including
        // society administrators.
        if ($item['super_admin_only'] ?? false) {
            return $user->isSuperAdmin();
        }

        $permission = $item['permission'] ?? null;

        if ($permission === null) {
            return true;
        }

        return $user->isSuperAdmin() || $user->can($permission);
    }

    private static function isActive(string $route): bool
    {
        $base = str_contains($route, '.')
            ? substr($route, 0, strrpos($route, '.'))
            : $route;

        return request()->routeIs($route) || request()->routeIs($base.'.*');
    }
}
