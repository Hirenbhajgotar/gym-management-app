<?php

namespace App\Filament\SuperAdmin\Resources;

use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Set;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\DateTimePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use App\Filament\SuperAdmin\Resources\BlogResource\Pages\ListBlogs;
use App\Filament\SuperAdmin\Resources\BlogResource\Pages\CreateBlog;
use App\Filament\SuperAdmin\Resources\BlogResource\Pages\EditBlog;
use App\Enums\BlogCategoryEnum;
use App\Enums\DefaultRole;
use App\Filament\BaseResources\BaseResource;
use App\Models\Blog;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class BlogResource extends BaseResource
{
    protected static ?string $model = Blog::class;

    protected static ?string $navigationGroup = 'Content Management';

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationLabel = 'Blog Posts';

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
                Section::make('Blog Details')
                    ->schema([
                        TextInput::make('title')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (string $operation, $state, Set $set) => $operation === 'create' ? $set('slug', Str::slug($state)) : null),

                        TextInput::make('slug')
                            ->required()
                            ->maxLength(255)
                            ->unique(Blog::class, 'slug', ignoreRecord: true),

                        Textarea::make('excerpt')
                            ->maxLength(500)
                            ->columnSpanFull(),

                        RichEditor::make('content')
                            ->toolbarButtons([
                                'attachFiles',
                                'blockquote',
                                'bold',
                                'bulletList',
                                'codeBlock',
                                'h1',
                                'h2',
                                'h3',
                                'italic',
                                'link',
                                'orderedList',
                                'redo',
                                'strike',
                                'underline',
                                'undo',
                            ])
                            ->columnSpanFull(),

                        Select::make('category')
                            ->required()
                            ->options(BlogCategoryEnum::class),

                        TextInput::make('author')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('author_role')
                            ->maxLength(255),

                        TextInput::make('read_time')
                            ->maxLength(50)
                            ->helperText('e.g., "5 min read"'),

                        FileUpload::make('image_url')
                            ->label('Image')
                            ->disk('public')
                            ->directory('blogs')
                            ->visibility('public')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make('Publishing')
                    ->schema([
                        Toggle::make('is_featured')
                            ->label('Featured')
                            ->default(false),

                        Toggle::make('is_published')
                            ->label('Published')
                            ->default(false),

                        DateTimePicker::make('published_at')
                            ->label('Publish Date')
                            ->default(now())
                            ->nullable(),
                    ])
                    ->columns(2),

                Section::make('SEO')
                    ->schema([
                        TextInput::make('meta_title')
                            ->label('Meta Title')
                            ->maxLength(255),

                        Textarea::make('meta_description')
                            ->label('Meta Description')
                            ->maxLength(500)
                            ->rows(3),

                        Textarea::make('meta_keywords')
                            ->label('Meta Keywords')
                            ->rows(2)
                            ->helperText('Comma-separated keywords for SEO.'),

                        TextInput::make('og_title')
                            ->label('Open Graph Title')
                            ->maxLength(255)
                            ->helperText('Title for social media sharing. Defaults to meta_title or blog title if empty.'),

                        Textarea::make('og_description')
                            ->label('Open Graph Description')
                            ->maxLength(500)
                            ->rows(3)
                            ->helperText('Description for social media sharing. Defaults to meta_description or excerpt if empty.'),

                        TextInput::make('og_image')
                            ->label('Open Graph Image URL')
                            ->maxLength(500)
                            ->url()
                            ->helperText('Image for social media sharing. Defaults to featured image if empty.'),
                    ])
                    ->collapsible()
                    ->collapsed(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->searchable()
                    ->limit(50),
                TextColumn::make('slug')
                    ->searchable()
                    ->limit(30),
                TextColumn::make('category')
                    ->searchable(),
                TextColumn::make('author')
                    ->searchable(),
                ToggleColumn::make('is_featured')
                    ->label('Featured'),
                ToggleColumn::make('is_published')
                    ->label('Published'),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('category')
                    ->options(BlogCategoryEnum::class),
                TernaryFilter::make('is_published'),
                TernaryFilter::make('is_featured'),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
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
            'index' => ListBlogs::route('/'),
            'create' => CreateBlog::route('/create'),
            'edit' => EditBlog::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return Cache::flexible(Blog::NAVIGATION_BADGE_CACHE_KEY, [21600, 604800], function () {
            return (string) static::getModel()::where('is_published', true)->count();
        }) ?: null;
    }
}
