<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\ReceptionistResource\Pages\ListReceptionists;
use App\Filament\Admin\Resources\ReceptionistResource\Pages\CreateReceptionist;
use App\Filament\Admin\Resources\ReceptionistResource\Pages\EditReceptionist;
use App\Filament\Admin\Resources\ReceptionistResource\Pages\ViewReceptionist;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use App\Enums\Currency;
use App\Enums\DefaultRole;
use App\Filament\Actions\ExportAction;
use App\Filament\Actions\ImportAction;
use App\Filament\Admin\Clusters\Permissions\Pages\ReceptionistPermissionsPage;
use App\Filament\Exports\ReceptionistExporter;
use App\Imports\ReceptionistsImport;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class ReceptionistResource extends BaseEmployeeResource
{
    public static function getPermissionLabel(): string
    {
        return 'receptionist';
    }

    public static function getLabel(): string
    {
        return 'Receptionist';
    }

    public static function getPluralLabel(): string
    {
        return 'Receptionists';
    }

    public static function getPages(): array
    {
        return [
            'index' => ListReceptionists::route('/'),
            'create' => CreateReceptionist::route('/create'),
            'edit' => EditReceptionist::route('/{record}/edit'),
            'view' => ViewReceptionist::route('/{record}'),
        ];
    }

    protected static function getEmployeeRole(): string
    {
        return DefaultRole::RECEPTIONIST->value;
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
            ViewAction::make(),
            EditAction::make(),
            DeleteAction::make(),
        ];
    }

    /**
     * Header actions that are common to all Receptionist resource pages.
     */
    public static function getCommonHeaderActions(): array
    {
        return [
            Action::make('managePermissions')
                ->label('Manage Permissions')
                ->color('info')
                ->icon('heroicon-o-key')
                ->outlined()
                ->url(fn (): string => ReceptionistPermissionsPage::getUrl())
                ->visible(fn (): bool => auth()->user()->hasAnyRole([DefaultRole::SUPER_ADMIN, DefaultRole::ADMIN, DefaultRole::GYM_OWNER]))
                ->authorize(fn (): bool => auth()->user()->hasAnyRole([DefaultRole::SUPER_ADMIN, DefaultRole::ADMIN, DefaultRole::GYM_OWNER])),

            ExportAction::make()
                ->label('Export ' . static::getPluralLabel())
                ->exporter(ReceptionistExporter::class)
                ->modifyQueryUsing(fn (Builder $query) => $query->role(static::getEmployeeRole()))
                ->visible(fn (): bool => auth()->user()->can('export_' . static::getPermissionLabel()))
                ->authorize(fn (): bool => auth()->user()->can('export_' . static::getPermissionLabel())),

            ImportAction::make()
                ->label('Import Receptionists')
                ->importer(ReceptionistsImport::class)
                ->use(User::class)
                ->modalHeading('Import Receptionists')
                ->sampleFile(
                    urlOrData: url('import/templates/xls/employee-sample.xlsx'),
                    fileName: 'receptionist-sample.xlsx',
                    sampleButtonLabel: 'Download Sample File'
                )
                ->visible(fn (): bool => auth()->user()->can('import_' . static::getPermissionLabel()))
                ->authorize(fn (): bool => auth()->user()->can('import_' . static::getPermissionLabel())),

            CreateAction::make()
                ->icon('heroicon-o-plus-circle')
                ->color('primary'),
        ];
    }
}
