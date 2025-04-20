<?php

namespace App\Filament\Resources;

use App\Filament\Resources\GymMemberResource\Pages;
use App\Filament\Resources\GymMemberResource\RelationManagers\PaymentsRelationManager;
use App\Imports\GymMembersImport;
use App\Models\GymMember;
use App\Models\MembershipPlan;
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
                            ->placeholder('e.g., john.doe@example.com')
                            ->extraInputAttributes(['class' => 'border-gray-300 focus:border-blue-500 focus:ring-blue-500']),
                        Forms\Components\TextInput::make('phone')
                            ->tel()
                            ->maxLength(255)
                            ->placeholder('e.g., +91 98765-43210')
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
                            ->relationship('gym', 'name')
                            ->required()
                            ->visible(fn() => auth()->user()->hasRole('admin') || auth()->user()->hasRole('gym_owner'))
                            ->default(function () {
                                if (auth()->user()->hasRole('gym_owner')) {
                                    $gym = auth()->user()->gyms()->first();
                                    return $gym ? $gym->id : null;
                                }
                                return null;
                            })
                            // ->disabled(fn() => auth()->user()->hasRole('gym_owner'))
                            ->placeholder('Select a gym')
                            ->rules([
                                'required_if:visible,true',
                                fn($get) => function ($attribute, $value, $fail) {
                                    if (auth()->user()->hasRole('gym_owner') && !$value) {
                                        $fail('You must create a gym before adding members.');
                                    }
                                },
                            ]),
                        Forms\Components\Select::make('membership_plan_id')
                            ->relationship('membershipPlan', 'name', fn($query) => $query->where('is_active', true))
                            ->required()
                            ->placeholder('Select a membership plan')
                            ->options(function ($get) {
                                $gymId = $get('gym_id') ?? (auth()->user()->hasRole('gym_owner') ? auth()->user()->gyms()->first()->id : null);
                                if ($gymId) {
                                    return MembershipPlan::where('gym_id', $gymId)
                                        ->where('is_active', true)
                                        ->pluck('name', 'id')
                                        ->toArray();
                                }
                                return [];
                            })
                            ->reactive()
                            ->afterStateUpdated(function ($state, callable $set, $get) {
                                if ($state) {
                                    $plan = MembershipPlan::find($state);
                                    if ($plan && $plan->duration) {
                                        $start = $get('membership_start') ?? now();
                                        $set('membership_end', Carbon::parse($start)->addMonths($plan->duration)->toDateString());
                                    }
                                }
                            }),
                        Forms\Components\DatePicker::make('membership_start')
                            ->placeholder('Select start date')
                            ->native(false)
                            ->default(now())
                            ->reactive(),
                        Forms\Components\DatePicker::make('membership_end')
                            ->placeholder('Auto-calculated based on plan')
                            ->disabled()
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
                    ->color(fn($state) => $state === 'Paid' ? 'success' : 'warning'),
                // Tables\Columns\TextColumn::make('payments_sum_amount')
                //     ->label('Total Paid')
                //     ->money('INR')
                //     ->summarize(Tables\Columns\Summarizers\Sum::make())
                //     ->badge()
                //     ->color('success'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('gym_id')
                    ->relationship('gym', 'name')
                    ->visible(fn() => auth()->user()->hasRole('admin')),
                Tables\Filters\SelectFilter::make('membership_plan_id')
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
                            ->relationship('gym', 'name')
                            ->required()
                            ->default(fn() => auth()->user()->hasRole('gym_owner') ? auth()->user()->gyms()->first()->id : null)
                            ->disabled(fn() => auth()->user()->hasRole('gym_owner')),
                        Forms\Components\FileUpload::make('file')
                            ->label('Upload CSV/XLS')
                            ->acceptedFileTypes(['text/csv', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])
                            ->required(),
                    ])
                    ->action(function (array $data) {
                        Excel::import(new GymMembersImport($data['gym_id']), $data['file']);
                        Notification::make()
                            ->title('Members imported successfully')
                            ->success()
                            ->send();
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
