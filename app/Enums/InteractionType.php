<?php

namespace App\Enums;

enum InteractionType: string
{
    case CALL = 'call';
    case EMAIL = 'email';
    case TOUR = 'tour';
    case MEETING = 'meeting';
}
