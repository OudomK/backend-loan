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
        $dateLabel = Carbon::today()->format('d/m/Y');
        $fileLabel = Carbon::today()->format('Ymd');
        
        $companyName = Setting::where('key', 'company_name')->value('value') ?? 'Quick Fund Finance Plc.';
        $companyNameKh = Setting::where('key', 'company_name_khmer')->value('value') ?? 'ប្រាក់ រហ័ស ហ្វាយនែន ម.ក';
        
        $logoPath = Setting::where('key', 'company_logo')->value('value');
        $logoImg = '';
        if ($logoPath && file_exists(storage_path('app/public/' . $logoPath))) {
            $logoImg = '<img src="' . storage_path('app/public/' . $logoPath) . '" width="60" style="max-height: 60px;" />';
        } elseif (file_exists(public_path('images/logo.jpg'))) {
            $logoImg = '<img src="' . public_path('images/logo.jpg') . '" width="60" style="max-height: 60px;" />';
        }

        $html = view('exports.income_statement_pdf', [
            'data' => $data,
        ])->render();

        $pdfFont = Setting::where('key', 'pdf_export_font')->value('value') ?? 'khmeros';
        
        $mpdf = new Mpdf([
            'format' => 'A4-P', // Portrait is usually better for income statement, but can adjust if needed
            'margin_left' => 15,
            'margin_right' => 15,
            'margin_top' => 45,
            'margin_bottom' => 20,
            'margin_header' => 5,
            'margin_footer' => 5,
            'autoScriptToLang' => true,
            'autoLangToFont' => true,
            'default_font' => $pdfFont,
        ]);

        $mpdf->SetTitle('Income Statement');
        
        $fromDate = $data['period']['from_date'] ?? '';
        $toDate = $data['period']['to_date'] ?? '';
        $periodLabel = "For the period " . \Carbon\Carbon::parse($fromDate)->format('F d, Y') . " to " . \Carbon\Carbon::parse($toDate)->format('F d, Y');

        $mpdf->SetHTMLHeader('
            <table width="100%" style="border: none; padding-bottom: 10px; border-bottom: 2px solid #BBDEFB;">
                <tr>
                    <td width="20%" style="border: none; vertical-align: middle; text-align: left;">
                        ' . $logoImg . '
                    </td>
                    <td width="60%" style="border: none; text-align: center; vertical-align: middle;">
                        <div style="font-size: 16px; font-weight: bold; color: #1A237E; margin-bottom: 4px;">' . $companyName . '</div>
                        <div style="font-size: 14px; font-weight: bold; color: #000; letter-spacing: 1px; margin-bottom: 4px;">INCOME STATEMENT</div>
                        <div style="font-size: 11px; color: #666; font-weight: bold; margin-bottom: 6px;">របាយការណ៍ចំណូល និង ចំណាយ</div>
                        <div style="font-size: 10px; color: #1A237E; font-style: italic; background-color: #E3F2FD; padding: 3px 10px; display: inline-block; border-radius: 3px;">' . $periodLabel . '</div>
                    </td>
                    <td width="20%" style="border: none;"></td>
                </tr>
            </table>
        ');

        $mpdf->SetHTMLFooter('
            <table width="100%" style="font-size: 8px; color: #666; border-top: 1px solid #E0E0E0; padding-top: 5px;">
                <tr>
                    <td width="50%" align="left">Printed on: ' . Carbon::now()->format('d M Y, H:i') . '</td>
                    <td width="50%" style="text-align: right;">Income Statement Report - Page {PAGENO} of {nbpg}</td>
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
