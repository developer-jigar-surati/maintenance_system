<?php

namespace App\Enums;

/**
 * Permission names, grouped by module.
 *
 * Kept as constants rather than free strings so a typo in a Blade @can or a
 * policy fails loudly at the seeder instead of silently denying access.
 */
final class Permission
{
    // Society administration
    public const SOCIETY_VIEW = 'society.view';

    public const SOCIETY_MANAGE = 'society.manage';

    public const SOCIETY_SETTINGS = 'society.settings';

    // Property
    public const UNIT_VIEW = 'unit.view';

    public const UNIT_MANAGE = 'unit.manage';

    public const RESIDENT_VIEW = 'resident.view';

    public const RESIDENT_MANAGE = 'resident.manage';

    public const DIRECTORY_VIEW = 'directory.view';

    /**
     * Who used to live in a flat, why they left, and who voted which way.
     *
     * Deliberately its own permission rather than part of `resident.view`.
     * Seeing who lives in 402 today is a directory; seeing that the previous
     * tenant was asked to leave, or how a neighbour voted, is the society's
     * record and not a neighbour's business. A resident who needs it asks the
     * secretary, who can answer from the record.
     */
    public const HISTORY_VIEW = 'history.view';

    // Billing and collection
    public const BILLING_VIEW = 'billing.view';

    public const BILLING_MANAGE = 'billing.manage';

    public const INVOICE_GENERATE = 'invoice.generate';

    public const INVOICE_CANCEL = 'invoice.cancel';

    public const PAYMENT_VIEW = 'payment.view';

    public const PAYMENT_RECORD = 'payment.record';

    public const PAYMENT_APPROVE = 'payment.approve';

    public const ADJUSTMENT_APPROVE = 'adjustment.approve';

    // Accounting
    public const ACCOUNTING_VIEW = 'accounting.view';

    public const ACCOUNTING_MANAGE = 'accounting.manage';

    public const EXPENSE_VIEW = 'expense.view';

    public const EXPENSE_MANAGE = 'expense.manage';

    public const EXPENSE_APPROVE = 'expense.approve';

    public const REPORT_VIEW = 'report.view';

    public const BUDGET_MANAGE = 'budget.manage';

    // Governance
    public const MEETING_VIEW = 'meeting.view';

    public const MEETING_MANAGE = 'meeting.manage';

    public const MINUTES_PUBLISH = 'minutes.publish';

    public const POLL_VIEW = 'poll.view';

    public const POLL_MANAGE = 'poll.manage';

    public const COMMITTEE_MANAGE = 'committee.manage';

    // Helpdesk
    public const COMPLAINT_VIEW_OWN = 'complaint.view_own';

    public const COMPLAINT_VIEW_ALL = 'complaint.view_all';

    public const COMPLAINT_CREATE = 'complaint.create';

    public const COMPLAINT_MANAGE = 'complaint.manage';

    // Facilities
    public const ASSET_VIEW = 'asset.view';

    public const ASSET_MANAGE = 'asset.manage';

    public const WORK_ORDER_VIEW = 'work_order.view';

    public const WORK_ORDER_MANAGE = 'work_order.manage';

    public const AMENITY_VIEW = 'amenity.view';

    public const AMENITY_BOOK = 'amenity.book';

    public const AMENITY_MANAGE = 'amenity.manage';

    // People
    public const STAFF_VIEW = 'staff.view';

    public const STAFF_MANAGE = 'staff.manage';

    public const VENDOR_VIEW = 'vendor.view';

    public const VENDOR_MANAGE = 'vendor.manage';

    // Security
    public const VISITOR_VIEW = 'visitor.view';

    public const VISITOR_MANAGE = 'visitor.manage';

    public const GATE_OPERATE = 'gate.operate';

    public const GATE_PASS_APPROVE = 'gate_pass.approve';

    // Communication
    public const NOTICE_VIEW = 'notice.view';

    public const NOTICE_MANAGE = 'notice.manage';

    public const DOCUMENT_VIEW = 'document.view';

    public const DOCUMENT_MANAGE = 'document.manage';

    public const AUDIT_VIEW = 'audit.view';

    public static function all(): array
    {
        return array_values((new \ReflectionClass(self::class))->getConstants());
    }

    /**
     * Default permission set per role. Seeded once per society and editable
     * afterwards, so a committee can tighten or loosen its own setup.
     */
    public static function defaultsForRole(string $role): array
    {
        return match ($role) {
            Role::SOCIETY_ADMIN, Role::PRESIDENT => self::all(),

            Role::SECRETARY => [
                self::SOCIETY_VIEW, self::UNIT_VIEW, self::UNIT_MANAGE,
                self::RESIDENT_VIEW, self::RESIDENT_MANAGE, self::DIRECTORY_VIEW,
                self::HISTORY_VIEW,
                self::BILLING_VIEW, self::PAYMENT_VIEW, self::REPORT_VIEW,
                self::MEETING_VIEW, self::MEETING_MANAGE, self::MINUTES_PUBLISH,
                self::POLL_VIEW, self::POLL_MANAGE, self::COMMITTEE_MANAGE,
                self::COMPLAINT_VIEW_ALL, self::COMPLAINT_CREATE, self::COMPLAINT_MANAGE,
                self::ASSET_VIEW, self::WORK_ORDER_VIEW, self::WORK_ORDER_MANAGE,
                self::AMENITY_VIEW, self::AMENITY_BOOK, self::AMENITY_MANAGE,
                self::STAFF_VIEW, self::STAFF_MANAGE, self::VENDOR_VIEW,
                self::VISITOR_VIEW, self::VISITOR_MANAGE, self::GATE_PASS_APPROVE,
                self::NOTICE_VIEW, self::NOTICE_MANAGE,
                self::DOCUMENT_VIEW, self::DOCUMENT_MANAGE, self::AUDIT_VIEW,
            ],

            Role::TREASURER, Role::ACCOUNTANT => [
                self::SOCIETY_VIEW, self::UNIT_VIEW, self::RESIDENT_VIEW, self::DIRECTORY_VIEW,
                self::BILLING_VIEW, self::BILLING_MANAGE, self::INVOICE_GENERATE, self::INVOICE_CANCEL,
                self::PAYMENT_VIEW, self::PAYMENT_RECORD, self::PAYMENT_APPROVE, self::ADJUSTMENT_APPROVE,
                self::ACCOUNTING_VIEW, self::ACCOUNTING_MANAGE, self::BUDGET_MANAGE,
                self::EXPENSE_VIEW, self::EXPENSE_MANAGE, self::EXPENSE_APPROVE,
                self::REPORT_VIEW, self::VENDOR_VIEW, self::VENDOR_MANAGE,
                self::MEETING_VIEW, self::NOTICE_VIEW, self::DOCUMENT_VIEW,
                self::COMPLAINT_VIEW_ALL,
            ],

            Role::MANAGER => [
                self::SOCIETY_VIEW, self::UNIT_VIEW, self::UNIT_MANAGE,
                self::RESIDENT_VIEW, self::RESIDENT_MANAGE, self::DIRECTORY_VIEW,
                self::BILLING_VIEW, self::PAYMENT_VIEW, self::PAYMENT_RECORD,
                self::EXPENSE_VIEW, self::EXPENSE_MANAGE, self::REPORT_VIEW,
                self::COMPLAINT_VIEW_ALL, self::COMPLAINT_CREATE, self::COMPLAINT_MANAGE,
                self::ASSET_VIEW, self::ASSET_MANAGE,
                self::WORK_ORDER_VIEW, self::WORK_ORDER_MANAGE,
                self::AMENITY_VIEW, self::AMENITY_BOOK, self::AMENITY_MANAGE,
                self::STAFF_VIEW, self::STAFF_MANAGE, self::VENDOR_VIEW, self::VENDOR_MANAGE,
                self::VISITOR_VIEW, self::VISITOR_MANAGE, self::GATE_PASS_APPROVE,
                self::NOTICE_VIEW, self::NOTICE_MANAGE, self::DOCUMENT_VIEW, self::DOCUMENT_MANAGE,
                self::MEETING_VIEW,
            ],

            Role::COMMITTEE_MEMBER => [
                self::SOCIETY_VIEW, self::UNIT_VIEW, self::RESIDENT_VIEW, self::DIRECTORY_VIEW,
                self::BILLING_VIEW, self::PAYMENT_VIEW, self::EXPENSE_VIEW, self::REPORT_VIEW,
                self::MEETING_VIEW, self::POLL_VIEW, self::COMPLAINT_VIEW_ALL, self::COMPLAINT_CREATE,
                self::ASSET_VIEW, self::WORK_ORDER_VIEW, self::AMENITY_VIEW, self::AMENITY_BOOK,
                self::NOTICE_VIEW, self::DOCUMENT_VIEW, self::VISITOR_VIEW,
            ],

            // A resident sees their own money, their own tickets, and whatever
            // the society publishes to everyone.
            Role::OWNER, Role::TENANT => [
                self::DIRECTORY_VIEW, self::BILLING_VIEW, self::PAYMENT_VIEW,
                self::MEETING_VIEW, self::POLL_VIEW,
                self::COMPLAINT_VIEW_OWN, self::COMPLAINT_CREATE,
                self::AMENITY_VIEW, self::AMENITY_BOOK,
                self::NOTICE_VIEW, self::DOCUMENT_VIEW, self::VISITOR_MANAGE,
            ],

            Role::SECURITY_GUARD => [
                self::VISITOR_VIEW, self::VISITOR_MANAGE, self::GATE_OPERATE,
                self::DIRECTORY_VIEW, self::NOTICE_VIEW, self::STAFF_VIEW,
            ],

            Role::STAFF => [
                self::COMPLAINT_VIEW_ALL, self::WORK_ORDER_VIEW, self::WORK_ORDER_MANAGE,
                self::ASSET_VIEW, self::NOTICE_VIEW,
            ],

            Role::VENDOR => [
                self::WORK_ORDER_VIEW, self::COMPLAINT_VIEW_ALL,
            ],

            default => [],
        };
    }
}
