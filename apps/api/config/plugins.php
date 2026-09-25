<?php

use App\Enums\PluginLicense;

return [
    'licenses' => [
        PluginLicense::MIT->value,
        PluginLicense::Apache20->value,
        PluginLicense::Gpl20->value,
        PluginLicense::Gpl30->value,
        PluginLicense::Bsd3Clause->value,
        PluginLicense::Proprietary->value,
    ],
    'submit_per_minute' => 5,
];
