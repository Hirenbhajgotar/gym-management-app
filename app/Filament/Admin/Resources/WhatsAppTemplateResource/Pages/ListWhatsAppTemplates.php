<?php

namespace App\Filament\Admin\Resources\WhatsAppTemplateResource\Pages;

use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use App\Enums\WhatsAppTemplateStatusEnum;
use App\Filament\Admin\Resources\WhatsAppTemplateResource;
use App\Models\WhatsAppTemplate;
use Filament\Facades\Filament;
use Filament\Forms\Components\CheckboxList;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Cache;

class ListWhatsAppTemplates extends ListRecords
{
    protected static string $resource = WhatsAppTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Import from Global Defaults
            Action::make('importDefault')
                ->label('Import Default Templates')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Import Default Templates')
                ->modalDescription('Select the templates you want to import. Existing templates with the same name will be skipped.')
                ->form([
                    CheckboxList::make('template_names')
                        ->label('Select Templates')
                        ->options(
                            fn () => WhatsAppTemplate::getDefaults()
                                ->mapWithKeys(fn ($template) => [$template->name => $template->display_name ?? $template->name])
                                ->toArray()
                        )
                        ->columns(2)
                        ->required(),
                ])
                ->action(fn (array $data) => $this->importDefaultTemplatesAction($data)),

            CreateAction::make()
                ->label('New Template')
                ->icon('heroicon-o-plus'),
        ];
    }

    private function importDefaultTemplatesAction(array $data): void
    {
        $tenant = Filament::getTenant();
        if (! $tenant) {
            Notification::make()
                ->danger()
                ->title('Error')
                ->body('No gym selected.')
                ->send();

            return;
        }

        $selectedNames = $data['template_names'] ?? [];

        if (blank($selectedNames)) {
            Notification::make()
                ->info()
                ->title('No Selection')
                ->body('Please select at least one template to import.')
                ->send();

            return;
        }

        $globalDefaults = WhatsAppTemplate::getDefaults();

        $existingNames = WhatsAppTemplate::where('gym_id', $tenant->id)
            ->pluck('name')
            ->toArray();

        $templatesToImport = $globalDefaults->filter(
            fn ($template) => in_array($template->name, $selectedNames)
                && ! in_array($template->name, $existingNames)
        );

        $importedCount = $templatesToImport->count();
        $skippedCount = count($selectedNames) - $importedCount;

        if ($templatesToImport->isNotEmpty()) {
            $importData = $templatesToImport->map(fn ($template) => [
                'gym_id' => $tenant->id,
                'name' => $template->name,
                'display_name' => $template->display_name,
                'body' => $template->body,
                'category' => $template->category,
                'variables' => json_encode($template->variables),
                'variable_count' => $template->variable_count,
                'variable_placeholder' => json_encode($template->variable_placeholder),
                'status' => WhatsAppTemplateStatusEnum::DRAFT,
                'is_default' => false,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ])->all();

            WhatsAppTemplate::insert($importData);

            Cache::forget(WhatsAppTemplate::NAVIGATION_BADGE_CACHE_KEY . $tenant->id);
        }

        if ($importedCount > 0) {
            Notification::make()
                ->success()
                ->title('Templates Imported!')
                ->body("Imported {$importedCount} template(s). Skipped {$skippedCount} existing template(s).")
                ->send();
        } else {
            Notification::make()
                ->info()
                ->title('No New Templates')
                ->body("Selected template(s) already exist ({$skippedCount} template(s)).")
                ->send();
        }
    }
}
