<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\ReceptionistResource\Pages;

use App\Filament\Admin\Resources\ReceptionistResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\Group;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\Split;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewReceptionist extends ViewRecord
{
    protected static string $resource = ReceptionistResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()
                ->icon('heroicon-o-pencil-square'),
            DeleteAction::make()
                ->icon('heroicon-o-trash'),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                // Profile Section with Avatar and Key Details
                Section::make('Receptionist Profile')
                    ->description('Key information about the receptionist')
                    ->icon('heroicon-o-user-circle')
                    ->schema([
                        Split::make([
                            ImageEntry::make('profile_photo')
                                ->label('')
                                ->circular()
                                ->grow(false),

                            Grid::make(2)
                                ->schema([
                                    Group::make([
                                        TextEntry::make('name')
                                            ->label('Full Name')
                                            ->weight('bold')
                                            ->size('lg')
                                            ->icon('heroicon-o-user'),

                                        TextEntry::make('email')
                                            ->copyable()
                                            ->icon('heroicon-o-envelope')
                                            ->url(fn ($record) => "mailto:{$record->email}")
                                            ->openUrlInNewTab(),

                                        TextEntry::make('phone')
                                            ->icon('heroicon-o-phone')
                                            ->url(fn ($record) => "tel:{$record->phone}")
                                            ->openUrlInNewTab(),

                                        TextEntry::make('employeeDetails.gender')
                                            ->label('Gender')
                                            ->badge()
                                            ->color('primary')
                                            ->icon('heroicon-o-users'),
                                    ]),

                                    Group::make([
                                        TextEntry::make('gyms.name')
                                            ->label('Assigned Gym')
                                            ->icon('heroicon-o-building-office-2'),

                                        IconEntry::make('is_active')
                                            ->label('Account Status')
                                            ->boolean()
                                            ->trueIcon('heroicon-o-check-circle')
                                            ->falseIcon('heroicon-o-x-circle')
                                            ->trueColor('success')
                                            ->falseColor('danger'),

                                        TextEntry::make('employeeDetails.hire_date')
                                            ->label('Hire Date')
                                            ->date('M d, Y')
                                            ->icon('heroicon-o-calendar'),

                                        TextEntry::make('employeeDetails.monthly_salary')
                                            ->label('Salary')
                                            ->money('INR')
                                            ->icon('heroicon-o-banknotes'),
                                    ]),
                                ]),
                        ])->from('md'),
                    ]),

                // Personal Information Section
                Section::make('Personal Information')
                    ->description('Detailed personal information')
                    ->icon('heroicon-o-identification')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('employeeDetails.date_of_birth')
                                    ->label('Date of Birth')
                                    ->date('M d, Y')
                                    ->icon('heroicon-o-calendar-days'),

                                TextEntry::make('employeeDetails.gender')
                                    ->label('Gender')
                                    ->icon('heroicon-o-users'),

                                TextEntry::make('employeeDetails.marital_status')
                                    ->label('Marital Status')
                                    ->icon('heroicon-o-heart'),
                            ]),
                    ])
                    ->collapsible(),

                // Emergency Contact Section
                Section::make('Emergency Contact')
                    ->description('Person to contact in case of emergency')
                    ->icon('heroicon-o-exclamation-circle')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('employeeDetails.emergency_contact_name')
                                    ->label('Name')
                                    ->icon('heroicon-o-user'),

                                TextEntry::make('employeeDetails.emergency_contact_relationship')
                                    ->label('Relationship')
                                    ->icon('heroicon-o-heart'),

                                TextEntry::make('employeeDetails.emergency_contact_phone')
                                    ->label('Phone')
                                    ->icon('heroicon-o-phone')
                                    ->url(fn ($record) => $record->employeeDetails?->emergency_contact_phone ? "tel:{$record->employeeDetails->emergency_contact_phone}" : null),

                                TextEntry::make('employeeDetails.emergency_contact_email')
                                    ->label('Email')
                                    ->icon('heroicon-o-envelope')
                                    ->url(fn ($record) => $record->employeeDetails?->emergency_contact_email ? "mailto:{$record->employeeDetails->emergency_contact_email}" : null),
                            ]),
                    ])
                    ->collapsible(),

                // Address Information Section
                Section::make('Address Information')
                    ->description('Residential address details')
                    ->icon('heroicon-o-map-pin')
                    ->schema([
                        TextEntry::make('address.street')
                            ->label('Street Address')
                            ->icon('heroicon-o-home'),

                        Grid::make(3)
                            ->schema([
                                TextEntry::make('address.city.name')
                                    ->label('City')
                                    ->icon('heroicon-o-building-office'),

                                TextEntry::make('address.state.name')
                                    ->label('State')
                                    ->icon('heroicon-o-map'),

                                TextEntry::make('address.postal_code')
                                    ->label('Postal Code')
                                    ->icon('heroicon-o-envelope'),
                            ]),

                        TextEntry::make('address.country.name')
                            ->label('Country')
                            ->icon('heroicon-o-globe-alt'),
                    ])
                    ->collapsible(),

                // Employment Details Section
                Section::make('Employment Details')
                    ->description('Job-related information')
                    ->icon('heroicon-o-briefcase')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('employeeDetails.hire_date')
                                    ->label('Hire Date')
                                    ->date('M d, Y')
                                    ->icon('heroicon-o-calendar'),

                                TextEntry::make('employeeDetails.employment_type')
                                    ->label('Employment Type')
                                    ->formatStateUsing(fn ($state) => match ($state) {
                                        'full_time' => 'Full Time',
                                        'part_time' => 'Part Time',
                                        'contract' => 'Contract',
                                        'temporary' => 'Temporary',
                                        default => $state,
                                    })
                                    ->badge()
                                    ->color('success')
                                    ->icon('heroicon-o-clock'),

                                TextEntry::make('employeeDetails.monthly_salary')
                                    ->label('Monthly Salary')
                                    ->money('INR')
                                    ->icon('heroicon-o-banknotes'),

                                // Components\TextEntry::make('employeeDetails.employee_id')
                                //     ->label('Employee ID')
                                //     ->icon('heroicon-o-identification'),
                            ]),
                    ])
                    ->collapsible(),

                // Skills Section
                Section::make('Skills & Competencies')
                    ->description('Receptionist capabilities and expertise')
                    ->icon('heroicon-o-academic-cap')
                    ->schema([
                        TextEntry::make('employeeDetails.skills')
                            ->label('Skills')
                            ->formatStateUsing(fn ($state) => collect($state ?? [])->map(function ($skill) {
                                return match ($skill) {
                                    'customer_service' => 'Customer Service Excellence',
                                    'phone_handling' => 'Phone Handling & Communication',
                                    'appointment_scheduling' => 'Appointment Scheduling',
                                    'payment_processing' => 'Payment Processing',
                                    'membership_management' => 'Membership Management',
                                    'conflict_resolution' => 'Conflict Resolution',
                                    'multilingual' => 'Multilingual Communication',
                                    'computer_skills' => 'Computer & Software Skills',
                                    'cash_handling' => 'Cash Handling',
                                    'first_aid' => 'First Aid Certified',
                                    default => $skill,
                                };
                            })->join(', '))
                            ->icon('heroicon-o-academic-cap')
                            ->bulleted(),
                    ])
                    ->collapsible(),

                // Account Information Section
                Section::make('Account Information')
                    ->description('System access and account details')
                    ->icon('heroicon-o-cog-6-tooth')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                IconEntry::make('is_active')
                                    ->label('Account Active')
                                    ->boolean()
                                    ->trueIcon('heroicon-o-check-circle')
                                    ->falseIcon('heroicon-o-x-circle')
                                    ->trueColor('success')
                                    ->falseColor('danger'),

                                IconEntry::make('email_verified_at')
                                    ->label('Email Verified')
                                    ->boolean()
                                    ->state(fn ($record) => ! is_null($record->email_verified_at))
                                    ->trueIcon('heroicon-o-check-badge')
                                    ->falseIcon('heroicon-o-exclamation-triangle')
                                    ->trueColor('success')
                                    ->falseColor('warning'),
                            ]),

                        TextEntry::make('employeeDetails.notes')
                            ->label('Additional Notes')
                            ->placeholder('No notes available')
                            ->markdown()
                            ->columnSpanFull(),
                    ])
                    ->collapsible(),
            ]);
    }
}
