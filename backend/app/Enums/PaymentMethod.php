<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Cash = 'cash';
    case Mpesa = 'mpesa';
    case Bank = 'bank';
    case Cheque = 'cheque';
    case Sponsor = 'sponsor';

    public function label(): string
    {
        // Follows the request locale (see SetLocale middleware) — lang/*/enums.php.
        return __('enums.payment_method.'.$this->value);
    }
}
