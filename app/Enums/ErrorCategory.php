<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ErrorCategory: string implements HasLabel
{
    case FORM_SUBMISSION = 'form_submission';
    case AUTH = 'auth';
    case NAVIGATION = 'navigation';
    case UI = 'ui';
    case ACTION = 'action';
    case DATA = 'data';
    case FILE = 'file';
    case PERFORMANCE = 'performance';
    case NOTIFICATION = 'notification';
    case OTHER = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::FORM_SUBMISSION => 'Form Submission Errors',
            self::AUTH => 'Authentication/Authorization Issues',
            self::NAVIGATION => 'Page Loading or Navigation Errors',
            self::UI => 'UI/Visual Display Issues',
            self::ACTION => 'Action or Button Failures',
            self::DATA => 'Data or Database Errors',
            self::FILE => 'File Upload/Download Issues',
            self::PERFORMANCE => 'Performance or Timeout Issues',
            self::NOTIFICATION => 'Notification or Feedback Issues',
            self::OTHER => 'Other/Unknown Errors',
        };
    }
}
