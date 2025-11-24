<?php

namespace App\Filament\Admin\Resources\ManagerResource\Pages;

use App\Filament\Admin\Resources\ManagerResource;
use Filament\Actions;
use Filament\Infolists\Components;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewManager extends ViewRecord
{
    protected static string $resource = ManagerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->icon('heroicon-o-pencil-square'),
            Actions\DeleteAction::make()
                ->icon('heroicon-o-trash'),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                // Profile Section with Avatar and Key Details
                Components\Section::make('Manager Profile')
                    ->description('Key information about the manager')
                    ->icon('heroicon-o-user-circle')
                    ->schema([
                        Components\Split::make([
                            Components\ImageEntry::make('profile_photo')
                                ->label('')
                                ->circular()
                                ->grow(false),

                            Components\Grid::make(2)
                                ->schema([
                                    Components\Group::make([
                                        Components\TextEntry::make('name')
                                            ->label('Full Name')
                                            ->weight('bold')
                                            ->size('lg')
                                            ->icon('heroicon-o-user'),

                                        Components\TextEntry::make('email')
                                            ->copyable()
                                            ->icon('heroicon-o-envelope')
                                            ->url(fn ($record) => "mailto:{$record->email}")
                                            ->openUrlInNewTab(),

                                        Components\TextEntry::make('phone')
                                            ->icon('heroicon-o-phone')
                                            ->url(fn ($record) => "tel:{$record->phone}")
                                            ->openUrlInNewTab(),

                                        Components\TextEntry::make('employeeDetails.gender')
                                            ->label('Gender')
                                            ->badge()
                                            ->color('primary')
                                            ->icon('heroicon-o-users'),
                                    ]),

                                    Components\Group::make([
                                        Components\TextEntry::make('gyms.name')
                                            ->label('Assigned Gym')
                                            ->icon('heroicon-o-building-office-2'),

                                        Components\IconEntry::make('is_active')
                                            ->label('Account Status')
                                            ->boolean()
                                            ->trueIcon('heroicon-o-check-circle')
                                            ->falseIcon('heroicon-o-x-circle')
                                            ->trueColor('success')
                                            ->falseColor('danger'),

                                        Components\TextEntry::make('employeeDetails.hire_date')
                                            ->label('Hire Date')
                                            ->date('M d, Y')
                                            ->icon('heroicon-o-calendar'),

                                        Components\TextEntry::make('employeeDetails.monthly_salary')
                                            ->label('Salary')
                                            ->money('INR')
                                            ->icon('heroicon-o-banknotes'),
                                    ]),
                                ]),
                        ])->from('md'),
                    ]),

                // Personal Information Section
                Components\Section::make('Personal Information')
                    ->description('Detailed personal information')
                    ->icon('heroicon-o-identification')
                    ->schema([
                        Components\Grid::make(3)
                            ->schema([
                                Components\TextEntry::make('employeeDetails.date_of_birth')
                                    ->label('Date of Birth')
                                    ->date('M d, Y')
                                    ->icon('heroicon-o-calendar-days'),

                                Components\TextEntry::make('employeeDetails.gender')
                                    ->label('Gender')
                                    ->icon('heroicon-o-users'),

                                Components\TextEntry::make('employeeDetails.marital_status')
                                    ->label('Marital Status')
                                    ->icon('heroicon-o-heart'),
                            ]),
                    ])
                    ->collapsible(),

                // Emergency Contact Section
                Components\Section::make('Emergency Contact')
                    ->description('Person to contact in case of emergency')
                    ->icon('heroicon-o-exclamation-circle')
                    ->schema([
                        Components\Grid::make(2)
                            ->schema([
                                Components\TextEntry::make('employeeDetails.emergency_contact_name')
                                    ->label('Name')
                                    ->icon('heroicon-o-user'),

                                Components\TextEntry::make('employeeDetails.emergency_contact_relationship')
                                    ->label('Relationship')
                                    ->icon('heroicon-o-heart'),

                                Components\TextEntry::make('employeeDetails.emergency_contact_phone')
                                    ->label('Phone')
                                    ->icon('heroicon-o-phone')
                                    ->url(fn ($record) => $record->employeeDetails?->emergency_contact_phone ? "tel:{$record->employeeDetails->emergency_contact_phone}" : null),

                                Components\TextEntry::make('employeeDetails.emergency_contact_email')
                                    ->label('Email')
                                    ->icon('heroicon-o-envelope')
                                    ->url(fn ($record) => $record->employeeDetails?->emergency_contact_email ? "mailto:{$record->employeeDetails->emergency_contact_email}" : null),
                            ]),
                    ])
                    ->collapsible(),

                // Address Information Section
                Components\Section::make('Address Information')
                    ->description('Residential address details')
                    ->icon('heroicon-o-map-pin')
                    ->schema([
                        Components\TextEntry::make('address.street')
                            ->label('Street Address')
                            ->icon('heroicon-o-home'),

                        Components\Grid::make(3)
                            ->schema([
                                Components\TextEntry::make('address.city.name')
                                    ->label('City')
                                    ->icon('heroicon-o-building-office'),

                                Components\TextEntry::make('address.state.name')
                                    ->label('State')
                                    ->icon('heroicon-o-map'),

                                Components\TextEntry::make('address.postal_code')
                                    ->label('Postal Code')
                                    ->icon('heroicon-o-envelope'),
                            ]),

                        Components\TextEntry::make('address.country.name')
                            ->label('Country')
                            ->icon('heroicon-o-globe-alt'),
                    ])
                    ->collapsible(),

                // Employment Details Section
                Components\Section::make('Employment Details')
                    ->description('Job-related information')
                    ->icon('heroicon-o-briefcase')
                    ->schema([
                        Components\Grid::make(3)
                            ->schema([
                                Components\TextEntry::make('employeeDetails.hire_date')
                                    ->label('Hire Date')
                                    ->date('M d, Y')
                                    ->icon('heroicon-o-calendar'),

                                Components\TextEntry::make('employeeDetails.employment_type')
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

                                Components\TextEntry::make('employeeDetails.monthly_salary')
                                    ->label('Monthly Salary')
                                    ->money('INR')
                                    ->icon('heroicon-o-banknotes'),
                            ]),
                    ])
                    ->collapsible(),

                // Skills Section
                Components\Section::make('Skills & Competencies')
                    ->description('Manager capabilities and expertise')
                    ->icon('heroicon-o-academic-cap')
                    ->schema([
                        Components\TextEntry::make('employeeDetails.skills')
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
                Components\Section::make('Account Information')
                    ->description('System access and account details')
                    ->icon('heroicon-o-cog-6-tooth')
                    ->schema([
                        Components\Grid::make(2)
                            ->schema([
                                Components\IconEntry::make('is_active')
                                    ->label('Account Active')
                                    ->boolean()
                                    ->trueIcon('heroicon-o-check-circle')
                                    ->falseIcon('heroicon-o-x-circle')
                                    ->trueColor('success')
                                    ->falseColor('danger'),

                                Components\IconEntry::make('email_verified_at')
                                    ->label('Email Verified')
                                    ->boolean()
                                    ->trueIcon('heroicon-o-check-badge')
                                    ->falseIcon('heroicon-o-exclamation-triangle')
                                    ->trueColor('success')
                                    ->falseColor('warning'),
                            ]),

                        Components\TextEntry::make('employeeDetails.notes')
                            ->label('Additional Notes')
                            ->placeholder('No notes available')
                            ->markdown()
                            ->columnSpanFull(),
                    ])
                    ->collapsible(),
            ]);
    }
}
