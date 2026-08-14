<?php

namespace App\Exports\Pdf;

use Illuminate\Http\Request;
use Carbon\Carbon;
use Mpdf\Mpdf;

class LoanOutstandingParPdfExport
{
    public function download(array $data, Request $request, ?string $reportDateStr)
    {
        $refDate = $reportDateStr ? Carbon::parse($reportDateStr)->endOfDay() : Carbon::today()->endOfDay();
        $dateLabel = $refDate->format('d/m/Y');
        $fileLabel = $refDate->format('Ymd');
        
        $exchangeRate = \App\Models\Setting::where('key', 'exchange_rate_khr_to_usd')->value('value')
            ?? \App\Models\Setting::where('key', 'exchange_rate')->value('value')
            ?? 4000;
            
        $companyName = \App\Models\Setting::where('key', 'company_name')->value('value') ?? 'Company Name';
        $companyNameKh = \App\Models\Setting::where('key', 'company_name_khmer')->value('value') ?? 'ឈ្មោះក្រុមហ៊ុន';
        
        $html = view('exports.loan_outstanding_par_pdf', [
            'data' => $data,
            'dateLabel' => $dateLabel,
            'exchangeRate' => $exchangeRate,
        ])->render();

        $mpdf = new Mpdf([
            'format' => 'A4-L',
            'margin_left' => 10,
            'margin_right' => 10,
            'margin_top' => 35,
            'margin_bottom' => 15,
            'margin_header' => 5,
            'margin_footer' => 5,
            'autoScriptToLang' => true,
            'autoLangToFont' => true,
            'default_font' => 'khmeros',
        ]);

        $mpdf->SetTitle('Loan Outstanding and PAR Report');
        
        $mpdf->SetHTMLHeader('
            <table width="100%" style="border: none; padding-bottom: 10px;">
                <tr>
                    <td width="100%" style="border: none; text-align: center; vertical-align: middle;">
                        <div style="font-size: 14px; font-weight: bold; color: #000; margin-bottom: 4px;">' . $companyNameKh . '</div>
                        <div style="font-size: 12px; font-weight: bold; color: #000; margin-bottom: 8px;">' . $companyName . '</div>
                        <div style="font-size: 11px; color: #000; margin-bottom: 4px;">Loan Outstanding and PAR Report</div>
                        <div style="font-size: 10px; color: #000;">As At ' . $dateLabel . ', Exchange Rate 4000</div>
                    </td>
                </tr>
            </table>
        ');

        // Footer
        $mpdf->SetHTMLFooter('
            <table width="100%" style="font-size: 8px; color: #666; border-top: 1px solid #ddd; padding-top: 5px;">
                <tr>
                    <td width="50%" align="left"></td>
                    <td width="50%" style="text-align: right;">Page {PAGENO} of {nbpg}</td>
                </tr>
            </table>
        ');

        $mpdf->WriteHTML($html);

        return response($mpdf->Output('', 'S'), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="loan_outstanding_par_' . $fileLabel . '.pdf"',
        ]);
    }
}
