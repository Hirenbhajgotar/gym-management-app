<?php

namespace App\Filament\Admin\Resources;

use App\Enums\Currency;
use App\Enums\DefaultRole;
use App\Filament\Admin\Resources\ManagerResource\Pages;
use Filament\Tables;

class ManagerResource extends BaseEmployeeResource
{
    public static function getLabel(): string
    {
        return 'Manager';
    }

    public static function getPluralLabel(): string
    {
        return 'Managers';
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListManagers::route('/'),
            'create' => Pages\CreateManager::route('/create'),
            'edit' => Pages\EditManager::route('/{record}/edit'),
            'view' => Pages\ViewManager::route('/{record}'),
        ];
    }

    protected static function getEmployeeRole(): string
    {
        return DefaultRole::MANAGER->value;
    }

    protected static function getSalaryPrefix(): string
    {
        return Currency::INR->getSymbol();
    }

    protected static function getSkillsOptions(): array
    {
        return [
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
        ];
    }

    protected static function getTableActions(): array
    {
        return [
            Tables\Actions\ViewAction::make(),
            Tables\Actions\EditAction::make(),
            Tables\Actions\DeleteAction::make(),
        ];
    }
}
