<?php

namespace App\Support;

class PluginNameHelper
{
    /**
     * The shape a plugin name may take: ASCII letters, digits, and `.`, `-`, `_`.
     *
     * A bare `.` or `..` is rejected because it is a special path segment.
     */
    public const PATTERN = '/^(?!\.{1,2}$)[A-Za-z0-9._-]+$/';
}
