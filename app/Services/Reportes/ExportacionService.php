<?php

declare(strict_types=1);

namespace App\Services\Reportes;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Response;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportacionService
{
    /**
     * Exporta datos tabulares a archivo CSV descargable mediante streaming rápido.
     *
     * @param  array<int, string>  $encabezados
     * @param  array<int, array<string, mixed>>  $filas
     */
    public function exportarCsv(string $nombreArchivo, array $encabezados, array $filas): StreamedResponse
    {
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$nombreArchivo}.csv\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        return Response::stream(function () use ($encabezados, $filas) {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            // BOM para compatibilidad con Microsoft Excel en UTF-8
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($handle, $encabezados);

            foreach ($filas as $fila) {
                // Aplanar valores para CSV
                $filaPlana = array_map(function ($val) {
                    if (is_array($val)) {
                        return json_encode($val, JSON_UNESCAPED_UNICODE);
                    }

                    return (string) $val;
                }, array_values($fila));

                fputcsv($handle, $filaPlana);
            }

            fclose($handle);
        }, 200, $headers);
    }

    /**
     * Exporta datos tabulares a archivo Excel (.xlsx) usando OpenSpout en disco temporal y lo retorna para descarga.
     *
     * @param  array<int, string>  $encabezados
     * @param  array<int, array<string, mixed>>  $filas
     */
    public function exportarExcel(string $nombreArchivo, array $encabezados, array $filas): BinaryFileResponse
    {
        $rutaTemporal = tempnam(sys_get_temp_dir(), 'rep_').'.xlsx';

        $writer = new XlsxWriter;
        $writer->openToFile($rutaTemporal);

        // Encabezados
        $writer->addRow(Row::fromValues($encabezados));

        // Filas de datos
        foreach ($filas as $fila) {
            $valores = array_map(function ($val) {
                if (is_array($val)) {
                    return json_encode($val, JSON_UNESCAPED_UNICODE);
                }

                return $val;
            }, array_values($fila));

            $writer->addRow(Row::fromValues($valores));
        }

        $writer->close();

        return response()->download($rutaTemporal, "{$nombreArchivo}.xlsx")->deleteFileAfterSend(true);
    }

    /**
     * Genera un PDF a partir de una vista Blade y lo retorna como string binario o respuesta de descarga.
     *
     * @param  array<string, mixed>  $datos
     */
    public function generarPdf(string $vistaBlade, array $datos): \Barryvdh\DomPDF\PDF
    {
        /** @var \Barryvdh\DomPDF\PDF $pdf */
        $pdf = Pdf::loadView($vistaBlade, $datos);
        $pdf->setPaper('a4', 'portrait');

        return $pdf;
    }
}
