<?php

namespace App\Filament\Resources\ViberUsers\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ViberUsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('viber_id')
                    ->label('Viber ID')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->label('Ime')
                    ->searchable()
                    ->sortable(),
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
