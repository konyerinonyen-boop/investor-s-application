<?php

namespace App\Filament\Resources\Products;

use App\Filament\Resources\Products\Pages\ManageProducts;
use App\Filament\Resources\Products\Pages\ViewProduct;
use App\Filament\Resources\Products\RelationManagers\EquityRoundsRelationManager;
use App\Filament\Resources\Products\RelationManagers\LoanOffersRelationManager;
use App\Filament\Support\AdminResource;
use App\Models\Product;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ProductResource extends AdminResource
{
    protected static ?string $model = Product::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCube;

    protected static string|\UnitEnum|null $navigationGroup = 'Opportunities';

    protected static ?string $navigationLabel = 'Products';

    protected static ?string $modelLabel = 'Product';

    protected static ?string $pluralModelLabel = 'Products';

    protected static ?string $permissionResource = 'products';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Product information')
                    ->description('Identify and describe this investment opportunity.')
                    ->icon('heroicon-o-cube')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('Product name')
                            ->placeholder('e.g. Renewable Energy Investment')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (
                                ?string $state,
                                callable $set,
                                callable $get
                            ): void {
                                if (blank($get('slug'))) {
                                    $set('slug', \Illuminate\Support\Str::slug($state ?? ''));
                                }
                            }),

                        TextInput::make('slug')
                            ->label('URL slug')
                            ->placeholder('renewable-energy-investment')
                            ->helperText('Used to identify this product in URLs.')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),

                        Textarea::make('description')
                            ->label('Product description')
                            ->placeholder('Explain the opportunity, its purpose, and relevant details.')
                            ->rows(4)
                            ->maxLength(5000)
                            ->columnSpanFull(),
                    ]),

                Section::make('Investment configuration')
                    ->description('Choose the investment instrument and control its availability.')
                    ->icon('heroicon-o-adjustments-horizontal')
                    ->columns(2)
                    ->schema([
                        Select::make('instrument_type')
                            ->label('Investment type')
                            ->options([
                            'equity' => 'Investment only',
                            'loan' => 'Loan only',
                            'both' => 'Investment and loan',
                        ])
                            ->native(false)
                            ->required()
                            ->live()
                            ->helperText('Determines whether this product represents an equity or loan opportunity.'),

                        Select::make('status')
                            ->label('Product status')
                            ->options([
                                'draft' => 'Draft — not published',
                                'active' => 'Active — available',
                                'paused' => 'Paused — temporarily unavailable',
                                'closed' => 'Closed — no longer accepting investments',
                            ])
                            ->native(false)
                            ->required()
                            ->default('draft')
                            ->helperText('Only make a product active when its investment terms and related records are ready.'),

                        DateTimePicker::make('launch_date')
                            ->label('Launch date')
                            ->native(false)
                            ->seconds(false)
                            ->displayFormat('d M Y, h:i A')
                            ->helperText('When this opportunity is scheduled to launch.'),
                    ]),

                Section::make('Investment limits')
                    ->description('Set the minimum and maximum amount an investor may commit.')
                    ->icon('heroicon-o-banknotes')
                    ->columns(2)
                    ->schema([
                        TextInput::make('minimum_investment')
                            ->label('Minimum investment')
                            ->prefix('₦')
                            ->placeholder('10000.00')
                            ->numeric()
                            ->minValue(0)
                            ->required()
                            ->helperText('The smallest permitted investment amount.'),

                        TextInput::make('maximum_investment')
                            ->label('Maximum investment')
                            ->prefix('₦')
                            ->placeholder('Leave empty for no limit')
                            ->numeric()
                            ->minValue(0)
                            ->helperText('Optional. Must not be lower than the minimum investment.')
                            ->rules([
                                function (callable $get) {
                                    return function (
                                        string $attribute,
                                        $value,
                                        \Closure $fail
                                    ) use ($get): void {
                                        if (
                                            $value !== null &&
                                            $value !== '' &&
                                            is_numeric($get('minimum_investment')) &&
                                            (float) $value < (float) $get('minimum_investment')
                                        ) {
                                            $fail('The maximum investment must be greater than or equal to the minimum investment.');
                                        }
                                    };
                                },
                            ]),

                        TextInput::make('interest_rate')
                            ->label('Interest rate')
                            ->suffix('%')
                            ->placeholder('e.g. 12.50')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->visible(fn (callable $get): bool => $get('instrument_type') === 'loan')
                            ->required(fn (callable $get): bool => $get('instrument_type') === 'loan')
                            ->helperText('Applicable to loan products. Confirm your actual interest-rate rules before publishing.'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(
                fn ($query) => $query
                    ->withCount(['equityRounds', 'loanOffers'])
                    ->latest()
            )
            ->columns([
                TextColumn::make('name')
                    ->label('Product')
                    ->searchable()
                    ->sortable()
                    ->description(fn (Product $record): string => $record->slug)
                    ->weight('bold')
                    ->wrap(),

                TextColumn::make('instrument_type')
                    ->label('Instrument')
                    ->badge()
                    ->formatStateUsing(
                        fn (?string $state): string => match ($state) {
                            'equity' => 'Equity',
                            'loan' => 'Loan',
                            default => str($state ?? 'unknown')->title()->toString(),
                        }
                    )
                    ->color(fn (?string $state): string => match ($state) {
                        'equity' => 'info',
                        'loan' => 'warning',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('instrument_type')
                ->label('Product type')
                ->badge()
                ->formatStateUsing(
                    fn (?string $state): string => match ($state) {
                        'equity' => 'Investment only',
                        'loan' => 'Loan only',
                        'both' => 'Investment and loan',
                        default => str($state ?? 'unknown')
                            ->title()
                            ->toString(),
                    }
                )->color(fn (?string $state): string => match ($state) {
                        'active' => 'success',
                        'draft' => 'gray',
                        'paused' => 'warning',
                        'closed' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('minimum_investment')
                    ->label('Minimum')
                    ->money('NGN')
                    ->sortable()
                    ->alignEnd(),

                TextColumn::make('maximum_investment')
                    ->label('Maximum')
                    ->money('NGN')
                    ->placeholder('No limit')
                    ->sortable()
                    ->alignEnd(),

                TextColumn::make('interest_rate')
                    ->label('Interest')
                    ->formatStateUsing(
                        fn ($state): string => $state === null
                            ? '—'
                            : number_format((float) $state, 2) . '%'
                    )
                    ->visible(fn (): bool => true)
                    ->toggleable(),

   TextColumn::make('loan_offers_count')
    ->label('Loan offers')
    ->counts('loanOffers')
    ->badge()
    ->color('warning')
    ->sortable()
    ->formatStateUsing(
        fn ($state, $record) => in_array(
            $record->instrument_type,
            ['loan', 'both'],
            true
        ) ? $state : '—'
    ),

TextColumn::make('equity_rounds_count')
    ->label('Equity rounds')
    ->counts('equityRounds')
    ->badge()
    ->color('success')
    ->sortable()
    ->formatStateUsing(
        fn ($state, $record) => in_array(
            $record->instrument_type,
            ['equity', 'both'],
            true
        ) ? $state : '—'
    ),
                TextColumn::make('loan_offers_count')
                    ->label('Loan offers')
                    ->counts('loanOffers')
                    ->badge()
                    ->color('warning')
                    ->sortable(),

                TextColumn::make('launch_date')
                    ->label('Launch date')
                    ->dateTime('d M Y')
                    ->placeholder('Not scheduled')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('instrument_type')
                    ->label('Investment type')
                    ->options([
                        'equity' => 'Equity',
                        'loan' => 'Loan',
                    ]),

                SelectFilter::make('status')
                    ->label('Product status')
                    ->options([
                        'draft' => 'Draft',
                        'active' => 'Active',
                        'paused' => 'Paused',
                        'closed' => 'Closed',
                    ]),
            ])
            
            ->recordActions([
                ViewAction::make()
                    ->url(fn (Product $record): string => static::getUrl(
                        'view',
                        ['record' => $record]
                    )),

                EditAction::make(),

                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->striped();
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageProducts::route('/'),
            'view' => ViewProduct::route('/{record}'),
        ];
    }

    public static function getRelations(): array
    {
        return [
            EquityRoundsRelationManager::class,
            LoanOffersRelationManager::class,
        ];
    }
}
