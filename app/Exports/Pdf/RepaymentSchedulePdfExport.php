<?php

namespace App\Exports\Pdf;

use Mpdf\Mpdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use App\Models\Setting;

class RepaymentSchedulePdfExport
{
    public function download(array $data, Request $request)
    {
        $dbSettings = Setting::pluck('value', 'key')->toArray();
        $font = $dbSettings['pdf_export_font'] ?? ($dbSettings['frontend_font_family'] ?? 'noto_sans_khmer');
        
        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4-L',
            'default_font' => $font,
            'margin_top' => 35,
            'margin_bottom' => 15,
            'margin_header' => 10,
            'margin_footer' => 10,
        ]);

        // Define company and report information
        $companyNameKh = 'ប្រាក់.រហ័ស ហ្វាយនែន ម.ក';
        $companyNameEn = 'Quick Fund Finance Plc.';
        $reportTitle = 'Schedule Repay';
        
        $fromDate = $request->query('start_date');
        $toDate = $request->query('end_date');
        $dateStr = '';
        if ($fromDate && $toDate) {
            $dateStr = 'From ' . date('d/m/Y', strtotime($fromDate)) . ' To ' . date('d/m/Y', strtotime($toDate));
        } else {
            $dateStr = 'As At ' . date('d/m/Y');
        }

        $logoPath = public_path('images/logo.jpg');
        $logoHtml = '';
        if (file_exists($logoPath)) {
            $logoHtml = '<img src="' . $logoPath . '" style="height: 60px;">';
        }

        // Header
        $mpdf->SetHTMLHeader('
            <table width="100%" style="border: none; padding-bottom: 10px;">
                <tr>
                    <td width="20%" style="border: none; vertical-align: middle; text-align: left;">
                        ' . $logoHtml . '
                    </td>
                    <td width="60%" style="border: none; text-align: center; vertical-align: top;">
                        <div style="font-size: 16px; font-weight: bold; margin-bottom: 5px;">' . $companyNameKh . '</div>
                        <div style="font-size: 14px; font-weight: bold; margin-bottom: 5px;">' . $companyNameEn . '</div>
                        <div style="font-size: 13px; margin-bottom: 5px;">' . $reportTitle . '</div>
                        <div style="font-size: 12px; color: #333;">' . $dateStr . ', Exchange Rate 4000</div>
                    </td>
                    <td width="20%" style="border: none; vertical-align: top; text-align: right;">
                    </td>
                </tr>
            </table>
        ');

        // Footer
        $mpdf->SetHTMLFooter('
            <table width="100%" style="font-size: 8px; color: #666; border-top: 1px solid #ddd; padding-top: 5px;">
                <tr>
                    <td width="50%" align="left">Quick Fund Finance Plc.</td>
                    <td width="50%" align="right">Page {PAGENO} of {nbpg}</td>
                </tr>
            </table>
        ');

        // Render blade view
        $html = View::make('exports.repayment_schedule_pdf', [
            'data' => (object)$data,
            'font' => $font,
        ])->render();

        $mpdf->WriteHTML($html);
        
        $fileName = "Schedule_Repay_" . date('Ymd_His') . ".pdf";
        
        return response()->streamDownload(function() use ($mpdf) {
            echo $mpdf->Output('', 'S');
        }, $fileName, [
            'Content-Type' => 'application/pdf',
        ]);
    }
}
