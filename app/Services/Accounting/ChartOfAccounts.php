<?php

namespace App\Services\Accounting;

use App\Models\LedgerAccount;
use App\Models\Society;

/**
 * The default chart of accounts for a housing society.
 *
 * Seeded when a society is created. Codes are grouped the conventional way --
 * 1xxx assets, 2xxx liabilities, 3xxx funds, 4xxx income, 5xxx expenses -- so
 * an auditor or accountant recognises the layout immediately.
 */
class ChartOfAccounts
{
    /** code => [name, type, sub_type] */
    public const ACCOUNTS = [
        // Assets
        '1010' => ['Cash in Hand', 'asset', 'current_asset'],
        '1020' => ['Bank Accounts', 'asset', 'current_asset'],
        '1030' => ['Fixed Deposits', 'asset', 'current_asset'],
        '1200' => ['Members Receivable', 'asset', 'receivable'],
        '1500' => ['Fixed Assets', 'asset', 'fixed_asset'],
        '1510' => ['Accumulated Depreciation', 'asset', 'fixed_asset'],

        // Liabilities
        '2000' => ['Sundry Creditors', 'liability', 'payable'],
        '2100' => ['Advance from Members', 'liability', 'current_liability'],
        '2150' => ['Security Deposits Held', 'liability', 'current_liability'],
        '2200' => ['GST Payable', 'liability', 'statutory'],
        '2300' => ['TDS Payable', 'liability', 'statutory'],

        // Funds and reserves
        '3000' => ['General Fund', 'equity', 'fund'],
        '3100' => ['Sinking Fund', 'equity', 'fund'],
        '3200' => ['Corpus Fund', 'equity', 'fund'],
        '3300' => ['Repair and Maintenance Fund', 'equity', 'fund'],

        // Income
        '4000' => ['Maintenance Income', 'income', 'operating'],
        '4100' => ['Water Charges', 'income', 'operating'],
        '4200' => ['Parking Charges', 'income', 'operating'],
        '4300' => ['Amenity Booking Income', 'income', 'operating'],
        '4400' => ['Transfer and NOC Charges', 'income', 'other'],
        '4800' => ['Bank Interest Received', 'income', 'other'],
        '4900' => ['Late Payment Interest', 'income', 'other'],

        // Expenses
        '5000' => ['General Expenses', 'expense', 'operating'],
        '5010' => ['Security Charges', 'expense', 'operating'],
        '5020' => ['Housekeeping', 'expense', 'operating'],
        '5030' => ['Electricity - Common Area', 'expense', 'utilities'],
        '5040' => ['Water Charges', 'expense', 'utilities'],
        '5050' => ['Lift Maintenance', 'expense', 'operating'],
        '5060' => ['Generator and Diesel', 'expense', 'operating'],
        '5070' => ['Repairs and Maintenance', 'expense', 'operating'],
        '5080' => ['Garden and Landscaping', 'expense', 'operating'],
        '5090' => ['Staff Salaries', 'expense', 'payroll'],
        '5100' => ['Professional and Audit Fees', 'expense', 'administrative'],
        '5110' => ['Insurance', 'expense', 'administrative'],
        '5120' => ['Printing and Stationery', 'expense', 'administrative'],
        '5130' => ['Bank Charges', 'expense', 'administrative'],
        '5140' => ['Festival and Events', 'expense', 'other'],
        '5150' => ['Property Tax', 'expense', 'statutory'],
    ];

    public static function nameFor(string $code): string
    {
        return self::ACCOUNTS[$code][0] ?? "Account {$code}";
    }

    public static function typeFor(string $code): string
    {
        return self::ACCOUNTS[$code][1] ?? match (substr($code, 0, 1)) {
            '1' => 'asset',
            '2' => 'liability',
            '3' => 'equity',
            '4' => 'income',
            default => 'expense',
        };
    }

    /** Creates any missing default accounts for a society. */
    public function install(Society $society): int
    {
        $created = 0;

        foreach (self::ACCOUNTS as $code => [$name, $type, $subType]) {
            $account = LedgerAccount::query()
                ->withoutGlobalScopes()
                ->firstOrCreate(
                    ['society_id' => $society->id, 'code' => $code],
                    [
                        'name' => $name,
                        'type' => $type,
                        'sub_type' => $subType,
                        'is_system' => true,
                        'is_active' => true,
                    ],
                );

            if ($account->wasRecentlyCreated) {
                $created++;
            }
        }

        return $created;
    }
}
