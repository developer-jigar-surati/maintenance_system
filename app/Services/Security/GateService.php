<?php

namespace App\Services\Security;

use App\Models\GatePass;
use App\Models\Society;
use App\Models\Unit;
use App\Models\User;
use App\Models\Visitor;
use App\Models\VisitorLog;
use App\Services\NumberGenerator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Gate operations: expected visitors, walk-ins, entry and exit, and the
 * material-movement passes that stop things leaving the premises unnoticed.
 */
class GateService
{
    public function __construct(private NumberGenerator $numbers) {}

    /**
     * A resident pre-approves someone, so the guard can wave them through
     * without phoning the flat.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function preApprove(Unit $unit, array $attributes, User $by): VisitorLog
    {
        $expectedAt = isset($attributes['expected_at'])
            ? Carbon::parse($attributes['expected_at'])
            : now();

        return VisitorLog::create([
            'society_id' => $unit->society_id,
            'unit_id' => $unit->id,
            'visitor_id' => $attributes['visitor_id'] ?? null,
            'visitor_name' => $attributes['visitor_name'],
            'phone' => $attributes['phone'] ?? null,
            'purpose' => $attributes['purpose'] ?? 'guest',
            'company' => $attributes['company'] ?? null,
            'vehicle_number' => $attributes['vehicle_number'] ?? null,
            'accompanying_count' => (int) ($attributes['accompanying_count'] ?? 0),
            'expected_at' => $expectedAt,
            'expected_until' => isset($attributes['expected_until'])
                ? Carbon::parse($attributes['expected_until'])
                : $expectedAt->copy()->addHours(12),
            'pre_approved_by' => $by->id,
            'approved_by' => $by->id,
            'approved_at' => now(),
            'status' => 'expected',
        ]);
    }

    /**
     * The guard logs a walk-in. Whether the resident must approve first is a
     * society setting; when it is on, the visitor waits at the gate.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function logArrival(Society $society, array $attributes, ?User $guard = null): VisitorLog
    {
        $requiresApproval = (bool) $society->setting('visitors.require_resident_approval', true);

        return DB::transaction(function () use ($society, $attributes, $guard, $requiresApproval) {
            $visitor = $this->rememberVisitor($society, $attributes);

            return VisitorLog::create([
                'society_id' => $society->id,
                'visitor_id' => $visitor?->id,
                'unit_id' => $attributes['unit_id'] ?? null,
                'gate_id' => $attributes['gate_id'] ?? null,
                'visitor_name' => $attributes['visitor_name'],
                'phone' => $attributes['phone'] ?? null,
                'purpose' => $attributes['purpose'] ?? 'guest',
                'company' => $attributes['company'] ?? null,
                'vehicle_number' => $attributes['vehicle_number'] ?? null,
                'accompanying_count' => (int) ($attributes['accompanying_count'] ?? 0),
                'photo_path' => $attributes['photo_path'] ?? null,
                'status' => $requiresApproval ? 'pending_approval' : 'approved',
                'recorded_by' => $guard?->id,
            ]);
        });
    }

    public function approveEntry(VisitorLog $log, User $approver): VisitorLog
    {
        $log->forceFill([
            'status' => 'approved',
            'approved_by' => $approver->id,
            'approved_at' => now(),
        ])->save();

        return $log;
    }

    public function denyEntry(VisitorLog $log, User $approver, ?string $reason = null): VisitorLog
    {
        $log->forceFill([
            'status' => 'denied',
            'approved_by' => $approver->id,
            'approved_at' => now(),
            'notes' => $reason,
        ])->save();

        return $log;
    }

    public function checkIn(VisitorLog $log, ?User $guard = null): VisitorLog
    {
        if (! in_array($log->status, ['expected', 'approved'], true)) {
            throw new \DomainException('This visitor has not been approved for entry.');
        }

        $log->forceFill([
            'status' => 'inside',
            'entered_at' => now(),
            'recorded_by' => $guard?->id ?? $log->recorded_by,
        ])->save();

        return $log;
    }

    public function checkOut(VisitorLog $log, ?User $guard = null): VisitorLog
    {
        $log->forceFill([
            'status' => 'exited',
            'exited_at' => now(),
            'recorded_by' => $guard?->id ?? $log->recorded_by,
        ])->save();

        return $log;
    }

    /** Looks a visitor up by the code they were given, for the gate screen. */
    public function findByPassCode(Society $society, string $code): ?VisitorLog
    {
        return VisitorLog::query()
            ->where('society_id', $society->id)
            ->where('pass_code', strtoupper(trim($code)))
            ->whereIn('status', ['expected', 'approved'])
            ->latest()
            ->first();
    }

    /**
     * Raises a material or move-in/out pass. Society settings decide whether
     * the committee must approve it or the resident's word is enough.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function issuePass(Unit $unit, array $attributes, User $by): GatePass
    {
        $society = $unit->society;

        return GatePass::create([
            'society_id' => $society->id,
            'unit_id' => $unit->id,
            'pass_number' => $this->numbers->next(NumberGenerator::GATE_PASS, $society),
            'type' => $attributes['type'] ?? 'material_out',
            'issued_to_name' => $attributes['issued_to_name'],
            'phone' => $attributes['phone'] ?? null,
            'vehicle_number' => $attributes['vehicle_number'] ?? null,
            'items' => $attributes['items'] ?? null,
            'purpose' => $attributes['purpose'] ?? null,
            'valid_from' => $attributes['valid_from'] ?? now(),
            'valid_to' => $attributes['valid_to'] ?? now()->endOfDay(),
            'status' => 'pending_approval',
            'requested_by' => $by->id,
        ]);
    }

    public function approvePass(GatePass $pass, User $approver): GatePass
    {
        $pass->forceFill([
            'status' => 'approved',
            'approved_by' => $approver->id,
            'approved_at' => now(),
        ])->save();

        return $pass;
    }

    /** Scanned at the gate. Marks the pass used so it cannot be reused. */
    public function redeemPass(GatePass $pass, ?User $guard = null): GatePass
    {
        if (! $pass->isUsable()) {
            throw new \DomainException('This gate pass is not valid right now.');
        }

        $pass->forceFill([
            'status' => 'used',
            'used_at' => now(),
            'verified_by' => $guard?->id,
        ])->save();

        return $pass;
    }

    /**
     * Keeps a directory of repeat visitors so the guard types a phone number
     * once rather than at every visit.
     */
    private function rememberVisitor(Society $society, array $attributes): ?Visitor
    {
        if (blank($attributes['phone'] ?? null)) {
            return null;
        }

        $visitor = Visitor::firstOrCreate(
            ['society_id' => $society->id, 'phone' => $attributes['phone']],
            [
                'name' => $attributes['visitor_name'],
                'company' => $attributes['company'] ?? null,
                'photo_path' => $attributes['photo_path'] ?? null,
            ],
        );

        if (! $visitor->wasRecentlyCreated && $visitor->logs()->count() >= 3) {
            $visitor->forceFill(['is_frequent' => true])->save();
        }

        return $visitor;
    }

    /** Anyone still inside, for the guard's handover at shift change. */
    public function currentlyInside(Society $society)
    {
        return VisitorLog::query()
            ->where('society_id', $society->id)
            ->inside()
            ->with(['unit.block'])
            ->orderByDesc('entered_at')
            ->get();
    }
}
