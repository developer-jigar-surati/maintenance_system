<?php

namespace App\Livewire\Platform;

use App\Enums\Role as RoleName;
use App\Livewire\Concerns\WithDataTable;
use App\Models\Society;
use App\Models\Unit;
use App\Models\User;
use App\Services\SocietyProvisioner;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * The platform operator's console: every society on the installation, and the
 * form that creates a new one.
 *
 * Deliberately outside the society-scoped part of the app. A super admin is
 * not a member of the societies they administer, so this screen reads across
 * all of them rather than through the usual scope.
 */
#[Layout('components.layouts.app')]
class SocietyIndex extends Component
{
    use WithDataTable;

    #[Url(except: '')]
    public string $type = '';

    #[Url(except: '')]
    public string $status = '';

    // --- new society form ---------------------------------------------

    public string $name = '';

    public string $newType = 'apartment';

    public string $city = '';

    public string $state = '';

    public string $areaUnit = 'sqft';

    public int $financialYearStart = 4;

    public string $paymentMode = 'offline';

    /** The first administrator. A society without one cannot be run. */
    public string $adminName = '';

    public string $adminEmail = '';

    public string $adminPhone = '';

    public string $adminPassword = '';

    protected function sortableColumns(): array
    {
        return ['name', 'type', 'status', 'created_at'];
    }

    protected function defaultSort(): array
    {
        return ['name', 'asc'];
    }

    protected function filterProperties(): array
    {
        return ['type', 'status'];
    }

    public function create(SocietyProvisioner $provisioner): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'min:3', 'max:180'],
            'newType' => ['required', 'string'],
            'city' => ['nullable', 'string', 'max:80'],
            'state' => ['nullable', 'string', 'max:80'],
            'areaUnit' => ['required', Rule::in(['sqft', 'sqm', 'sqyd'])],
            'financialYearStart' => ['required', 'integer', 'min:1', 'max:12'],
            'paymentMode' => ['required', Rule::in(['offline', 'online', 'both'])],
            'adminName' => ['required', 'string', 'min:2', 'max:120'],
            'adminEmail' => ['required', 'email', 'max:180'],
            'adminPhone' => ['nullable', 'string', 'max:20'],
            'adminPassword' => ['nullable', 'string', 'min:8', 'max:100'],
        ]);

        $society = DB::transaction(function () use ($validated, $provisioner) {
            $society = $provisioner->create([
                'name' => $validated['name'],
                'type' => $validated['newType'],
                'city' => $validated['city'] ?: null,
                'state' => $validated['state'] ?: null,
                'area_unit' => $validated['areaUnit'],
                'financial_year_start_month' => $validated['financialYearStart'],
                'payment_mode' => $validated['paymentMode'],
            ], auth()->user());

            // An existing account is reused rather than duplicated: the same
            // person may already administer another society.
            $admin = User::where('email', $validated['adminEmail'])->first();

            if ($admin === null) {
                $admin = User::create([
                    'name' => $validated['adminName'],
                    'email' => $validated['adminEmail'],
                    'phone' => $validated['adminPhone'] ?: null,
                    'password' => $validated['adminPassword'] ?: Str::random(16),
                ]);
            }

            $provisioner->attachAdministrator($society, $admin, RoleName::SOCIETY_ADMIN);

            return $society;
        });

        $this->reset(['name', 'city', 'state', 'adminName', 'adminEmail', 'adminPhone', 'adminPassword']);
        $this->dispatch('close-modal', 'new-society');
        $this->dispatch('notify',
            message: "{$society->name} created. Open it to finish setting it up.",
            tone: 'positive');
    }

    /** Jumps the operator into a society so they can run its onboarding. */
    public function open(int $societyId)
    {
        $society = Society::findOrFail($societyId);

        auth()->user()->switchTo($society);

        return redirect()->route($society->isOnboarding() ? 'onboarding.index' : 'dashboard');
    }

    public function render()
    {
        $query = Society::query()
            ->withCount(['units', 'users'])
            ->when($this->search !== '', fn (Builder $q) => $q->where(function (Builder $i) {
                $i->where('name', 'like', "%{$this->search}%")
                    ->orWhere('code', 'like', "%{$this->search}%")
                    ->orWhere('city', 'like', "%{$this->search}%");
            }))
            ->when($this->type !== '', fn (Builder $q) => $q->where('type', $this->type))
            ->when($this->status !== '', fn (Builder $q) => $q->where('status', $this->status));

        return view('livewire.platform.society-index', [
            'societies' => $this->applySort($query)->paginate($this->perPage),
            'totals' => [
                'societies' => Society::count(),
                'units' => Unit::query()->withoutGlobalScopes()->count(),
                'onboarding' => Society::where('status', 'onboarding')->count(),
            ],
            'types' => [
                'apartment' => 'Apartment complex',
                'villa' => 'Villa project',
                'row_house' => 'Row houses',
                'bungalow' => 'Bungalows',
                'gated_community' => 'Gated community',
                'township' => 'Township',
                'plotted_development' => 'Plotted development',
                'builder_floor' => 'Builder floors',
                'commercial_complex' => 'Commercial complex',
                'office_park' => 'Office park',
                'industrial_estate' => 'Industrial estate',
                'mixed_use' => 'Mixed use',
                'cooperative_housing' => 'Co-operative housing society',
                'student_housing' => 'Student housing',
                'co_living' => 'Co-living',
                'other' => 'Other',
            ],
        ])->title('All societies');
    }
}
