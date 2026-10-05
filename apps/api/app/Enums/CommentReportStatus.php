<?php

namespace App\Enums;

enum CommentReportStatus: string
{
    case Pending = 'pending';
    case Resolved = 'resolved';
    case Rejected = 'rejected';
}
