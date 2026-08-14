<?php

namespace App\Exports\Pdf;

use Illuminate\Http\Request;
use Carbon\Carbon;
use Mpdf\Mpdf;
use App\Models\Setting;

class BorrowingPdfExport
{
    public function download(array $data, Request $request)
    {
        $dateLabel = Carbon::today()->format('d/m/Y');
        $fileLabel = Carbon::today()->format('Ymd');
        
        $companyName = Setting::where('key', 'company_name')->value('value') ?? '';
        $companyNameKh = Setting::where('key', 'company_name_khmer')->value('value') ?? '';
        
        $search = $request->query('search');

        $html = view('exports.borrowing_pdf', [
            'data' => $data,
            'search' => $search,
        ])->render();

        $pdfFont = Setting::where('key', 'pdf_export_font')->value('value') ?? 'khmeros';
        
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
            'default_font' => $pdfFont,
        ]);

        $mpdf->SetTitle('Borrowing Report');
        
        $mpdf->SetHTMLHeader('
            <table width="100%" style="border: none; padding-bottom: 10px;">
                <tr>
                    <td width="100%" style="border: none; text-align: center; vertical-align: middle;">
                        <div style="font-size: 14px; font-weight: bold; color: #000; margin-bottom: 4px;">' . $companyNameKh . '</div>
                        <div style="font-size: 12px; font-weight: bold; color: #000; margin-bottom: 8px;">' . $companyName . '</div>
                        <div style="font-size: 11px; color: #000; margin-bottom: 4px;">Borrowing Report</div>
                        <div style="font-size: 10px; color: #000;">Date: ' . $dateLabel . '</div>
                    </td>
                </tr>
            </table>
        ');

        $mpdf->SetHTMLFooter('
            <table width="100%" style="font-size: 8px; color: #666; border-top: 1px solid #ddd; padding-top: 5px;">
                <tr>
                    <td width="50%" align="left">' . $companyName . '</td>
                    <td width="50%" style="text-align: right;">Page {PAGENO} of {nbpg}</td>
                </tr>
            </table>
        ');

        $mpdf->WriteHTML($html);

        return response($mpdf->Output('', 'S'), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="borrowing_report_' . $fileLabel . '.pdf"',
        ]);
    }
}
