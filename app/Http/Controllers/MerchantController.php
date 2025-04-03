<?php

namespace App\Filament\Pages;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Grid;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Http;

class CreateMerchant extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-plus-circle';
    protected static string $view = 'filament.pages.create-merchant';

    public $formData = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    protected function getFormSchema(): array
    {
        return [
            TextInput::make('mchName')->required()->label('Nom du marchand'),
            TextInput::make('providerReference')->label('Référence fournisseur'),
            TextInput::make('profileId')->required()->numeric()->label('ID du profil acquirer'),
            TextInput::make('contactName')->required(),
            TextInput::make('contactEmail')->required()->email(),
            TextInput::make('contactAddress')->required(),
            TextInput::make('contactPhone')->required(),
            TextInput::make('mcc')->label('MCC'),
            TextInput::make('timeZone')->default('Africa/Douala'),
            TextInput::make('receiptLogo')->label('URL du logo'),
            TextInput::make('supportedPaymentMethods')
                ->label('Méthodes de paiement (séparées par virgule)')
                ->default('VISA,MASTERCARD'),

            Grid::make()
                ->schema([
                    TextInput::make('extParams.acqMid')->label('acqMid'),
                    TextInput::make('extParams.acqTid')->label('acqTid'),
                ]),
            Grid::make()
                ->schema([
                    TextInput::make('terminalEmvParams.amexFloorLimit'),
                    TextInput::make('terminalEmvParams.terminalCountryCode'),
                    TextInput::make('terminalEmvParams.txnCurrencyCode'),
                    TextInput::make('terminalEmvParams.amexExReaderCapability'),
                    TextInput::make('terminalEmvParams.amexReaderCapability'),
                    TextInput::make('terminalEmvParams.terminalType'),
                    TextInput::make('terminalEmvParams.terminalCapability'),
                    TextInput::make('terminalEmvParams.ifdSN'),
                    TextInput::make('terminalEmvParams.emvFlags'),
                ]),
        ];
    }

    public function submit()
    {
        $data = $this->form->getState();
        $data['supportedPaymentMethods'] = array_map('trim', explode(',', $data['supportedPaymentMethods'] ?? ''));

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . config('services.minesec.token'),
        ])->post('https://uat-mtms.mspayhub.com/api/v2/mchInfo', $data);

        if ($response->successful()) {
            Notification::make()
                ->title('Marchand créé avec succès')
                ->success()
                ->send();
        } else {
            Notification::make()
                ->title('Erreur')
                ->body($response->json('msg') ?? 'Une erreur est survenue.')
                ->danger()
                ->send();
        }
    }

    protected function getFormActions(): array
    {
        return [
            \Filament\Forms\Components\Actions\Action::make('Créer le marchand')
                ->submit('submit')
        ];
    }
}
