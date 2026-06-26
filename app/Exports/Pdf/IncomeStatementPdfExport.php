<?php

namespace App\Exports\Pdf;

use Illuminate\Http\Request;
use Carbon\Carbon;
use Mpdf\Mpdf;
use App\Models\Setting;

class IncomeStatementPdfExport
{
    public function download(array $data, Request $request)
    {
        $fileLabel = Carbon::today()->format('Ymd');
        
        $companyName = Setting::where('key', 'company_name')->value('value') ?? 'Quick Fund Finance Plc.';
        $companyNameKh = Setting::where('key', 'company_name_khmer')->value('value') ?? 'ប្រាក់ រហ័ស ហ្វាយនែន ម.ក';
        
        $logoPath = Setting::where('key', 'company_logo')->value('value');
        $logoImg = '';
        if ($logoPath && file_exists(storage_path('app/public/' . $logoPath))) {
            $logoImg = '<img src="' . storage_path('app/public/' . $logoPath) . '" width="50" />';
        } elseif (file_exists(public_path('images/logo.jpg'))) {
            $logoImg = '<img src="' . public_path('images/logo.jpg') . '" width="50" />';
        }

        $html = view('exports.income_statement_pdf', [
            'data' => $data,
        ])->render();

        $pdfFont = Setting::where('key', 'pdf_export_font')->value('value') ?? 'khmeros';
        
        $mpdf = new Mpdf([
            'format' => 'A4-P',
            'margin_left' => 15,
            'margin_right' => 15,
            'margin_top' => 38,
            'margin_bottom' => 18,
            'margin_header' => 5,
            'margin_footer' => 5,
            'autoScriptToLang' => true,
            'autoLangToFont' => true,
            'default_font' => $pdfFont,
        ]);

        $mpdf->SetTitle('Income Statement');
        
        $fromDate = $data['period']['from_date'] ?? '';
        $toDate = $data['period']['to_date'] ?? '';
        $fromFmt = Carbon::parse($fromDate)->format('F d, Y');
        $toFmt = Carbon::parse($toDate)->format('F d, Y');

        // ── HEADER ──
        $mpdf->SetHTMLHeader('
            <table width="100%" style="border-collapse: collapse; border-bottom: 1px solid #000; padding-bottom: 5px;">
                <tr>
                    <td width="15%" style="vertical-align: middle; border: none;">' . $logoImg . '</td>
                    <td width="70%" style="text-align: center; vertical-align: middle; border: none;">
                        <div style="font-size: 13px; font-weight: bold; color: #000;">' . htmlspecialchars($companyName) . '</div>
                        <div style="font-size: 10px; color: #555; margin-top: 1px;">' . htmlspecialchars($companyNameKh) . '</div>
                        <div style="font-size: 11px; font-weight: bold; color: #000; margin-top: 4px; letter-spacing: 1px;">INCOME STATEMENT</div>
                        <div style="font-size: 9px; color: #555; margin-top: 1px;">របាយការណ៍ចំណូល និង ចំណាយ</div>
                        <div style="font-size: 8px; color: #333; margin-top: 3px; font-style: italic;">For the period ' . $fromFmt . ' to ' . $toFmt . '</div>
                    </td>
                    <td width="15%" style="border: none;"></td>
                </tr>
            </table>
        ');

        // ── FOOTER ──
        $mpdf->SetHTMLFooter('
            <table width="100%" style="border-collapse: collapse; border-top: 0.5px solid #999; font-size: 7px; color: #888; padding-top: 3px;">
                <tr>
                    <td width="33%" style="text-align: left; border: none;">Printed: ' . Carbon::now()->format('d/m/Y H:i') . '</td>
                    <td width="34%" style="text-align: center; border: none;">' . htmlspecialchars($companyName) . '</td>
                    <td width="33%" style="text-align: right; border: none;">Page {PAGENO} of {nbpg}</td>
                </tr>
            </table>
        ');

        $mpdf->WriteHTML($html);

        return response($mpdf->Output('', 'S'), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="income_statement_report_' . $fileLabel . '.pdf"',
        ]);
    }
}
