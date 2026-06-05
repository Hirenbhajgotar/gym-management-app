<?php

namespace App\Filament\Admin\Resources;

use App\Enums\Currency;
use App\Enums\DefaultRole;
use App\Filament\Actions\ExportAction;
use App\Filament\Actions\ImportAction;
use App\Filament\Admin\Clusters\Permissions\Pages\ManagerPermissionsPage;
use App\Filament\Admin\Resources\ManagerResource\Pages;
use App\Filament\Exports\ManagerExporter;
use App\Imports\ManagersImport;
use App\Models\User;
use Filament\Actions;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;

class ManagerResource extends BaseEmployeeResource
{
    // Only access super_admin, admin and gym_owner
    public static function canAccess(array $parameters = []): bool
    {
        $user = auth()->user();

        return $user?->hasAnyRole([
            DefaultRole::SUPER_ADMIN->value,
            DefaultRole::ADMIN->value,
            DefaultRole::GYM_OWNER->value,
        ]) ?? false;
    }

    public static function getPermissionLabel(): string
    {
        return 'manager';
    }

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

    /**
     * Header actions that are common to all Manager resource pages.
     */
    public static function getCommonHeaderActions(): array
    {
        return [
            Actions\Action::make('managePermissions')
                ->label('Manage Permissions')
                ->color('info')
                ->icon('heroicon-o-key')
                ->outlined()
                ->url(fn (): string => ManagerPermissionsPage::getUrl())
                ->visible(fn (): bool => auth()->user()->hasAnyRole([DefaultRole::SUPER_ADMIN, DefaultRole::ADMIN, DefaultRole::GYM_OWNER]))
                ->authorize(fn (): bool => auth()->user()->hasAnyRole([DefaultRole::SUPER_ADMIN, DefaultRole::ADMIN, DefaultRole::GYM_OWNER])),

            ExportAction::make()
                ->label('Export ' . static::getPluralLabel())
                ->exporter(ManagerExporter::class)
                ->modifyQueryUsing(function (Builder $query) {
                    /** @var Builder<User> $query */
                    $query->role(static::getEmployeeRole());
                }),

            ImportAction::make()
                ->label('Import Managers')
                ->importer(ManagersImport::class)
                ->use(User::class)
                ->modalHeading('Import Managers')
                ->sampleFile(
                    urlOrData: url('import/templates/xls/employee-sample.xlsx'),
                    fileName: 'manager-sample.xlsx',
                    sampleButtonLabel: 'Download Sample File'
                ),

            Actions\CreateAction::make()
                ->icon('heroicon-o-plus-circle')
                ->color('primary'),
        ];
    }
}
