<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\FeeItem;
use App\Models\FeeStructure;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Receipt;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Term;
use App\Models\User;
use App\Services\Pdf\ReceiptPdf;
use App\Services\Pdf\StudentStatementPdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The SPA header switches the UI between English and Swahili and sends the choice
 * as `X-Locale`. Everything the server renders itself — receipts, statements, the
 * clearance certificate, report PDFs, enum labels in JSON — has to follow it.
 *
 * PDF bytes are compressed with embedded fonts, so the wording of each document is
 * asserted on the Blade view rendered to HTML; the download endpoints are hit as well
 * to prove the full DomPDF pipeline still works in both languages.
 */
class LocalizedDocumentsTest extends TestCase
{
    use RefreshDatabase;

    private User $accountant;

    private School $school;

    private Student $student;

    private Invoice $invoice;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'accountant', 'guard_name' => 'web']);

        $this->school = School::create(['name' => 'Test School', 'code' => 'MSG-01', 'slug' => 'test-school', 'level' => 'primary', 'is_active' => true, 'phone' => '0712345678']);
        $class = SchoolClass::create(['school_id' => $this->school->id, 'name' => 'STANDARD ONE', 'sort_order' => 1]);
        $year = AcademicYear::create(['school_id' => $this->school->id, 'name' => '2024', 'start_date' => '2024-01-01', 'end_date' => '2024-12-31', 'is_current' => true]);
        $term = Term::create(['academic_year_id' => $year->id, 'name' => 'FIRST TERM', 'number' => 1, 'start_date' => '2024-01-01', 'end_date' => '2024-04-30', 'is_current' => true]);
        $this->student = Student::create(['school_id' => $this->school->id, 'school_class_id' => $class->id, 'admission_number' => 'M-001', 'first_name' => 'Amina', 'last_name' => 'Juma', 'status' => 'active', 'admission_date' => '2024-01-15']);

        // The letterhead and the class/admission rows come from the student's active enrollment.
        Enrollment::create(['student_id' => $this->student->id, 'school_id' => $this->school->id, 'school_class_id' => $class->id, 'academic_year_id' => $year->id, 'term_id' => $term->id, 'admission_number' => 'M-001', 'status' => 'active', 'admitted_at' => '2024-01-15']);

        $structure = FeeStructure::create(['school_id' => $this->school->id, 'school_class_id' => $class->id, 'academic_year_id' => $year->id, 'term_id' => $term->id, 'name' => 'Test Structure', 'is_active' => true]);
        $feeItem = FeeItem::create(['fee_structure_id' => $structure->id, 'name' => 'Tuition', 'category' => 'masomo', 'amount_cents' => 100000, 'is_optional' => false, 'sort_order' => 0]);

        $this->accountant = User::factory()->create(['school_id' => $this->school->id]);
        $this->accountant->assignRole('accountant');

        $this->invoice = Invoice::create(['school_id' => $this->school->id, 'student_id' => $this->student->id, 'term_id' => $term->id, 'academic_year_id' => $year->id, 'invoice_number' => 'INV-0001', 'total_amount_cents' => 100000, 'discount_cents' => 0, 'arrears_cents' => 0, 'status' => 'unpaid', 'generated_at' => now(), 'generated_by' => $this->accountant->id]);
        InvoiceLine::create(['invoice_id' => $this->invoice->id, 'fee_item_id' => $feeItem->id, 'description' => 'Tuition', 'amount_cents' => 100000]);
    }

    /** Record a 60,000 cash payment in the given language and return the decoded `data`. */
    private function pay(?string $locale): array
    {
        $this->actingAs($this->accountant, 'sanctum');

        $headers = $locale ? ['X-Locale' => $locale] : [];

        return $this->postJson('/api/payments', [
            'invoice_id' => $this->invoice->id,
            'amount_cents' => 60000,
            'method' => 'cash',
        ], $headers)->assertStatus(201)->json('data');
    }

    private function receiptHtml(string $locale, array $data): string
    {
        $receipt = Receipt::findOrFail($data['receipt']['id']);
        app()->setLocale($locale);

        return view('pdf.receipt', app(ReceiptPdf::class)->viewData($receipt))->render();
    }

    // ── Middleware / JSON labels ──────────────────────────────────────────────

    public function test_enum_labels_in_json_follow_the_x_locale_header(): void
    {
        $this->assertSame('Taslimu', $this->pay('sw')['method_label']);
    }

    public function test_english_header_gives_english_labels(): void
    {
        $this->assertSame('Cash', $this->pay('en')['method_label']);
    }

    public function test_region_suffix_and_case_are_tolerated(): void
    {
        $this->assertSame('Taslimu', $this->pay('SW-tz')['method_label']);
    }

    public function test_unsupported_locale_falls_back_to_the_app_default(): void
    {
        $this->assertSame(config('app.locale'), 'en');
        $this->assertSame('Cash', $this->pay('fr')['method_label']);
    }

    public function test_missing_header_uses_the_app_default(): void
    {
        $this->assertSame('Cash', $this->pay(null)['method_label']);
    }

    public function test_lang_query_parameter_works_for_plain_links(): void
    {
        $this->actingAs($this->accountant, 'sanctum');
        $this->postJson('/api/payments?lang=sw', [
            'invoice_id' => $this->invoice->id,
            'amount_cents' => 1000,
            'method' => 'mpesa',
        ])->assertStatus(201);

        $this->assertSame('sw', app()->getLocale());
    }

    // ── Receipt ───────────────────────────────────────────────────────────────

    public function test_receipt_renders_in_english(): void
    {
        $html = $this->receiptHtml('en', $this->pay('en'));

        foreach (['lang="en"', 'PAYMENT RECEIPT', 'RECEIPT NO.', 'Student', 'Admission No.', 'Payment Method', 'Cash',
            'Description', 'AMOUNT PAID', 'Invoice Total', 'BALANCE', 'Thank you for your payment', 'Tel: 0712345678'] as $needle) {
            $this->assertStringContainsString($needle, $html, "English receipt is missing “{$needle}”");
        }

        foreach (['Risiti', 'Mwanafunzi', 'Asante', 'Taslimu', 'Njia ya Malipo', 'Simu:'] as $swahili) {
            $this->assertStringNotContainsString($swahili, $html, "English receipt still contains Swahili “{$swahili}”");
        }
    }

    public function test_receipt_renders_in_swahili(): void
    {
        $html = $this->receiptHtml('sw', $this->pay('sw'));

        foreach (['lang="sw"', 'RISITI YA MALIPO', 'NAMBA YA RISITI', 'Mwanafunzi', 'Namba ya Usajili', 'Njia ya Malipo', 'Taslimu',
            'Maelezo', 'KIASI KILICHOLIPWA', 'Jumla ya Ankara', 'SALIO', 'Asante kwa malipo yako', 'Simu: 0712345678'] as $needle) {
            $this->assertStringContainsString($needle, $html, "Swahili receipt is missing “{$needle}”");
        }

        foreach (['PAYMENT RECEIPT', 'Student', 'Thank you', 'Payment Method', 'Tel:'] as $english) {
            $this->assertStringNotContainsString($english, $html, "Swahili receipt still contains English “{$english}”");
        }
    }

    public function test_fully_paid_receipt_shows_the_settled_banner_in_each_language(): void
    {
        $this->actingAs($this->accountant, 'sanctum');
        $data = $this->postJson('/api/payments', ['invoice_id' => $this->invoice->id, 'amount_cents' => 100000, 'method' => 'cash'])
            ->assertStatus(201)->json('data');

        $this->assertStringContainsString('INVOICE FULLY PAID', $this->receiptHtml('en', $data));
        $this->assertStringContainsString('ANKARA IMELIPWA YOTE', $this->receiptHtml('sw', $data));
    }

    public function test_receipt_download_filename_and_pdf_follow_the_locale(): void
    {
        $data = $this->pay('sw');
        $id = $data['receipt']['id'];
        $number = $data['receipt']['receipt_number'];

        $sw = $this->get("/api/receipts/{$id}/download", ['X-Locale' => 'sw']);
        $sw->assertOk();
        $this->assertSame('application/pdf', $sw->headers->get('Content-Type'));
        $this->assertStringContainsString("filename=\"Risiti-{$number}.pdf\"", $sw->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('%PDF', $sw->getContent());

        $en = $this->get("/api/receipts/{$id}/download", ['X-Locale' => 'en']);
        $en->assertOk();
        $this->assertStringContainsString("filename=\"Receipt-{$number}.pdf\"", $en->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('%PDF', $en->getContent());
    }

    // ── Statement / bulk ──────────────────────────────────────────────────────

    public function test_student_statement_receipt_follows_the_locale(): void
    {
        $this->pay('en');
        $section = app(StudentStatementPdf::class)->buildSection($this->student->fresh());
        $vars = [
            'student' => $section->student, 'enrollment' => $section->enrollment, 'statementNumber' => $section->statementNumber,
            'invoices' => $section->invoices, 'totalInvoiced' => $section->totalInvoiced, 'totalPaid' => $section->totalPaid,
            'totalBalance' => $section->totalBalance, 'appName' => $section->appName, 'appTagline' => $section->appTagline,
            'logoBase64' => $section->lh['logo'], 'lh' => $section->lh,
        ];

        app()->setLocale('en');
        $en = view('pdf.student_statement', $vars)->render();
        foreach (['lang="en"', 'STATEMENT OF ALL PAYMENTS (ALL INVOICES)', 'STATEMENT NO.', 'Total of All Invoices', 'TOTAL BALANCE (ALL DEBTS)', 'Partially paid', 'Cash'] as $needle) {
            $this->assertStringContainsString($needle, $en, "English statement is missing “{$needle}”");
        }
        $this->assertStringNotContainsString('Taarifa', $en);
        $this->assertStringNotContainsString('Hajalipa', $en);

        app()->setLocale('sw');
        // method_label is resolved while the section is built, so rebuild under Swahili
        $sectionSw = app(StudentStatementPdf::class)->buildSection($this->student->fresh());
        $vars['invoices'] = $sectionSw->invoices;
        $sw = view('pdf.student_statement', $vars)->render();
        foreach (['lang="sw"', 'TAARIFA YA MALIPO YOTE (ANKARA ZOTE)', 'TAARIFA NA.', 'Jumla ya Ankara Zote', 'SALIO LA JUMLA (MADENI YOTE)', 'Amelipa Kiasi', 'Taslimu'] as $needle) {
            $this->assertStringContainsString($needle, $sw, "Swahili statement is missing “{$needle}”");
        }
        $this->assertStringNotContainsString('Total of All Invoices', $sw);
    }

    public function test_bulk_invoices_empty_state_and_download_filenames_follow_the_locale(): void
    {
        app()->setLocale('en');
        $en = view('pdf.bulk_invoices', ['sections' => collect(), 'statusFilter' => 'unpaid', 'generatedAt' => now()])->render();
        $this->assertStringContainsString('No students match this filter.', $en);

        app()->setLocale('sw');
        $sw = view('pdf.bulk_invoices', ['sections' => collect(), 'statusFilter' => 'unpaid', 'generatedAt' => now()])->render();
        $this->assertStringContainsString('Hakuna wanafunzi wanaolingana na kigezo hiki.', $sw);

        $this->pay('en'); // leaves a partial invoice for the bulk endpoint to find
        $this->actingAs($this->accountant, 'sanctum');
        $response = $this->get('/api/invoices/bulk-receipt?status=partial', ['X-Locale' => 'en']);
        $response->assertOk();
        $this->assertStringContainsString('filename="Invoices-partial-', $response->headers->get('Content-Disposition'));

        $response = $this->get('/api/invoices/bulk-receipt?status=partial', ['X-Locale' => 'sw']);
        $this->assertStringContainsString('filename="Ankara-partial-', $response->headers->get('Content-Disposition'));
    }

    public function test_bulk_no_match_message_is_localised(): void
    {
        $this->actingAs($this->accountant, 'sanctum');

        $this->get('/api/invoices/bulk-receipt?status=paid', ['X-Locale' => 'en', 'Accept' => 'application/json'])
            ->assertStatus(404)
            ->assertJsonPath('message', 'No students match this filter.');

        $this->get('/api/invoices/bulk-receipt?status=paid', ['X-Locale' => 'sw', 'Accept' => 'application/json'])
            ->assertStatus(404)
            ->assertJsonPath('message', 'Hakuna wanafunzi wanaolingana na kigezo hiki.');
    }

    // ── Clearance certificate (bilingual by design; the UI language leads) ─────

    public function test_clearance_certificate_is_led_by_the_ui_language(): void
    {
        $vars = [
            'student' => $this->student, 'enrollment' => $this->student->currentEnrollment, 'school' => $this->school,
            'academicYear' => AcademicYear::first(), 'issuedAt' => Carbon::parse('2026-03-05 10:30'), 'issuedBy' => $this->accountant, 'logoBase64' => null,
        ];

        app()->setLocale('en');
        $en = view('pdf.clearance', $vars)->render();
        $this->assertStringContainsString('lang="en"', $en);
        $this->assertStringContainsString('Full Name / Jina Kamili', $en);
        $this->assertStringContainsString('Date: 05 March 2026', $en);
        $this->assertStringContainsString('settled all school fees', $en);
        $this->assertStringContainsString('malipo yote ya shule', $en);   // secondary language still present

        app()->setLocale('sw');
        $sw = view('pdf.clearance', $vars)->render();
        $this->assertStringContainsString('lang="sw"', $sw);
        $this->assertStringContainsString('Jina Kamili / Full Name', $sw);
        $this->assertStringContainsString('Tarehe: 05 Machi 2026', $sw);
        $this->assertStringContainsString('Cheti cha Usafi wa Madeni', $sw);
    }

    // ── Report PDFs ───────────────────────────────────────────────────────────

    public function test_report_pdfs_follow_the_locale(): void
    {
        $report = [
            'period' => ['from' => '2026-01-01', 'to' => '2026-01-31'],
            'summary' => ['total_payments' => 1, 'total_amount_cents' => 100, 'invoice_count' => 1, 'paid_count' => 0, 'partial_count' => 1, 'unpaid_count' => 0],
            'rows' => [['period' => '2026-01', 'payment_count' => 1, 'amount_cents' => 100]],
            'by_method' => [['method' => 'cash', 'count' => 1, 'amount_cents' => 100]],
            'by_class' => [['class' => 'STANDARD ONE', 'amount_cents' => 100]],
        ];

        app()->setLocale('en');
        $en = view('pdf.reports.collections', ['report' => $report, 'school' => $this->school])->render();
        $this->assertStringContainsString('Fee Collections Report', $en);
        $this->assertStringContainsString('Collections by Payment Method', $en);
        $this->assertStringContainsString('<td>CASH</td>', $en);

        app()->setLocale('sw');
        $sw = view('pdf.reports.collections', ['report' => $report, 'school' => $this->school])->render();
        $this->assertStringContainsString('Ripoti ya Makusanyo ya Ada', $sw);
        $this->assertStringContainsString('Makusanyo kwa Njia ya Malipo', $sw);
        $this->assertStringContainsString('<td>TASLIMU</td>', $sw);
        $this->assertStringNotContainsString('Fee Collections Report', $sw);
    }

    // ── Translation files stay in step ───────────────────────────────────────

    public function test_english_and_swahili_language_files_define_the_same_keys(): void
    {
        foreach (['pdf', 'enums'] as $file) {
            $en = array_keys(Arr::dot(require lang_path("en/{$file}.php")));
            $sw = array_keys(Arr::dot(require lang_path("sw/{$file}.php")));
            sort($en);
            sort($sw);

            $this->assertSame($en, $sw, "lang/en/{$file}.php and lang/sw/{$file}.php define different keys");
        }
    }
}
