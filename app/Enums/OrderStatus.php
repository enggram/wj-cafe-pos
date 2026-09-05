<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Active = 'active';
    case Billed = 'billed';
    case Completed = 'completed';
}
