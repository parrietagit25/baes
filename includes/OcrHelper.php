<?php
/**
 * Helper para extraer texto de documentos (imágenes con OCR, PDF, TXT).
 * Requiere en el servidor:
 * - Tesseract OCR (imágenes): apt install tesseract-ocr tesseract-ocr-spa
 * - Poppler (PDF con texto): apt install poppler-utils  -> pdftotext
 * - Para PDF escaneados (solo imágenes): pdftoppm + tesseract (opcional)
 */
class OcrHelper
{
    /** Idiomas para Tesseract (español + inglés) */
    const TESSERACT_LANG = 'spa+eng';

    /**
     * Extrae todo el texto de un archivo según su tipo MIME.
     * @param string $rutaArchivoAbsoluta Ruta absoluta al archivo en disco
     * @param string $tipoMime Tipo MIME (ej. image/jpeg, application/pdf)
     * @return string Texto extraído o cadena vacía si no aplica o falla
     */
    public static function extraerTexto($rutaArchivoAbsoluta, $tipoMime)
    {
        if (!is_file($rutaArchivoAbsoluta)) {
            return '';
        }
        $tipoMime = strtolower(trim($tipoMime));
        if (strpos($tipoMime, 'image/') === 0) {
            return self::ocrImagen($rutaArchivoAbsoluta);
        }
        if ($tipoMime === 'application/pdf') {
            return self::extraerTextoPdf($rutaArchivoAbsoluta);
        }
        if ($tipoMime === 'text/plain') {
            return self::extraerTextoTxt($rutaArchivoAbsoluta);
        }
        return '';
    }

    /**
     * OCR sobre una imagen (JPEG, PNG, GIF) usando Tesseract.
     */
    public static function ocrImagen($rutaAbsoluta)
    {
        $rutaAbsoluta = realpath($rutaAbsoluta);
        if (!$rutaAbsoluta || !is_file($rutaAbsoluta)) {
            return '';
        }
        $salida = $rutaAbsoluta . '_ocr_' . uniqid() . '.txt';
        $comando = sprintf(
            'tesseract %s %s -l %s 2>/dev/null',
            escapeshellarg($rutaAbsoluta),
            escapeshellarg(pathinfo($salida, PATHINFO_DIRNAME) . '/' . pathinfo($salida, PATHINFO_FILENAME)),
            escapeshellarg(self::TESSERACT_LANG)
        );
        @exec($comando);
        $texto = '';
        if (is_file($salida . '.txt')) {
            $texto = @file_get_contents($salida . '.txt');
            @unlink($salida . '.txt');
        }
        return $texto !== false ? trim($texto) : '';
    }

    /**
     * Extrae texto de un PDF: primero intenta pdftotext (texto embebido);
     * si no hay texto, intenta PDF escaneado con pdftoppm + tesseract si está disponible.
     */
    public static function extraerTextoPdf($rutaAbsoluta)
    {
        $rutaAbsoluta = realpath($rutaAbsoluta);
        if (!$rutaAbsoluta || !is_file($rutaAbsoluta)) {
            return '';
        }
        $texto = self::pdftotext($rutaAbsoluta);
        if ($texto !== '' && strlen(trim(preg_replace('/\s+/', ' ', $texto))) > 20) {
            return trim($texto);
        }
        return self::ocrPdfEscaneado($rutaAbsoluta);
    }

    /**
     * Usa pdftotext (poppler-utils) para PDF con texto seleccionable.
     */
    public static function pdftotext($rutaAbsoluta)
    {
        $comando = sprintf(
            'pdftotext -layout -enc UTF-8 -q %s - 2>/dev/null',
            escapeshellarg($rutaAbsoluta)
        );
        $out = @shell_exec($comando);
        return $out !== null ? trim($out) : '';
    }

    /**
     * Convierte PDF a imágenes (pdftoppm) y aplica Tesseract a cada página.
     * Requiere: poppler-utils (pdftoppm) y tesseract-ocr.
     */
    public static function ocrPdfEscaneado($rutaAbsoluta)
    {
        $dir = dirname($rutaAbsoluta);
        $prefijo = pathinfo($rutaAbsoluta, PATHINFO_FILENAME) . '_pg';
        $rutaImagenes = $dir . '/' . $prefijo;
        $comando = sprintf(
            'pdftoppm -png -r 150 %s %s 2>/dev/null',
            escapeshellarg($rutaAbsoluta),
            escapeshellarg($rutaImagenes)
        );
        @exec($comando);
        $textos = [];
        $k = 1;
        while (true) {
            $img = $rutaImagenes . '-' . $k . '.png';
            if (!is_file($img)) {
                $imgAlt = $rutaImagenes . '-' . sprintf('%03d', $k) . '.png';
                if (!is_file($imgAlt)) break;
                $img = $imgAlt;
            }
            $t = self::ocrImagen($img);
            if ($t !== '') {
                $textos[] = $t;
            }
            @unlink($img);
            $k++;
        }
        return implode("\n\n", $textos);
    }

    /**
     * Lee contenido de un archivo de texto plano.
     */
    public static function extraerTextoTxt($rutaAbsoluta)
    {
        $rutaAbsoluta = realpath($rutaAbsoluta);
        if (!$rutaAbsoluta || !is_file($rutaAbsoluta)) {
            return '';
        }
        $content = @file_get_contents($rutaAbsoluta);
        if ($content === false) {
            return '';
        }
        if (!mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'ISO-8859-1');
        }
        return trim($content);
    }
}
