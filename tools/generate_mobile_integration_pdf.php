<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

$root = dirname(__DIR__);
$source = $root.'/docs/mobile_app_api_integration_handoff.html';
$outputDir = $root.'/output/pdf';
$tempDir = $root.'/tmp/pdfs/mpdf';

if (! is_dir($outputDir)) {
    mkdir($outputDir, 0775, true);
}
if (! is_dir($tempDir)) {
    mkdir($tempDir, 0775, true);
}

$mpdf = new Mpdf\Mpdf([
    'mode' => 'utf-8',
    'format' => 'A4',
    'default_font' => 'dejavusans',
    'margin_left' => 14,
    'margin_right' => 14,
    'margin_top' => 15,
    'margin_bottom' => 15,
    'tempDir' => $tempDir,
    'autoScriptToLang' => true,
    'autoLangToFont' => true,
]);

$mpdf->SetDirectionality('rtl');
$mpdf->SetTitle('دليل تكامل تطبيق العميل والتاجر');
$mpdf->SetAuthor('Laravel Mobile API Integration');
$mpdf->SetHTMLFooter(
    '<div style="text-align:center;color:#607087;font-size:8pt;border-top:1px solid #d7e4f0;padding-top:5px;">{PAGENO} / {nbpg}</div>'
);
$mpdf->WriteHTML((string) file_get_contents($source));
$mpdf->Output($outputDir.'/mobile_app_api_integration_handoff.pdf', Mpdf\Output\Destination::FILE);

echo "PDF generated successfully.\n";
