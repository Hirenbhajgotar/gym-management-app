<?php

namespace App\Filament\Resources;

use App\Filament\Resources\GymMemberResource\Pages;
use App\Filament\Resources\GymMemberResource\RelationManagers\PaymentsRelationManager;
use App\Imports\GymMembersImport;
use App\Models\GymMember;
use App\Models\MembershipPlan;
use App\Rules\ValidEmailDomain;
use App\Rules\ValidPhone;
use Carbon\Carbon;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Support\Facades\Storage;

class GymMemberResource extends Resource
{
    protected static ?string $model = GymMember::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationLabel = 'Members';

    protected static ?string $navigationGroup = 'Gym Management';

    // Restrict to admins or gym owners with gyms
    public static function canAccess(array $parameters = []): bool
    {
        $user = auth()->user();
        return $user->hasRole('admin') || ($user->hasRole('gym_owner') && $user->gyms()->exists());
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Personal Information')
                    ->icon('heroicon-o-user')
                    ->description('Enter the member\'s personal details')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('e.g., John Doe')
                            ->autofocus()
                            ->extraInputAttributes(['class' => 'border-gray-300 focus:border-blue-500 focus:ring-blue-500']),
                        Forms\Components\TextInput::make('email')
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->unique(GymMember::class, 'email', ignoreRecord: true)
                            ->rules(['email:rfc'])
                            ->validationMessages([
                                'email' => 'Please enter a valid email address.',
                                'email.rfc' => 'The email format is invalid.',
                                'email.dns' => 'The email domain does not exist.',
                            ])
                            ->placeholder('e.g., john.doe@example.com')
                            ->extraInputAttributes(['class' => 'border-gray-300 focus:border-blue-500 focus:ring-blue-500']),
                        Forms\Components\TextInput::make('phone')
                            ->tel()
                            ->maxLength(255)
                            ->rules([new ValidPhone])
                            ->validationMessages([
                                'phone' => 'The phone must be a valid 10-digit phone number.',
                            ])
                            ->placeholder('e.g., 9876543210')
                            ->extraInputAttributes(['class' => 'border-gray-300 focus:border-blue-500 focus:ring-blue-500']),
                        Forms\Components\Textarea::make('address')
                            ->maxLength(65535)
                            ->placeholder('e.g., 123 Fitness Street, City')
                            ->rows(4)
                            ->extraAttributes(['class' => 'border-gray-300 focus:border-blue-500 focus:ring-blue-500']),
                        Forms\Components\DatePicker::make('dob')
                            ->label('Date of Birth')
                            ->placeholder('Select date')
                            ->native(false),
                    ])
                    ->collapsible()
                    ->columns(2),
                Forms\Components\Section::make('Membership Details')
                    ->icon('heroicon-o-ticket')
                    ->description('Configure membership settings')
                    ->schema([
                        Forms\Components\Select::make('gym_id')
                            ->relationship('gym', 'name', function ($query) {
                                if (auth()->user()->hasRole('gym_owner')) {
                                    return $query->whereIn('id', auth()->user()->gyms()->pluck('id'));
                                }
                                return $query;
                            })
                            ->required()
                            ->visible(fn() => auth()->user()->hasRole('admin') || auth()->user()->hasRole('gym_owner'))
                            ->default(function () {
                                if (auth()->user()->hasRole('gym_owner')) {
                                    $gym = auth()->user()->gyms()->first();
                                    return $gym ? $gym->id : null;
                                }
                                return null;
                            })
                            ->placeholder('Select a gym')
                            ->live()
                            ->afterStateUpdated(fn(callable $set) => $set('gym_membership_plan_id', null))
                            ->rules([
                                'required_if:visible,true',
                                fn($get) => function ($attribute, $value, $fail) {
                                    if (auth()->user()->hasRole('gym_owner') && !$value) {
                                        $fail('You must create a gym before adding members.');
                                    }
                                },
                            ]),
                        Forms\Components\Select::make('gym_membership_plan_id')
                            ->label('Membership Plan')
                            ->required()
                            ->placeholder('Select a membership plan')
                            ->reactive()
                            ->afterStateUpdated(function ($state, callable $set, $get) {
                                if ($state) {
                                    $plan = MembershipPlan::find($state);
                                    if ($plan && $plan->duration) {
                                        $start = $get('membership_start') ?? now();
                                        $set('membership_end', Carbon::parse($start)->addMonths($plan->duration)->toDateString());
                                    }
                                }
                            })
                            ->options(function (callable $get) {
                                $gymId = $get('gym_id');
                                if (!$gymId) return [];

                                return MembershipPlan::query()
                                    ->where('gym_id', $gymId)
                                    ->where('is_active', true)
                                    ->pluck('name', 'id')
                                    ->toArray();
                            })
                            ->disabled(fn($get) => !$get('gym_id')),
                        Forms\Components\DatePicker::make('membership_start')
                            ->placeholder('Select start date')
                            ->live()
                            ->native(false)
                            ->default(now())
                            ->reactive()
                            ->afterStateUpdated(function ($state, callable $set, $get) {
                                $planId = $get('gym_membership_plan_id');
                                if ($planId && $state) {
                                    $plan = MembershipPlan::find($planId);
                                    if ($plan && $plan->duration) {
                                        $set('membership_end', Carbon::parse($state)->addMonths($plan->duration)->toDateString());
                                    }
                                }
                            }),
                        Forms\Components\DatePicker::make('membership_end')
                            ->placeholder('Auto-calculated based on plan')
                            ->disabled()
                            ->native(false)
                            ->dehydrated(false),
                    ])
                    ->collapsible()
                    ->columns(2),
            ])
            ->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->formatStateUsing(fn($state) => "<div class='flex items-center gap-2'>
                        <span class='inline-flex items-center justify-center w-8 h-8 bg-green-100 text-green-800 rounded-full'>
                            " . strtoupper(substr($state, 0, 1)) . "
                        </span>
                        <span class='font-semibold'>$state</span>
                    </div>")
                    ->html(),
                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->copyable()
                    ->copyMessage('Email copied!')
                    ->icon('heroicon-o-envelope')
                    ->color('gray'),
                Tables\Columns\TextColumn::make('phone')
                    ->searchable()
                    ->icon('heroicon-o-phone')
                    ->color('gray'),
                Tables\Columns\TextColumn::make('gym.name')
                    ->label('Gym')
                    ->badge()
                    ->color('primary'),
                Tables\Columns\TextColumn::make('membershipPlan.name')
                    ->label('Plan')
                    ->badge()
                    ->color('success'),
                Tables\Columns\TextColumn::make('membership_start')
                    ->date()
                    ->badge()
                    ->color('info'),
                Tables\Columns\TextColumn::make('membership_end')
                    ->date()
                    ->badge()
                    ->color(fn($state) => now()->greaterThan($state) ? 'danger' : 'warning'),
                Tables\Columns\TextColumn::make('payment_status')
                    ->label('Payment Status')
                    ->getStateUsing(function ($record) {
                        $payment = $record->payments()->where('status', 'completed')->first();
                        $status = $payment ? 'Paid' : 'Unpaid';
                        return $status;
                    })
                    ->badge()
                    ->color(fn($state) => $state === 'Paid' ? 'success' : 'danger'),
                // Tables\Columns\TextColumn::make('payments_sum_amount')
                //     ->label('Total Paid')
                //     ->money('INR')
                //     ->summarize(Tables\Columns\Summarizers\Sum::make())
                //     ->badge()
                //     ->color('success'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('gym_id')
                    ->relationship('gym', 'name', function ($query) {
                        if (auth()->user()->hasRole('gym_owner')) {
                            return $query->whereIn('id', auth()->user()?->gyms()->pluck('id'));
                        }
                        if (auth()->user()->hasRole('admin')) {
                            return $query;
                        }
                    })
                    ->label('Gym')
                    ->visible(fn() => auth()->user()->hasRole('admin') || auth()->user()->hasRole('gym_owner')),
                Tables\Filters\SelectFilter::make('gym_membership_plan_id')
                    ->relationship('membershipPlan', 'name')
                    ->label('Plan'),
                SelectFilter::make('payment_status')
                    ->options([
                        'paid' => 'Paid',
                        'unpaid' => 'Unpaid',
                    ])
                    ->query(function ($query, array $state) {
                        if (!empty($state['value'])) {
                            $value = $state['value'];
                            if ($value === 'paid') {
                                $query->whereHas('payments', function ($q) {
                                    $q->where('status', 'completed');
                                });
                            } elseif ($value === 'unpaid') {
                                $query->whereDoesntHave('payments', function ($q) {
                                    $q->where('status', 'completed');
                                });
                            }
                        }
                    })
                    ->label('Payment Status'),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->icon('heroicon-o-pencil')
                    ->color('primary')
                    ->visible(fn() => auth()->user()->hasPermissionTo('update_gym::member')),
                Tables\Actions\DeleteAction::make()
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->visible(fn() => auth()->user()->hasPermissionTo('delete_gym::member')),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->headerActions([
                Tables\Actions\Action::make('download_template')
                    ->label('Download CSV Template')
                    ->icon('heroicon-o-document-arrow-down')
                    ->action(fn() => response()->download(resource_path('views/templates/gym_members_import_template.csv')))
                    ->color('info'),
                Tables\Actions\Action::make('import')
                    ->label('Import Members')
                    ->icon('heroicon-o-document-plus')
                    ->form([
                        Forms\Components\Select::make('gym_id')
                            ->relationship('gym', 'name', function ($query) {
                                if (auth()->user()->hasRole('gym_owner')) {
                                    return $query->whereIn('id', auth()->user()->gyms()->pluck('id'));
                                }
                                return $query;
                            })
                            ->required()
                            ->default(fn() => auth()->user()->hasRole('gym_owner') ? auth()->user()?->gyms()?->first()?->id : null),
                        Forms\Components\FileUpload::make('file')
                            ->label('Upload CSV/XLS')
                            ->acceptedFileTypes(['text/csv', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])
                            ->required()
                            ->storeFiles(false) // Prevent automatic storage
                            ->getUploadedFileNameForStorageUsing(
                                fn($file) => 'imports/' . $file->getClientOriginalName()
                            ),
                    ])
                    ->action(function (array $data) {
                        try {
                            $file = $data['file']; // TemporaryUploadedFile object
                            $filePath = $file->getRealPath(); // Get the actual file path (e.g., C:\Users\SID\AppData\Local\Temp\phpEB47.tmp)

                            if (!file_exists($filePath)) {
                                throw new \Exception('Uploaded file not found.');
                            }
                            Excel::import(new GymMembersImport($data['gym_id']), $filePath);
                        } catch (\Exception $e) {
                            Log::error('Import failed:', ['error' => $e->getMessage()]);
                            Notification::make()
                                ->title('Import failed')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    })
                    ->requiresConfirmation()
                    ->visible(fn() => auth()->user()->hasRole('gym_owner') || auth()->user()->hasRole('admin')),
            ])
            ->modifyQueryUsing(function ($query) {
                $user = auth()->user();
                if ($user->hasRole('gym_owner')) {
                    $query->whereIn('gym_id', $user->gyms()->pluck('id'));
                }
                return $query;
            })
            ->defaultSort('name')
            ->striped();
    }

    public static function getRelations(): array
    {
        return [
            PaymentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListGymMembers::route('/'),
            'create' => Pages\CreateGymMember::route('/create'),
            'edit' => Pages\EditGymMember::route('/{record}/edit'),
        ];
    }
}
