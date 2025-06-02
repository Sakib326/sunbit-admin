<?php

// filepath: app/Enums/CarRentalEnums.php

namespace App\Enums;

class CarRentalEnums
{
    public const TRANSMISSION_TYPES = [
        'automatic' => 'Automatic',
        'manual' => 'Manual'
    ];

    public const AIR_CONDITION_TYPES = [
        'with_ac' => 'With AC',
        'without_ac' => 'Without AC'
    ];

    public const PAX_CAPACITY = [
        '1-4' => '1-4 Passengers',
        '5-6' => '5-6 Passengers',
        '7-8' => '7-8 Passengers',
        '9-12' => '9-12 Passengers'
    ];

    public const CHAUFFEUR_OPTIONS = [
        'with_chauffeur' => 'With Chauffeur',
        'without_chauffeur' => 'Self Drive'
    ];
}
