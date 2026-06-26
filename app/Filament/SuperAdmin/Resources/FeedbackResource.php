<?php

namespace App\Filament\SuperAdmin\Resources;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\DateTimePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\Split;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\Group;
use Illuminate\Support\HtmlString;
use App\Filament\SuperAdmin\Resources\FeedbackResource\Pages\ListFeedback;
use App\Filament\SuperAdmin\Resources\FeedbackResource\Pages\CreateFeedback;
use App\Filament\SuperAdmin\Resources\FeedbackResource\Pages\EditFeedback;
use App\Filament\SuperAdmin\Resources\FeedbackResource\Pages\ViewFeedback;
use App\Enums\DefaultRole;
use App\Filament\BaseResources\BaseResource;
use App\Models\Feedback;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Form;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Mokhosh\FilamentRating\Columns\RatingColumn;
use Mokhosh\FilamentRating\Components\Rating;
use Mokhosh\FilamentRating\Entries\RatingEntry;

class FeedbackResource extends BaseResource
{
    protected static ?string $model = Feedback::class;

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-ellipsis';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if ($user === null) {
            return false;
        }

        return $user->hasRole(DefaultRole::SUPER_ADMIN->value);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('user_id')
                    ->relationship('user', 'name')
                    ->required(),
                Rating::make('rating')
                    ->required(),
                Textarea::make('feedback')
                    ->required()
                    ->columnSpanFull(),
                DateTimePicker::make('submitted_at')
                    ->native(false)
                    ->closeOnDateSelection(true)
                    ->required(),
                FileUpload::make('files'),
            ]);
    }

    // Or in your resource/controller
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['user.roles']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('feedback')
                    ->label('Feedback')
                    ->limit(50),
                RatingColumn::make('rating')
                    ->sortable(),
                TextColumn::make('submitted_at')
                    ->dateTime()
                    ->sortable(),

            ])
            ->filters([
                //
            ])
            ->actions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make()
                    ->schema([
                        Split::make([
                            Grid::make(2)
                                ->schema([
                                    Group::make([
                                        TextEntry::make('user.name')
                                            ->label('User Name'),
                                        // TextEntry::make('user.roles.first.name')
                                        //     ->label('User Role')
                                        //     ->default('N/A'),

                                        // TextEntry::make('user_roles')
                                        //     ->label('User Roles')
                                        //     ->formatStateUsing(function ($record) {
                                        //         dd($record);
                                        //         return $record->user->getRoleNames()->toArray();
                                        //     })
                                        //     ->badge()
                                        //     ->separator(','),
                                    ]),
                                    Group::make([
                                        RatingEntry::make('rating')
                                            ->label('Rating'),

                                        TextEntry::make('submitted_at')
                                            ->label('Submitted At')
                                            ->badge()
                                            ->date()
                                            ->color('success'),
                                    ]),
                                ]),
                        ])->from('lg'),
                    ]),
                Section::make('Feedback')
                    ->schema([
                        TextEntry::make('feedback')
                            ->prose()
                            ->markdown()
                            ->hiddenLabel(),
                    ])
                    ->collapsible(),
                Section::make('Attachments')
                    ->schema([
                        Group::make([
                            TextEntry::make('files')
                                ->label('Attached Files')
                                ->formatStateUsing(function ($state, $record) {
                                    if (blank($state)) {
                                        return 'No files attached';
                                    }

                                    $filesList = collect($state)->map(function ($file) {
                                        $fileName = basename($file);
                                        $fileExtension = pathinfo($file, PATHINFO_EXTENSION);
                                        $fileUrl = asset('storage/' . $file);

                                        // Check if it's an image
                                        if (in_array(strtolower($fileExtension), ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                                            return "<img src='" . asset('storage/' . $file) . "' alt='{$fileName}' style='max-width: 100px; max-height: 100px; margin: 5px;' />";
                                        } else {
                                            return "<a href='" . asset('storage/' . $file) . "' target='_blank' download='{$fileName}'>{$fileName}</a>";
                                        }

                                        // if (in_array(strtolower($fileExtension), ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg'])) {
                                        //     return "
                                        //         <div class='inline-block m-2'>
                                        //             <img src='{$fileUrl}'
                                        //                  alt='{$fileName}'
                                        //                  class='w-24 h-24 object-cover rounded-lg border border-gray-200 cursor-pointer hover:opacity-80 hover:scale-105 transition-all duration-200 shadow-sm'
                                        //                  onclick='openImageModal(\"{$fileUrl}\", \"{$fileName}\")'
                                        //                  title='Click to view full size - {$fileName}' />
                                        //             <p class='text-xs text-gray-600 mt-1 text-center max-w-24 truncate'>{$fileName}</p>
                                        //         </div>
                                        //     ";
                                        // } else {
                                        //     return "
                                        //         <div class='inline-block m-2'>
                                        //             <a href='{$fileUrl}'
                                        //                target='_blank'
                                        //                download='{$fileName}'
                                        //                class='flex flex-col items-center p-3 border border-gray-200 rounded-lg hover:bg-gray-50 hover:border-gray-300 transition-all duration-200 w-24'>
                                        //                 <svg class='w-8 h-8 text-gray-400 mb-2' fill='currentColor' viewBox='0 0 20 20'>
                                        //                     <path fill-rule='evenodd' d='M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z' clip-rule='evenodd'></path>
                                        //                 </svg>
                                        //                 <span class='text-xs text-gray-600 text-center truncate w-full'>{$fileName}</span>
                                        //             </a>
                                        //         </div>
                                        //     ";
                                        // }
                                    })->join('<br>');

                                    // Add modal HTML and JavaScript
                                    $modalHtml = "
                                        <div id='imageModal' class='fixed inset-0 bg-black bg-opacity-90 flex items-center justify-center z-50 hidden' style='z-index: 9999;'>
                                            <div class='relative max-w-screen-lg max-h-screen p-4 w-full h-full flex items-center justify-center'>
                                                <!-- Close Button -->
                                                <button onclick='closeImageModal()' 
                                                        class='absolute top-4 right-4 text-white bg-black bg-opacity-50 rounded-full p-2 hover:bg-opacity-75 transition-all duration-200 z-10'>
                                                    <svg class='w-6 h-6' fill='none' stroke='currentColor' viewBox='0 0 24 24'>
                                                        <path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M6 18L18 6M6 6l12 12'></path>
                                                    </svg>
                                                </button>
                                                
                                                <!-- Image Container -->
                                                <div class='flex flex-col items-center justify-center max-w-full max-h-full'>
                                                    <img id='modalImage' 
                                                        src='' 
                                                        alt='' 
                                                        class='max-w-full max-h-[80vh] object-contain rounded-lg shadow-2xl' />
                                                    <p id='modalImageName' class='text-white text-center mt-4 text-lg font-medium'></p>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <script>
                                            function openImageModal(src, name) {
                                                const modal = document.getElementById('imageModal');
                                                const modalImage = document.getElementById('modalImage');
                                                const modalImageName = document.getElementById('modalImageName');
                                                
                                                modalImage.src = src;
                                                modalImageName.textContent = name;
                                                modal.classList.remove('hidden');
                                                document.body.style.overflow = 'hidden';
                                                
                                                // Prevent body scroll when modal is open
                                                document.body.style.paddingRight = window.innerWidth - document.documentElement.clientWidth + 'px';
                                            }
                                            
                                            function closeImageModal() {
                                                const modal = document.getElementById('imageModal');
                                                modal.classList.add('hidden');
                                                document.body.style.overflow = 'auto';
                                                document.body.style.paddingRight = '';
                                            }
                                            
                                            // Close modal when clicking outside the image
                                            document.addEventListener('click', function(e) {
                                                const modal = document.getElementById('imageModal');
                                                if (e.target === modal) {
                                                    closeImageModal();
                                                }
                                            });
                                            
                                            // Close modal with Escape key
                                            document.addEventListener('keydown', function(e) {
                                                if (e.key === 'Escape') {
                                                    const modal = document.getElementById('imageModal');
                                                    if (!modal.classList.contains('hidden')) {
                                                        closeImageModal();
                                                    }
                                                }
                                            });
                                            
                                            // Prevent image dragging in modal
                                            document.addEventListener('dragstart', function(e) {
                                                if (e.target.id === 'modalImage') {
                                                    e.preventDefault();
                                                }
                                            });
                                        </script>
                                        ";

                                    return new HtmlString($filesList);
                                })
                                ->html(),
                        ]),
                    ])
                    ->collapsible(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFeedback::route('/'),
            'create' => CreateFeedback::route('/create'),
            'edit' => EditFeedback::route('/{record}/edit'),
            'view' => ViewFeedback::route('/{record}'),
        ];
    }
}
