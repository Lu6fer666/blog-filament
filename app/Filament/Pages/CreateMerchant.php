<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Illuminate\Support\Facades\Http;
use Filament\Notifications\Notification;

class CreateMerchant extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-plus-circle';
    protected static ?string $navigationLabel = 'Créer un Marchand';
    protected static ?string $title = 'Créer un Marchand';
    protected static ?string $slug = 'create-merchant';
    protected static string $view = 'filament.pages.create-merchant';

    public ?array $formData = [];

    public function getForm(string $name): ?Form
    {
        return Form::make($this)
            ->schema($this->getFormSchema())
            ->statePath('formData');
    }

    protected function getFormSchema(): array
    {
        return [
            TextInput::make('mchName')->required()->label('Nom du marchand'),
            TextInput::make('contactName')->required()->label('Nom du contact'),
            TextInput::make('contactEmail')->required()->email()->label('Email du contact'),

            Hidden::make('timeZone')->default('Europe/Brussels'),

            FileUpload::make('receiptLogo')
                ->label('Logo')
                ->image()
                ->acceptedFileTypes(['image/png', 'image/jpeg']),

            TextInput::make('extParams.acqMid')->label('acqMid')->required(),
            TextInput::make('extParams.acqTid')->label('acqTid')->required(),
            TextInput::make('extParams.signKey')->label('signKey')->required(),
            TextInput::make('extParams.subMid')->label('subMid')->required(),
            TextInput::make('terminalCount')
                ->label('Nombre de terminaux à créer')
                ->numeric()
                ->default(1)
                ->minValue(1)
                ->required(),
        ];
    }

    public function submit()
    {
        $data = collect($this->formData)->toArray();
        $data['supportedPaymentMethods'] = ['VISA', 'MASTERCARD'];

        $data['terminalEmvParams'] = [
            'amexExReaderCapability' => '18E00003',
            'amexFloorLimit' => '00000100',
            'amexReaderCapability' => 'C3',
            'emvFlags' => 'VMAUJDP',
            'ifdSN' => '00001234',
            'terminalCapability' => '0068C8',
            'terminalCountryCode' => '0840',
            'terminalType' => '21',
            'txnCurrencyCode' => '0840',
        ];

        $data['extParams'] = [
            'acqMid' => $data['extParams']['acqMid'],
            'acqTid' => $data['extParams']['acqTid'],
            'signKey' => $data['extParams']['signKey'],
            'subMid' => $data['extParams']['subMid'],
        ];

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . config('services.minesec.token'),
        ])->post('https://mtms.mspayhub.com/api/v2/mchInfo', $data);

        if ($response->successful()) {
            Notification::make()
                ->title('Marchand créé avec succès')
                ->body('Réponse API : ' . json_encode($response->json()))
                ->success()
                ->send();

            $mchId = $response->json('data.mchId') ?? null;

            if ($mchId) {
                $terminalCount = (int) ($data['terminalCount'] ?? 1);
                $merchantName = preg_replace('/\s+/', '', $data['mchName']); // Supprime les espaces

                $terminals = [];

                for ($i = 1; $i <= $terminalCount; $i++) {
                    $terminals[] = [
                        'alias' => $merchantName . $i,
                        'deviceAdmin' => '1234',
                    ];
                }

                $data['terminals'] = $terminals;

                foreach ($data['terminals'] ?? [] as $terminal) {
                    $deviceResponse = Http::withHeaders([
                        'Authorization' => 'Bearer ' . config('services.minesec.token'),
                    ])->post('https://mtms.mspayhub.com/api/v2/device', [
                        'mchId' => $mchId,
                        'deviceType' => 'COTS',
                        'activateCodeAlias' => strtoupper($terminal['alias']),
                        'deviceAdmin' => $terminal['deviceAdmin'] ?? '1234',
                        'terminalEmvParams' => $data['terminalEmvParams'],
                    ]);

                    if ($deviceResponse->successful()) {
                        Notification::make()
                            ->title('Terminal créé avec succès')
                            ->body('Alias : ' . $terminal['alias'])
                            ->success()
                            ->send();

                        Notification::make()
                            ->title('Réponse API terminal')
                            ->body(json_encode($deviceResponse->json()))
                            ->info()
                            ->send();
                    } else {
                        Notification::make()
                            ->title('Erreur lors de la création du terminal')
                            ->body('Alias : ' . $terminal['alias'] . ' — Réponse : ' . $deviceResponse->body())
                            ->danger()
                            ->send();
                    }
                }
            }
        } else {
            Notification::make()
                ->title('Erreur API')
                ->body('Réponse API : ' . json_encode($response->json()))
                ->danger()
                ->send();
        }
    }

    protected function getFormActions(): array
    {
        return [
            \Filament\Forms\Components\Actions\Action::make('submit')
                ->label('Créer le marchand')
                ->action('submit')
                ->color('primary'),
        ];
    }
}
