<?php

namespace App\Filament\Admin\Resources;

use App\Enums\Currency;
use App\Enums\DefaultRole;
use App\Filament\Actions\Imports\ExcelImportAction;
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
            // Permissions action
            Actions\Action::make('managePermissions')
                ->label('Manage Permissions')
                ->color('info')
                ->icon('heroicon-o-key')
                ->outlined()
                ->url(fn (): string => ManagerPermissionsPage::getUrl()),

            // Export action
            Actions\ExportAction::make()
                ->label('Export ' . static::getPluralLabel())
                ->icon('heroicon-o-arrow-up-tray')
                ->exporter(ManagerExporter::class)
                ->modifyQueryUsing(fn (Builder $query) => $query->role(static::getEmployeeRole())),

            // Import action
            ExcelImportAction::make()
                ->label('Import Managers')
                ->importer(ManagersImport::class)
                ->use(User::class)
                ->modalHeading('Import Managers')
                ->sampleFile(
                    url: url('import/templates/xls/employee-sample.xlsx'),
                    fileName: 'manager-sample.xlsx',
                    sampleButtonLabel: 'Download Sample File'
                ),

            // Create action
            Actions\CreateAction::make(),
        ];
    }
}
