<?php

namespace App\Enums;

/**
 * The role names used across the platform.
 *
 * Roles are stored by spatie/laravel-permission and scoped to a society, so
 * the same person can be a committee treasurer in one society and an ordinary
 * resident in another. SUPER_ADMIN is the exception: it is a platform role
 * held outside any society.
 */
final class Role
{
    public const SUPER_ADMIN = 'super_admin';

    public const SOCIETY_ADMIN = 'society_admin';
    public const PRESIDENT = 'president';
    public const SECRETARY = 'secretary';
    public const TREASURER = 'treasurer';
    public const COMMITTEE_MEMBER = 'committee_member';

    public const MANAGER = 'manager';
    public const ACCOUNTANT = 'accountant';

    public const OWNER = 'owner';
    public const TENANT = 'tenant';

    public const SECURITY_GUARD = 'security_guard';
    public const STAFF = 'staff';
    public const VENDOR = 'vendor';

    /** Roles that run a society day to day and see its money. */
    public const MANAGEMENT = [
        self::SOCIETY_ADMIN,
        self::PRESIDENT,
        self::SECRETARY,
        self::TREASURER,
        self::MANAGER,
        self::ACCOUNTANT,
    ];

    /** Roles held by people who live in the society. */
    public const RESIDENTS = [
        self::OWNER,
        self::TENANT,
    ];

    public static function all(): array
    {
        return [
            self::SUPER_ADMIN, self::SOCIETY_ADMIN, self::PRESIDENT, self::SECRETARY,
            self::TREASURER, self::COMMITTEE_MEMBER, self::MANAGER, self::ACCOUNTANT,
            self::OWNER, self::TENANT, self::SECURITY_GUARD, self::STAFF, self::VENDOR,
        ];
    }

    /** Roles assignable inside a society, i.e. everything but the platform role. */
    public static function assignable(): array
    {
        return array_values(array_diff(self::all(), [self::SUPER_ADMIN]));
    }

    public static function label(string $role): string
    {
        return ucwords(str_replace('_', ' ', $role));
    }
}
