<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class Settings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?string $navigationLabel = 'Podešavanja';

    protected static ?string $title = 'Podešavanja bota';

    protected static string|UnitEnum|null $navigationGroup = 'Majstori';

    protected static ?int $navigationSort = 99;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'about_text' => Setting::get('about_text'),
            'contact_phone' => Setting::get('contact_phone'),
            'contact_email' => Setting::get('contact_email'),
        ]);
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Textarea::make('about_text')
                    ->label('O nama (tekst u botu)')
                    ->rows(5)
                    ->required(),
                TextInput::make('contact_phone')
                    ->label('Kontakt telefon')
                    ->tel()
                    ->maxLength(30),
                TextInput::make('contact_email')
                    ->label('Kontakt email')
                    ->email()
                    ->maxLength(255),
            ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();

        Setting::set('about_text', $data['about_text'] ?? null);
        Setting::set('contact_phone', $data['contact_phone'] ?? null);
        Setting::set('contact_email', $data['contact_email'] ?? null);

        Notification::make()
            ->title('Podešavanja sačuvana')
            ->success()
            ->send();
    }

    /**
     * @return array<Action>
     */
    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Sačuvaj')
                ->submit('save'),
        ];
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([EmbeddedSchema::make('form')])
                    ->id('form')
                    ->livewireSubmitHandler('save')
                    ->footer([
                        Actions::make($this->getFormActions()),
                    ]),
            ]);
    }
}
