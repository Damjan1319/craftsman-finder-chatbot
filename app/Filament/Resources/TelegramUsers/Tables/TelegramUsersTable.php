<?php

namespace App\Filament\Resources\TelegramUsers\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TelegramUsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('telegram_id')
                    ->label('Telegram ID')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('first_name')
                    ->label('Ime')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('last_name')
                    ->label('Prezime')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('username')
                    ->label('Username')
                    ->formatStateUsing(fn (?string $state): string => filled($state) ? '@'.$state : '—')
                    ->searchable(),
                TextColumn::make('source')
                    ->label('Izvor')
                    ->formatStateUsing(fn (?string $state): string => filled($state) ? $state : 'organicki')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('last_interaction')
                    ->label('Poslednja aktivnost')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Prvi kontakt')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('last_interaction', 'desc')
            ->filters([])
            ->recordActions([])
            ->toolbarActions([]);
    }
}
