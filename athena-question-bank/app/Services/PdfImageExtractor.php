<?php

namespace App\Services;

use RuntimeException;

/**
 * Detecta y extrae imágenes incrustadas de un PDF (sin dependencias externas).
 *
 * - Lee objetos directos y flujos ObjStm.
 * - Mapea cada imagen a su página de origen mediante /Resources /XObject.
 * - Conserva el JPEG original (DCTDecode) sin recomprimir.
 * - Convierte flujos Flate 8 bpc (gris o RGB) a PNG sin pérdida.
 * - Lo que no puede decodificarse con seguridad se reporta como "unsupported".
 */
class PdfImageExtractor
{
    /** Imágenes menores a esto se consideran fragmentos decorativos/de maquetación. */
    public const MIN_WIDTH = 150;
    public const MIN_HEIGHT = 100;

    /** @var array<int,string> */
    protected array $objs = [];

    /**
     * @return array{pages:int, images:array<int,array<string,mixed>>}
     */
    public function extract(string $pdfBytes): array
    {
        $this->loadObjects($pdfBytes);
        $pageOrder = $this->orderedPageObjects();

        $images = [];
        $seen = [];

        foreach ($pageOrder as $idx => $pageObj) {
            $pageNumber = $idx + 1;
            foreach ($this->pageImageObjects($pageObj) as $imgObj) {
                if (isset($seen[$imgObj])) {
                    continue; // recurso compartido: se atribuye a su primera página
                }
                $body = $this->objs[$imgObj];
                [$w, $h] = $this->dimensions($body);
                if ($w < self::MIN_WIDTH || $h < self::MIN_HEIGHT) {
                    continue;
                }
                $seen[$imgObj] = true;

                $entry = [
                    'page'   => $pageNumber,
                    'object' => $imgObj,
                    'width'  => $w,
                    'height' => $h,
                ];

                $decoded = $this->decodeImage($body, $w, $h);
                if ($decoded === null) {
                    $entry['status'] = 'unsupported';
                } else {
                    $entry['status'] = 'ok';
                    $entry['mime'] = $decoded['mime'];
                    $entry['ext'] = $decoded['ext'];
                    $entry['bytes'] = $decoded['bytes'];
                }
                $images[] = $entry;
            }
        }

        return ['pages' => count($pageOrder), 'images' => $images];
    }

    protected function loadObjects(string $c): void
    {
        $this->objs = [];
        preg_match_all('#(?<!\d)(\d+)\s+0\s+obj(.*?)endobj#s', $c, $m, PREG_SET_ORDER);
        foreach ($m as $x) {
            $this->objs[(int) $x[1]] = $x[2];
        }

        foreach ($this->objs as $body) {
            if (! preg_match('#/Type\s*/ObjStm#', $body)) {
                continue;
            }
            preg_match('#/N\s+(\d+)#', $body, $nM);
            preg_match('#/First\s+(\d+)#', $body, $fM);
            $raw = $this->streamData($body);
            if (! isset($nM[1], $fM[1]) || $raw === null) {
                continue;
            }
            $data = @gzuncompress($raw);
            if ($data === false) {
                $data = @gzinflate(substr($raw, 2));
            }
            if ($data === false) {
                continue;
            }
            $n = (int) $nM[1];
            $first = (int) $fM[1];
            $hdr = preg_split('#\s+#', trim(substr($data, 0, $first)));
            for ($i = 0; $i < $n; $i++) {
                $on = (int) $hdr[$i * 2];
                $off = (int) $hdr[$i * 2 + 1];
                $end = ($i + 1 < $n) ? (int) $hdr[($i + 1) * 2 + 1] : strlen($data) - $first;
                $this->objs[$on] = substr($data, $first + $off, $end - $off);
            }
        }

        if (empty($this->objs)) {
            throw new RuntimeException('No se pudo interpretar la estructura del PDF.');
        }
    }

    /** @return int[] números de objeto de página en orden de lectura */
    protected function orderedPageObjects(): array
    {
        $order = [];
        foreach ($this->objs as $b) {
            if (preg_match('#/Type\s*/Pages#', $b) && preg_match('#/Kids\s*\[(.*?)\]#s', $b, $k)) {
                preg_match_all('#(\d+)\s+0\s+R#', $k[1], $r);
                foreach ($r[1] as $kid) {
                    $order[] = (int) $kid;
                }
            }
        }

        return array_values(array_filter(
            $order,
            fn ($o) => isset($this->objs[$o]) && preg_match('#/Type\s*/Page(?!s)#', $this->objs[$o])
        ));
    }

    /** @return int[] */
    protected function pageImageObjects(int $pageObj): array
    {
        $b = $this->objs[$pageObj];
        $res = $b;
        if (preg_match('#/Resources\s+(\d+)\s+0\s+R#', $b, $rr)) {
            $res = $this->objs[(int) $rr[1]] ?? '';
        }

        $xo = '';
        if (preg_match('#/XObject\s*(\d+)\s+0\s+R#', $res, $xr)) {
            $xo = $this->objs[(int) $xr[1]] ?? '';
        } elseif (preg_match('#/XObject\s*<<(.*?)>>#s', $res, $xr)) {
            $xo = $xr[1];
        }

        preg_match_all('#/[A-Za-z0-9_.]+\s+(\d+)\s+0\s+R#', $xo, $im);
        $out = [];
        foreach ($im[1] as $o) {
            $o = (int) $o;
            if (isset($this->objs[$o]) && preg_match('#/Subtype\s*/Image#', $this->objs[$o])) {
                $out[] = $o;
            }
        }

        return $out;
    }

    /** Extrae los bytes del flujo sin usar regex (evita el límite de backtracking en binarios grandes). */
    protected function streamData(string $body): ?string
    {
        $start = strpos($body, 'stream');
        $end = strrpos($body, 'endstream');
        if ($start === false || $end === false || $end <= $start) {
            return null;
        }
        $start += 6;
        if (($body[$start] ?? '') === "\r") {
            $start++;
        }
        if (($body[$start] ?? '') === "\n") {
            $start++;
        }
        $data = substr($body, $start, $end - $start);

        return rtrim($data, "\r\n") === $data ? $data : (str_ends_with($data, "\r\n") ? substr($data, 0, -2) : substr($data, 0, -1));
    }

    /** @return array{0:int,1:int} */
    protected function dimensions(string $b): array
    {
        preg_match('#/Width\s+(\d+)#', $b, $w);
        preg_match('#/Height\s+(\d+)#', $b, $h);

        return [(int) ($w[1] ?? 0), (int) ($h[1] ?? 0)];
    }

    /** @return array{mime:string,ext:string,bytes:string}|null */
    protected function decodeImage(string $body, int $w, int $h): ?array
    {
        $stream = $this->streamData($body);
        if ($stream === null) {
            return null;
        }
        preg_match('#/Filter\s*\[?\s*(/[A-Za-z0-9]+)#', $body, $fM);
        $filter = $fM[1] ?? '';

        if ($filter === '/DCTDecode') {
            return ['mime' => 'image/jpeg', 'ext' => 'jpg', 'bytes' => $stream]; // original, sin recomprimir
        }

        if ($filter === '/FlateDecode' && ! preg_match('#/Predictor\s+(?:[2-9]|1\d)#', $body)) {
            $raw = @gzuncompress($stream);
            if ($raw === false) {
                return null;
            }
            $len = strlen($raw);
            $bpp = match (true) {
                $len === $w * $h => 1,
                $len === $w * $h * 3 => 3,
                default => 0,
            };
            if ($bpp === 0 || ! preg_match('#/BitsPerComponent\s+8#', $body) || preg_match('#/Indexed#', $body)) {
                return null; // no se puede decodificar con seguridad
            }

            $im = imagecreatetruecolor($w, $h);
            $o = 0;
            for ($y = 0; $y < $h; $y++) {
                for ($x = 0; $x < $w; $x++) {
                    if ($bpp === 1) {
                        $g = ord($raw[$o++]);
                        $col = ($g << 16) | ($g << 8) | $g;
                    } else {
                        $col = (ord($raw[$o]) << 16) | (ord($raw[$o + 1]) << 8) | ord($raw[$o + 2]);
                        $o += 3;
                    }
                    imagesetpixel($im, $x, $y, $col);
                }
            }
            ob_start();
            imagepng($im);
            $png = ob_get_clean();

            return ['mime' => 'image/png', 'ext' => 'png', 'bytes' => $png];
        }

        return null;
    }
}
