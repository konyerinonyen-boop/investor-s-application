<?php

namespace App\Filament\Resources\EquityRounds;

use App\Filament\Resources\EquityRounds\Pages\ManageEquityRounds;
use App\Filament\Support\AdminResource;
use App\Models\EquityRound;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class EquityRoundResource extends AdminResource
{
    protected static ?string $model = EquityRound::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static string|\UnitEnum|null $navigationGroup = 'Opportunities';

    protected static ?string $permissionResource = 'equity rounds';

   
public static function form(Schema $schema): Schema
{
    return $schema
        ->components([
            Section::make('Round Information')
                ->description('Define the equity offering and the product it belongs to.')
                ->icon('heroicon-o-building-library')
                ->columns(2)
                ->schema([
                    Select::make('product_id')
                        ->label('Investment Product')
                        ->relationship('product', 'name')
                        ->searchable()
                        ->preload()
                        ->required(),

                    TextInput::make('name')
                        ->label('Equity Round Name')
                        ->placeholder('e.g. Series A Funding Round')
                        ->required()
                        ->maxLength(255),

                    Select::make('status')
                        ->options([
                            'draft' => 'Draft',
                            'open' => 'Open',
                            'paused' => 'Paused',
                            'closed' => 'Closed',
                        ])
                        ->default('draft')
                        ->required()
                        ->native(false)
                        ->helperText('Only open rounds within their scheduled dates should accept investments.')
                        ->columnSpanFull(),
                ]),

            Section::make('Valuation & Pricing')
                ->description('Set the company valuation and the price investors pay per equity unit.')
                ->icon('heroicon-o-currency-dollar')
                ->columns(2)
                ->schema([
                    TextInput::make('valuation')
                        ->label('Company Valuation')
                        ->prefix('$')
                        ->numeric()
                        ->minValue(0.01)
                        ->step('0.01')
                        ->required(),

                    TextInput::make('price_per_unit')
                        ->label('Price Per Unit')
                        ->prefix('$')
                        ->numeric()
                        ->minValue(0.000001)
                        ->step('0.000001')
                        ->required()
                        ->helperText('Supports up to 6 decimal places.'),

                    TextInput::make('minimum_ticket')
                        ->label('Minimum Investment')
                        ->prefix('$')
                        ->numeric()
                        ->minValue(0.01)
                        ->step('0.01')
                        ->required(),

                    TextInput::make('maximum_ticket')
                        ->label('Maximum Investment')
                        ->prefix('$')
                        ->numeric()
                        ->minValue(0.01)
                        ->step('0.01')
                        ->helperText('Leave empty if there is no maximum.')
                        ->gte('minimum_ticket'),
                ]),

            Section::make('Equity Allocation')
                ->description('Set the total units offered and the units that remain available for investment.')
                ->icon('heroicon-o-chart-pie')
                ->columns(2)
                ->schema([
                    TextInput::make('total_units')
                        ->label('Total Equity Units')
                        ->numeric()
                        ->minValue(0.000001)
                        ->step('0.000001')
                        ->required()
                        ->helperText('Total units offered in this round.'),

                    TextInput::make('available_units')
                        ->label('Available Equity Units')
                        ->numeric()
                        ->minValue(0)
                        ->step('0.000001')
                        ->required()
                        ->helperText('Units currently available for allocation.'),
                ]),

            Section::make('Offering Schedule')
                ->description('Specify when investors can participate in this round.')
                ->icon('heroicon-o-calendar-days')
                ->columns(2)
                ->schema([
                    DateTimePicker::make('opens_at')
                        ->label('Opening Date & Time')
                        ->seconds(false)
                        ->native(false),

                    DateTimePicker::make('closes_at')
                        ->label('Closing Date & Time')
                        ->seconds(false)
                        ->native(false)
                        ->afterOrEqual('opens_at'),
                ]),
        ]);
}



public static function table(Table $table): Table
{
    return $table
        ->columns([
            TextColumn::make('name')
                ->label('Equity Round')
                ->searchable()
                ->sortable()
                ->description(fn ($record) => $record->product?->name ?? 'No product assigned'),

            TextColumn::make('status')
                ->badge()
                ->formatStateUsing(fn (string $state): string => match ($state) {
                    'draft' => 'Draft',
                    'open' => 'Open',
                    'paused' => 'Paused',
                    'closed' => 'Closed',
                    default => str($state)->replace('_', ' ')->title()->toString(),
                })
                ->color(fn (string $state): string => match ($state) {
                    'open' => 'success',
                    'draft' => 'gray',
                    'paused' => 'warning',
                    'closed' => 'danger',
                    default => 'gray',
                })
                ->sortable(),

            TextColumn::make('valuation')
                ->label('Valuation')
                ->money('USD')
                ->sortable(),

            TextColumn::make('price_per_unit')
                ->label('Price / Unit')
                ->money('USD', decimalPlaces: 6)
                ->sortable(),

            TextColumn::make('total_units')
                ->label('Total Units')
                ->numeric(decimalPlaces: 6)
                ->sortable()
                ->toggleable(),

            TextColumn::make('available_units')
                ->label('Available Units')
                ->numeric(decimalPlaces: 6)
                ->sortable()
                ->color(fn ($state, $record): string =>
                    (float) $state <= 0 ? 'danger' : 'success'
                ),

            TextColumn::make('investments_count')
                ->counts('investments')
                ->label('Investments')
                ->badge()
                ->color('info')
                ->sortable(),

            TextColumn::make('opens_at')
                ->label('Opens')
                ->dateTime('M d, Y H:i')
                ->sortable()
                ->toggleable(),

            TextColumn::make('closes_at')
                ->label('Closes')
                ->dateTime('M d, Y H:i')
                ->description(fn ($record) => $record->closes_at?->diffForHumans())
                ->sortable(),
        ])
        ->filters([
            SelectFilter::make('status')
                ->label('Round Status')
                ->options([
                    'draft' => 'Draft',
                    'open' => 'Open',
                    'paused' => 'Paused',
                    'closed' => 'Closed',
                ]),

            SelectFilter::make('product_id')
                ->label('Investment Product')
                ->relationship('product', 'name'),
        ])
        ->recordActions([
            EditAction::make()
                ->icon('heroicon-o-pencil-square'),

            DeleteAction::make()
                ->requiresConfirmation()
                ->icon('heroicon-o-trash'),
        ])
        ->toolbarActions([
            BulkActionGroup::make([
                DeleteBulkAction::make()
                    ->requiresConfirmation(),
            ]),
        ])
        ->defaultSort('created_at', 'desc')
        ->striped()
        ->emptyStateHeading('No equity rounds yet')
        ->emptyStateDescription(
            'Create an equity round to configure pricing, unit availability, and the investment window.'
        )
        ->emptyStateIcon('heroicon-o-chart-pie');
}


    public static function getPages(): array
    {
        return [
            'index' => ManageEquityRounds::route('/'),
        ];
    }
}
