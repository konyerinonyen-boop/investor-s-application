<?php

namespace App\Filament\Resources\RepaymentPayouts\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class RepaymentPayoutForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('repayment_schedule_id')
                    ->relationship('repaymentSchedule', 'id')
                    ->required(),
                Select::make('investor_id')
                    ->relationship('investor', 'id')
                    ->default(null),
                TextInput::make('amount')
                    ->required()
                    ->numeric(),
                TextInput::make('currency')
                    ->required()
                    ->default('NGN'),
                TextInput::make('approval_status')
                    ->required()
                    ->default('pending_review'),
                TextInput::make('reviewed_by')
                    ->numeric()
                    ->default(null),
                DateTimePicker::make('reviewed_at'),
                Textarea::make('review_note')
                    ->default(null)
                    ->columnSpanFull(),
                TextInput::make('recipient_code')
                    ->default(null),
                TextInput::make('transfer_reference')
                    ->default(null),
                TextInput::make('transfer_status')
                    ->required()
                    ->default('not_started'),
                Textarea::make('provider_response')
                    ->default(null)
                    ->columnSpanFull(),
                DateTimePicker::make('transferred_at'),
            ]);
    }
}
