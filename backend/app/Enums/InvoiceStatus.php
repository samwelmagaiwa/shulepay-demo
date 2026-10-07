<?php

namespace App\Enums;

enum InvoiceStatus: string
{
    case Paid = 'paid';
    case Partial = 'partial';
    case Unpaid = 'unpaid';

    public function label(): string
    {
        // Follows the request locale (see SetLocale middleware) — lang/*/enums.php.
        return __('enums.invoice_status.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Paid => 'green',
            self::Partial => 'amber',
            self::Unpaid => 'red',
        };
    }
}
