<?php

namespace App\Enums;

enum BillStatus: string
{
    case Unpaid = 'unpaid';
    case Paid = 'paid';
}
