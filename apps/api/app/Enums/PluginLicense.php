<?php

namespace App\Enums;

enum PluginLicense: string
{
    case MIT = 'MIT';
    case Apache20 = 'Apache-2.0';
    case Gpl20 = 'GPL-2.0';
    case Gpl30 = 'GPL-3.0';
    case Bsd3Clause = 'BSD-3-Clause';
    case Proprietary = 'proprietary';
}
