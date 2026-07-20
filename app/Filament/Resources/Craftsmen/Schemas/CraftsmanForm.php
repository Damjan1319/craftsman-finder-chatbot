<?php

namespace App\Filament\Resources\Craftsmen\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CraftsmanForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Osnovno')
                    ->columns(2)
                    ->schema([
                        Select::make('category_id')
                            ->label('Kategorija')
                            ->relationship('category', 'name')
                            ->required()
                            ->searchable()
                            ->preload(),
                        TextInput::make('name')
                            ->label('Ime / Firma')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('phone')
                            ->label('Telefon')
                            ->required()
                            ->tel()
                            ->placeholder('+3816...')
                            ->maxLength(30),
                        TextInput::make('viber_id')
                            ->label('Viber ID')
                            ->placeholder('+3816...')
                            ->maxLength(30)
                            ->helperText('Opciono — za dugme za Viber poruku'),
                        TextInput::make('city')
                            ->label('Grad')
                            ->required()
                            ->maxLength(255),
                        Select::make('status')
                            ->label('Status')
                            ->options([
                                'active' => 'Aktivan',
                                'inactive' => 'Neaktivan',
                                'pending' => 'Na čekanju',
                            ])
                            ->default('pending')
                            ->required(),
                        Textarea::make('bio')
                            ->label('Opis usluga')
                            ->rows(4)
                            ->columnSpanFull(),
                    ]),
                Section::make('Plaćena preporuka')
                    ->description('Preporučeni majstori se prikazuju na vrhu liste sa oznakom „Preporučeno”.')
                    ->columns(2)
                    ->schema([
                        Toggle::make('is_premium')
                            ->label('Preporučeni majstor')
                            ->helperText('Uključite kada majstor plati za istaknutu poziciju')
                            ->live(),
                        TextInput::make('sort_order')
                            ->label('Redosled u listi')
                            ->numeric()
                            ->default(0)
                            ->minValue(0)
                            ->helperText('Manji broj = viša pozicija među preporučenima'),
                        DateTimePicker::make('subscription_expires_at')
                            ->label('Preporuka važi do')
                            ->native(false)
                            ->seconds(false)
                            ->helperText('Posle ovog datuma preporuka se automatski gasi'),
                    ]),
            ]);
    }
}
