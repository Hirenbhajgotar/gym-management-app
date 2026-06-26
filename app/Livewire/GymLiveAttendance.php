<?php

namespace App\Livewire;

use App\Models\Attendances;
use App\Models\GymMember;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\On;
use Livewire\Component;

final class GymLiveAttendance extends Component
{
    public Collection $todayAttendances;

    public int $checkedInCount = 0;

    public int $checkedOutCount = 0;

    public int $activeCount = 0;

    public string $search = '';

    protected $listeners = [
        'echo:gym.{gymId}.attendance,MemberCheckedIn' => 'handleCheckIn',
        'echo:gym.{gymId}.attendance,MemberCheckedOut' => 'handleCheckOut',
    ];

    public function mount(): void
    {
        $this->loadAttendances();
    }

    protected function loadAttendances(): void
    {
        $gym = Filament::getTenant();

        if (! $gym) {
            return;
        }

        $query = Attendances::query()
            ->with('attendanceable')
            ->where('gym_id', $gym->id)
            ->whereDate('created_at', now()->toDateString())
            ->where('attendanceable_type', GymMember::class);

        if ($this->search) {
            $query->whereHas('attendanceable', function (Builder $q) {
                $q->where('name', 'like', "%{$this->search}%");
            });
        }

        $this->todayAttendances = $query->orderByDesc('check_in_time')->get();

        $this->updateCounts();
    }

    protected function updateCounts(): void
    {
        $this->checkedInCount = $this->todayAttendances->whereNotNull('check_in_time')->count();
        $this->checkedOutCount = $this->todayAttendances->whereNotNull('check_out_time')->count();
        $this->activeCount = $this->todayAttendances->filter(function ($attendance) {
            return $attendance->check_in_time && ! $attendance->check_out_time;
        })->count();
    }

    #[On('echo:gym.{gymId}.attendance,MemberCheckedIn')]
    public function handleCheckIn(array $data): void
    {
        $this->loadAttendances();
    }

    #[On('echo:gym.{gymId}.attendance,MemberCheckedOut')]
    public function handleCheckOut(array $data): void
    {
        $this->loadAttendances();
    }

    public function updatingSearch(): void
    {
        $this->loadAttendances();
    }

    public function render()
    {
        return view('livewire.gym-live-attendance');
    }
}
