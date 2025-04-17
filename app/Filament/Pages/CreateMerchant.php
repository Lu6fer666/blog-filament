<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms;
use Filament\Forms\Form;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Http;

class CreateMerchant extends Page
{
    use InteractsWithForms;
    protected static ?string $navigationIcon = 'heroicon-o-plus-circle';
    protected static ?string $navigationLabel = 'Créer un Marchand';
    protected static ?string $title = 'Créer un Marchand';
    protected static ?string $slug = 'create-merchant';
    protected static string $view = 'filament.pages.create-merchant';

    /**
     * The form state.
     */
    public array $data = [
        'mchName'      => '',
        'contactName'  => '',
        'contactEmail' => '',
        'mcc'          => '',
        'timeZone'     => 'Europe/Brussels',
        'receiptLogo'  => null,
        'extParams'    => [
            'acqMid'  => '',
            'signKey' => '',
            'subMid'  => '',
        ],
        'terminals'    => [
            [],
        ],
    ];

    public function mount(): void
    {
        $this->form->fill($this->data);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema($this->getFormSchema())
            ->statePath('data');
    }

    protected function getFormSchema(): array
    {
        return [
            TextInput::make('mchName')
                ->required()
                ->label('Nom du marchand'),
            TextInput::make('contactName')
                ->required()
                ->label('Nom du contact'),
            TextInput::make('contactEmail')
                ->required()
                ->email()
                ->label('Email du contact'),
            TextInput::make('mcc')
                ->required()
                ->label('MCC'),
            Hidden::make('timeZone')
                ->default('Europe/Brussels'),
            FileUpload::make('receiptLogo')
                ->label('Logo')
                ->image()
                ->acceptedFileTypes(['image/png', 'image/jpeg']),
            TextInput::make('extParams.acqMid')
                ->label('acqMid')
                ->required(),
            TextInput::make('extParams.signKey')
                ->label('signKey')
                ->required(),
            TextInput::make('extParams.subMid')
                ->label('subMid')
                ->required(),

            Repeater::make('terminals')
                ->minItems(1)
                ->label('Terminaux')
                ->schema([
                    TextInput::make('acqTid')
                        ->label('TID Acquéreur')
                        ->required(),
                    Hidden::make('deviceAdmin')
                        ->afterStateHydrated(function ($state, callable $set) {
                            if ($state === null || $state === '') {
                                $set('deviceAdmin', '1234');
                            }
                        }),
                ])];
    }

    public function submit()
    {
        $data = $this->form->getState();
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

        $payload = [
            'mchName'       => $data['mchName'],
            'contactName'   => $data['contactName'],
            'contactEmail'  => $data['contactEmail'],
            'mcc'           => $data['mcc'],
            'timeZone'      => 'Europe/Brussels',
            'logo'          => $data['receiptLogo'] ?? null,
            'profileName'   => 'PROD Live',
            'supportedPaymentMethods' => $data['supportedPaymentMethods'],
            'terminalEmvParams' => $data['terminalEmvParams'],
            'extParams' => [
                'acquirerMerchantId' => $data['extParams']['acqMid'],
                'signKey' => $data['extParams']['signKey'],
                'subMerchantId' => $data['extParams']['subMid'],
            ],
            'activated'  => 'true',
        ];

        // Appel à l'API pour créer le marchand
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . config('services.minesec.token'),
        ])->asJson()->post('https://mtms.mspayhub.com/api/v2/mchInfo', $payload);

        if ($response->successful()) {
            Notification::make()
                ->title('Marchand créé avec succès')
                ->body('Réponse API : ' . json_encode($response->json()))
                ->success()
                ->send();

            $mchId = $response->json('data.mchId') ?? null;
            if ($mchId && isset($data['terminals']) && is_array($data['terminals'])) {
                foreach ($data['terminals'] as $terminalData) {
                    $acqTid = $terminalData['acqTid'] ?? throw new \Exception('Le champ acqTid est requis pour chaque terminal');

                    $terminalPayload = [
                        'mchId'       => $mchId,
                        'deviceType'  => 'COTS',
                        'activateCodeAlias' => strtoupper(substr(str_shuffle('0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, 12)),
                        'deviceAdmin' => $terminalData['deviceAdmin'] ?? '1234',
                        'methodList' => [
                            'all' => [
                                'tid' => $acqTid,
                                'mid' => $data['extParams']['acqMid'],
                            ],
                        ],
                    'extParams' => [
                        'acqMid' => $data['extParams']['acqMid'],
                        'acqTid' => $acqTid,
                        'signKey' => $data['extParams']['signKey'],
                        'subMid' => $data['extParams']['subMid'],
                    ],
                        'terminalEmvParams' => $data['terminalEmvParams'],
                    ];
                    $deviceResponse = Http::withHeaders([
                        'Authorization' => 'Bearer ' . config('services.minesec.token'),
                    ])->post('https://mtms.mspayhub.com/api/v2/device', $terminalPayload);

                    if ($deviceResponse->successful()) {
                        Notification::make()
                            ->title('Terminal créé avec succès')
                            ->body('Alias : ' . $terminalPayload['activateCodeAlias'])
                            ->success()
                            ->send();
                    } else {
                        Notification::make()
                            ->title('Erreur lors de la création du terminal')
                            ->body('Alias : ' . $terminalPayload['activateCodeAlias'] . ' — ' . $deviceResponse->body())
                            ->danger()
                            ->send();
                    }
                }

                $groupId = "G-53325433";

                if ($groupId && $mchId) {
                    foreach (['01', '02'] as $paymentMethod) {
                        $updatePayload = [
                            'groupId' => $groupId,
                            'paymentMethod' => $paymentMethod,
                            'profileId' => 6,
                        ];
                        $updateResponse = Http::withHeaders([
                            'Authorization' => 'Bearer ' . config('services.minesec.token'),
                        ])->timeout(60)->asJson()->post("https://mtms.mspayhub.com/api/v2/mchInfo/payment/{$mchId}", $updatePayload);

                        logger()->info('🔁 Méthode paiement - Status: ' . $updateResponse->status());
                        logger()->info('🔁 Méthode paiement - Headers: ' . json_encode($updateResponse->headers()));
                        logger()->info('🔁 Méthode paiement - Body: ' . $updateResponse->body());

                        if ($updateResponse->successful()) {
                            Notification::make()
                                ->title('Méthode de paiement liée')
                                ->body("Méthode {$paymentMethod} ajoutée au marchand")
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('Erreur update méthode de paiement')
                                ->body("Payload: " . json_encode($updatePayload) . " — Réponse : " . $updateResponse->body())
                                ->danger()
                                ->send();
                        }
                    }
                }
            }
        } else {
            Notification::make()
                ->title('Erreur lors de la création du marchand')
                ->body($response->body())
                ->danger()
                ->send();
        }
    }

}
