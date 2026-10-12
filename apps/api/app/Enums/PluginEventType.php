<?php

namespace App\Enums;

enum PluginEventType: string
{
    case Created = 'created';
    case Resubmitted = 'resubmitted';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case UpdateRequested = 'update_requested';

    /**
     * Human readable label used for logging.
     */
    public function label(): string
    {
        return match ($this) {
            self::Created => 'Plugin submitted',
            self::Resubmitted => 'Changes resubmitted for review',
            self::Approved => 'Plugin approved',
            self::Rejected => 'Plugin rejected',
            self::UpdateRequested => 'Changes requested by admin',
        };
    }
}
