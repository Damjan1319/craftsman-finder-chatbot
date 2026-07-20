<?php

namespace App\Filament\Resources\Craftsmen\Tables;

use App\Models\Craftsman;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CraftsmenTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Majstor')
                    ->searchable(['name', 'city', 'category.name'])
                    ->sortable()
                    ->weight(FontWeight::Bold)
                    ->description(fn (Craftsman $record): string => $record->category->name.' · '.$record->city)
                    ->tooltip(fn (Craftsman $record): ?string => filled($record->bio) ? $record->bio : null)
                    ->wrap(),
                TextColumn::make('phone')
                    ->label('Telefon')
                    ->copyable()
                    ->copyMessage('Broj kopiran')
                    ->icon(Heroicon::OutlinedPhone)
                    ->searchable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(function (Craftsman $record): string {
                        $status = match ($record->status) {
                            'active' => 'Aktivan',
                            'inactive' => 'Neaktivan',
                            'pending' => 'Na čekanju',
                            default => $record->status,
                        };

                        if ($record->is_premium) {
                            $status .= ' · Preporučeno';
                        }

                        return $status;
                    })
                    ->color(fn (Craftsman $record): string => match (true) {
                        $record->is_premium && $record->isSubscriptionExpired() => 'danger',
                        $record->is_premium => 'warning',
                        $record->status === 'active' => 'success',
                        $record->status === 'pending' => 'warning',
                        default => 'danger',
                    })
                    ->description(fn (Craftsman $record): ?string => $record->is_premium && $record->subscription_expires_at
                        ? 'Važi do '.$record->subscription_expires_at->format('d.m.Y')
                        : null)
                    ->sortable(query: function ($query, string $direction): void {
                        $query
                            ->orderBy('is_premium', $direction)
                            ->orderBy('status', $direction)
                            ->orderBy('subscription_expires_at', $direction);
                    }),
            ])
            ->defaultSort('is_premium', 'desc')
            ->modifyQueryUsing(fn ($query) => $query
                ->with('category')
                ->orderByDesc('is_premium')
                ->orderBy('sort_order')
                ->orderBy('name'))
            ->striped()
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(25)
            ->recordClasses(fn (Craftsman $record): ?string => $record->is_premium
                ? 'fi-ta-row-premium !bg-amber-50 dark:!bg-amber-950/20'
                : null)
            ->filters([
                SelectFilter::make('category_id')
                    ->label('Kategorija')
                    ->relationship('category', 'name'),
                SelectFilter::make('city')
                    ->label('Grad')
                    ->options(fn () => Craftsman::query()->distinct()->orderBy('city')->pluck('city', 'city')->all()),
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'active' => 'Aktivan',
                        'inactive' => 'Neaktivan',
                        'pending' => 'Na čekanju',
                    ]),
                SelectFilter::make('is_premium')
                    ->label('Preporuka')
                    ->options([
                        '1' => 'Preporučeni (plaćeni)',
                        '0' => 'Standardni',
                    ]),
            ])
            ->recordActionsColumnLabel('')
            ->recordActions([
                ActionGroup::make([
                    Action::make('recommend')
                        ->label('Uključi preporuku')
                        ->icon(Heroicon::OutlinedStar)
                        ->color('warning')
                        ->form([
                            Select::make('months')
                                ->label('Trajanje preporuke')
                                ->options([
                                    1 => '1 mesec',
                                    3 => '3 meseca',
                                    6 => '6 meseci',
                                    12 => '12 meseci',
                                ])
                                ->default(1)
                                ->required(),
                        ])
                        ->action(function (Craftsman $record, array $data): void {
                            $record->activateRecommendation((int) $data['months']);
                        })
                        ->visible(fn (Craftsman $record): bool => ! $record->is_premium),
                    Action::make('remove_recommendation')
                        ->label('Ukloni preporuku')
                        ->icon(Heroicon::OutlinedXMark)
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('Ukloni plaćenu preporuku?')
                        ->modalDescription('Majstor više neće biti istaknut na vrhu liste.')
                        ->action(fn (Craftsman $record) => $record->deactivateRecommendation())
                        ->visible(fn (Craftsman $record): bool => $record->is_premium),
                    EditAction::make(),
                ])
                    ->icon(Heroicon::OutlinedEllipsisVertical)
                    ->iconButton()
                    ->tooltip('Akcije'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
