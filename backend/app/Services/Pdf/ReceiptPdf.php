<?php

namespace App\Services\Pdf;

use App\Models\Receipt;
use App\Support\SchoolLetterhead;
use Barryvdh\DomPDF\Facade\Pdf;

class ReceiptPdf
{
    public function generate(Receipt $receipt): string
    {
        return Pdf::loadView('pdf.receipt', $this->viewData($receipt))
            // A4 — the paper actually loaded in the school's printer. An A5 page
            // sent to an A4 tray prints as a small block in one corner.
            ->setPaper('a4')
            ->output();
    }

    /**
     * Variables the `pdf.receipt` view renders with. Split out of generate() so the
     * view can be rendered to HTML on its own — the PDF bytes are compressed and use
     * embedded fonts, so the printed wording can only be asserted on the HTML.
     *
     * @return array{receipt: Receipt, appName: string, appTagline: ?string, logoBase64: ?string, lh: array}
     */
    public function viewData(Receipt $receipt): array
    {
        // Everything the receipt prints, loaded up front so the Blade view never
        // triggers a lazy query (and never silently renders a blank particular).
        $receipt->loadMissing([
            'student.currentEnrollment.schoolClass',
            'student.currentEnrollment.school',
            'student.guardians',
            'payment.invoice.term',
            'payment.invoice.academicYear',
            'payment.invoice.lines',
            'payment.invoice.payments',
            'payment.recorder',
        ]);

        $school = $receipt->student?->school ?? $receipt->student?->currentEnrollment?->school;

        // Single source of truth for the letterhead — see App\Support\SchoolLetterhead.
        $lh = SchoolLetterhead::for($school);
        $appName = $lh['name'];
        $appTagline = $lh['tagline'];
        $logoBase64 = $lh['logo'];

        return compact('receipt', 'appName', 'appTagline', 'logoBase64', 'lh');
    }
}
