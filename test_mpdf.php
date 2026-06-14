<?php
require_once __DIR__ . '/vendor/autoload.php';

try {
    $mpdf = new \Mpdf\Mpdf(['tempDir' => sys_get_temp_dir()]);
    $mpdf->WriteHTML('<h1>Hello PDF</h1><p>Testing mPDF installation.</p>');

    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="test.pdf"');
    $mpdf->Output('test.pdf', \Mpdf\Output\Destination::INLINE);
    exit;
} catch (Throwable $e) {
    echo '<pre>mPDF error: ' . $e->getMessage() . "\n\n";
    echo $e->getTraceAsString();
    echo '</pre>';
}