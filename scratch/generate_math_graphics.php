<?php

/**
 * Genera imágenes PNG vectoriales-renderizadas limpias para las opciones de Matemáticas 2026.
 */

function createBarChartPng(string $label, int $wTrans, int $wTrab, int $zTrans, int $zTrab, int $maxY = 900000): string {
    $width = 460;
    $height = 280;
    $im = imagecreatetruecolor($width, $height);
    
    // Colores
    $bg = imagecolorallocate($im, 255, 255, 255);
    $textCol = imagecolorallocate($im, 30, 41, 59);
    $gridCol = imagecolorallocate($im, 226, 232, 240);
    $borderCol = imagecolorallocate($im, 148, 163, 184);
    
    // Barras
    $barTrans = imagecolorallocate($im, 37, 99, 235); // Azul transporte
    $barTrab = imagecolorallocate($im, 249, 115, 22);  // Naranja trabajadores
    
    imagefilledrectangle($im, 0, 0, $width, $height, $bg);
    imagerectangle($im, 0, 0, $width - 1, $height - 1, $borderCol);
    
    // Título
    imagestring($im, 5, 20, 15, "GRAFICA {$label} - Costos de Transporte", $textCol);
    
    // Ejes
    $ox = 70;
    $oy = 220;
    $plotW = 350;
    $plotH = 170;
    
    // Líneas horizontales de guía
    $steps = 4;
    for ($i = 0; $i <= $steps; $i++) {
        $val = ($maxY / $steps) * $i;
        $y = $oy - ($plotH / $steps) * $i;
        imageline($im, $ox, (int)$y, $ox + $plotW, (int)$y, $gridCol);
        $valText = "$" . number_format($val / 1000, 0) . "k";
        imagestring($im, 2, 15, (int)$y - 7, $valText, $textCol);
    }
    
    imageline($im, $ox, $oy, $ox + $plotW, $oy, $textCol);
    imageline($im, $ox, $oy, $ox, $oy - $plotH, $textCol);
    
    // Dibujar barras Ciudad W
    $wX = $ox + 50;
    $barW = 32;
    $hWTrans = ($wTrans / $maxY) * $plotH;
    $hWTrab = ($wTrab / $maxY) * $plotH;
    
    imagefilledrectangle($im, $wX, (int)($oy - $hWTrans), $wX + $barW, $oy, $barTrans);
    imagefilledrectangle($im, $wX + $barW + 6, (int)($oy - $hWTrab), $wX + ($barW * 2) + 6, $oy, $barTrab);
    imagestring($im, 4, $wX + 10, $oy + 8, "Ciudad W", $textCol);
    
    // Dibujar barras Ciudad Z
    $zX = $ox + 210;
    $hZTrans = ($zTrans / $maxY) * $plotH;
    $hZTrab = ($zTrab / $maxY) * $plotH;
    
    imagefilledrectangle($im, $zX, (int)($oy - $hZTrans), $zX + $barW, $oy, $barTrans);
    imagefilledrectangle($im, $zX + $barW + 6, (int)($oy - $hZTrab), $zX + ($barW * 2) + 6, $oy, $barTrab);
    imagestring($im, 4, $zX + 10, $oy + 8, "Ciudad Z", $textCol);
    
    // Leyenda
    imagefilledrectangle($im, 100, 255, 120, 267, $barTrans);
    imagestring($im, 2, 126, 254, "Transporte", $textCol);
    
    imagefilledrectangle($im, 240, 255, 260, 267, $barTrab);
    imagestring($im, 2, 266, 254, "Pago Trabajadores", $textCol);
    
    ob_start();
    imagepng($im);
    imagedestroy($im);
    return ob_get_clean();
}

function createFieldDiagramPng(string $label, string $anchoStr, string $largoStr): string {
    $width = 380;
    $height = 240;
    $im = imagecreatetruecolor($width, $height);
    
    $bg = imagecolorallocate($im, 255, 255, 255);
    $textCol = imagecolorallocate($im, 30, 41, 59);
    $fieldCol = imagecolorallocate($im, 240, 253, 244);
    $lineCol = imagecolorallocate($im, 22, 163, 74);
    $borderCol = imagecolorallocate($im, 148, 163, 184);
    
    imagefilledrectangle($im, 0, 0, $width, $height, $bg);
    imagerectangle($im, 0, 0, $width - 1, $height - 1, $borderCol);
    
    // Cancha
    $fx = 60;
    $fy = 50;
    $fw = 240;
    $fh = 140;
    imagefilledrectangle($im, $fx, $fy, $fx + $fw, $fy + $fh, $fieldCol);
    imagerectangle($im, $fx, $fy, $fx + $fw, $fy + $fh, $lineCol);
    
    // Línea central y círculo
    imageline($im, $fx + (int)($fw / 2), $fy, $fx + (int)($fw / 2), $fy + $fh, $lineCol);
    imageellipse($im, $fx + (int)($fw / 2), $fy + (int)($fh / 2), 40, 40, $lineCol);
    
    // Áreas
    imagerectangle($im, $fx, $fy + 35, $fx + 30, $fy + $fh - 35, $lineCol);
    imagerectangle($im, $fx + $fw - 30, $fy + 35, $fx + $fw, $fy + $fh - 35, $lineCol);
    
    // Rotulaciones
    imagestring($im, 5, 20, 15, "DISENO {$label}", $textCol);
    
    // Largo (horizontal superior)
    imagestring($im, 4, $fx + 60, $fy - 20, "Largo: {$largoStr}", $textCol);
    
    // Ancho (vertical lateral)
    imagestring($im, 4, 10, $fy + 60, $anchoStr, $textCol);
    
    ob_start();
    imagepng($im);
    imagedestroy($im);
    return ob_get_clean();
}

function createFunctionGraphPng(string $label, string $typeDesc): string {
    $width = 380;
    $height = 250;
    $im = imagecreatetruecolor($width, $height);
    
    $bg = imagecolorallocate($im, 255, 255, 255);
    $textCol = imagecolorallocate($im, 30, 41, 59);
    $axisCol = imagecolorallocate($im, 71, 85, 105);
    $curveCol = imagecolorallocate($im, 2, 132, 199);
    $borderCol = imagecolorallocate($im, 148, 163, 184);
    $gridCol = imagecolorallocate($im, 241, 245, 249);
    
    imagefilledrectangle($im, 0, 0, $width, $height, $bg);
    imagerectangle($im, 0, 0, $width - 1, $height - 1, $borderCol);
    
    imagestring($im, 5, 20, 15, "GRAFICA {$label} (Plano xy)", $textCol);
    
    $ox = 60;
    $oy = 190;
    $gw = 280;
    $gh = 140;
    
    // Cuadrícula
    for ($gx = $ox; $gx <= $ox + $gw; $gx += 40) imageline($im, $gx, $oy - $gh, $gx, $oy, $gridCol);
    for ($gy = $oy; $gy >= $oy - $gh; $gy -= 35) imageline($im, $ox, $gy, $ox + $gw, $gy, $gridCol);
    
    // Ejes
    imageline($im, $ox, $oy, $ox + $gw, $oy, $axisCol); // Eje X
    imageline($im, $ox, $oy, $ox, $oy - $gh, $axisCol); // Eje Y
    imagestring($im, 3, $ox + $gw + 5, $oy - 6, "x", $axisCol);
    imagestring($im, 3, $ox - 5, $oy - $gh - 15, "y = Ax", $axisCol);
    imagestring($im, 2, $ox - 15, $oy - 5, "0", $axisCol);
    
    // Marcas en eje Y: 5, 10
    imagestring($im, 2, $ox - 25, $oy - 70, "5", $axisCol);
    imagestring($im, 2, $ox - 25, $oy - 140, "10", $axisCol);
    
    imagesetthickness($im, 3);
    
    if ($label === 'A') {
        // Recta creciente que pasa por (0,0) y (2,10)
        imageline($im, $ox, $oy, $ox + 160, $oy - 140, $curveCol);
    } elseif ($label === 'B') {
        // Recta horizontal constante en y = 5 para x > 0
        imageline($im, $ox + 8, $oy - 70, $ox + $gw, $oy - 70, $curveCol);
        imageellipse($im, $ox + 4, $oy - 70, 7, 7, $curveCol); // Círculo abierto en (0,5)
    } elseif ($label === 'C') {
        // Curva convexa cuadrática creciente
        for ($x = 0; $x < 240; $x++) {
            $y1 = ($x * $x) / 400;
            $y2 = (($x + 1) * ($x + 1)) / 400;
            if ($y2 <= $gh) {
                imageline($im, $ox + $x, (int)($oy - $y1), $ox + $x + 1, (int)($oy - $y2), $curveCol);
            }
        }
    } elseif ($label === 'D') {
        // Parábola cóncava desde (0,5) cortando eje X en 5
        for ($x = 0; $x < 200; $x++) {
            $y1 = 70 - (($x * $x) / 570);
            $y2 = 70 - ((($x + 1) * ($x + 1)) / 570);
            if ($y1 >= 0 && $y2 >= 0) {
                imageline($im, $ox + $x, (int)($oy - $y1), $ox + $x + 1, (int)($oy - $y2), $curveCol);
            }
        }
    }
    
    imagesetthickness($im, 1);
    imagestring($im, 3, 20, 220, $typeDesc, $textCol);
    
    ob_start();
    imagepng($im);
    imagedestroy($im);
    return ob_get_clean();
}

function createPackagesDiagramPng(): string {
    $width = 540;
    $height = 240;
    $im = imagecreatetruecolor($width, $height);
    
    $bg = imagecolorallocate($im, 255, 255, 255);
    $textCol = imagecolorallocate($im, 15, 23, 42);
    $shapeFill = imagecolorallocate($im, 238, 242, 255);
    $lineCol = imagecolorallocate($im, 79, 70, 229);
    $borderCol = imagecolorallocate($im, 148, 163, 184);
    
    imagefilledrectangle($im, 0, 0, $width, $height, $bg);
    imagerectangle($im, 0, 0, $width - 1, $height - 1, $borderCol);
    
    imagestring($im, 5, 20, 15, "EMPAQUES Y DIMENSIONES (Pregunta 50)", $textCol);
    
    // 1. Cilindro
    $cx = 100; $cy = 130;
    imagefilledellipse($im, $cx, $cy + 40, 70, 26, $shapeFill);
    imagefilledrectangle($im, $cx - 35, $cy - 40, $cx + 35, $cy + 40, $shapeFill);
    imagefilledellipse($im, $cx, $cy - 40, 70, 26, $shapeFill);
    
    imageellipse($im, $cx, $cy + 40, 70, 26, $lineCol);
    imageline($im, $cx - 35, $cy - 40, $cx - 35, $cy + 40, $lineCol);
    imageline($im, $cx + 35, $cy - 40, $cx + 35, $cy + 40, $lineCol);
    imageellipse($im, $cx, $cy - 40, 70, 26, $lineCol);
    imageline($im, $cx, $cy - 40, $cx + 35, $cy - 40, $lineCol);
    
    imagestring($im, 3, $cx - 30, 190, "1. Cilindro", $textCol);
    imagestring($im, 2, $cx - 40, 208, "r=3/2, h=2", $lineCol);
    
    // 2. Caja base cuadrada
    $bx = 270; $by = 90; $bw = 65; $bh = 80;
    imagefilledrectangle($im, $bx, $by, $bx + $bw, $by + $bh, $shapeFill);
    imagerectangle($im, $bx, $by, $bx + $bw, $by + $bh, $lineCol);
    // perspectiva
    imageline($im, $bx, $by, $bx + 20, $by - 15, $lineCol);
    imageline($im, $bx + $bw, $by, $bx + $bw + 20, $by - 15, $lineCol);
    imageline($im, $bx + 20, $by - 15, $bx + $bw + 20, $by - 15, $lineCol);
    imageline($im, $bx + $bw + 20, $by - 15, $bx + $bw + 20, $by + $bh - 15, $lineCol);
    imageline($im, $bx + $bw, $by + $bh, $bx + $bw + 20, $by + $bh - 15, $lineCol);
    
    imagestring($im, 3, $bx - 5, 190, "2. Caja cuadrada", $textCol);
    imagestring($im, 2, $bx - 5, 208, "L=3/2, h=2", $lineCol);
    
    // 3. Esfera
    $ex = 440; $ey = 130;
    imagefilledellipse($im, $ex, $ey, 75, 75, $shapeFill);
    imageellipse($im, $ex, $ey, 75, 75, $lineCol);
    imageellipse($im, $ex, $ey, 75, 22, $lineCol); // ecuador
    imageline($im, $ex, $ey, $ex + 37, $ey, $lineCol); // radio
    
    imagestring($im, 3, $ex - 25, 190, "3. Esfera", $textCol);
    imagestring($im, 2, $ex - 20, 208, "r = 3/2", $lineCol);
    
    ob_start();
    imagepng($im);
    imagedestroy($im);
    return ob_get_clean();
}

function createGeometricSquaresDiagramPng(): string {
    $width = 540;
    $height = 230;
    $im = imagecreatetruecolor($width, $height);
    
    $bg = imagecolorallocate($im, 255, 255, 255);
    $textCol = imagecolorallocate($im, 15, 23, 42);
    $sqFill = imagecolorallocate($im, 254, 243, 199);
    $shadeFill = imagecolorallocate($im, 245, 158, 11);
    $lineCol = imagecolorallocate($im, 180, 83, 9);
    $borderCol = imagecolorallocate($im, 148, 163, 184);
    
    imagefilledrectangle($im, 0, 0, $width, $height, $bg);
    imagerectangle($im, 0, 0, $width - 1, $height - 1, $borderCol);
    
    imagestring($im, 5, 20, 15, "CONSTRUCCION GEOMETRICA (Pregunta 24)", $textCol);
    
    // Figura 1
    $f1x = 50; $f1y = 50; $s = 100;
    imagefilledrectangle($im, $f1x, $f1y, $f1x + $s, $f1y + $s, $sqFill);
    imagerectangle($im, $f1x, $f1y, $f1x + $s, $f1y + $s, $lineCol);
    // Paralelogramo sombreado
    $pts = [
        $f1x, $f1y + 50,
        $f1x + 50, $f1y,
        $f1x + 100, $f1y + 50,
        $f1x + 50, $f1y + 100
    ];
    imagefilledpolygon($im, $pts, $shadeFill);
    imagepolygon($im, $pts, $lineCol);
    imagestring($im, 3, $f1x + 15, $f1y + $s + 12, "Figura 1", $textCol);
    imagestring($im, 2, $f1x - 5, $f1y + $s + 30, "Area = 1 dm2", $lineCol);
    
    // Figura 2 (construcción de Eloísa)
    $f2x = 215; $f2y = 50;
    imagefilledrectangle($im, $f2x, $f2y, $f2x + $s, $f2y + $s, $shadeFill);
    imagerectangle($im, $f2x, $f2y, $f2x + $s, $f2y + $s, $lineCol);
    imageline($im, $f2x + 50, $f2y, $f2x + 50, $f2y + $s, $lineCol);
    imageline($im, $f2x, $f2y + 50, $f2x + $s, $f2y + 50, $lineCol);
    imagestring($im, 3, $f2x + 15, $f2y + $s + 12, "Figura 2", $textCol);
    imagestring($im, 2, $f2x - 10, $f2y + $s + 30, "4 partes = 1/4 dm2", $lineCol);
    
    // Figura 3 (pregunta central)
    $f3x = 380; $f3y = 50;
    imagefilledrectangle($im, $f3x, $f3y, $f3x + $s, $f3y + $s, $sqFill);
    imagerectangle($im, $f3x, $f3y, $f3x + $s, $f3y + $s, $lineCol);
    // Cuadrado sombreado central inclinado
    $pts3 = [
        $f3x + 30, $f3y + 40,
        $f3x + 60, $f3y + 30,
        $f3x + 70, $f3y + 60,
        $f3x + 40, $f3y + 70
    ];
    imagefilledpolygon($im, $pts3, $shadeFill);
    imagepolygon($im, $pts3, $lineCol);
    imagestring($im, 3, $f3x + 15, $f3y + $s + 12, "Figura 3", $textCol);
    imagestring($im, 2, $f3x + 5, $f3y + $s + 30, "Area = 1/5 dm2", $lineCol);
    
    ob_start();
    imagepng($im);
    imagedestroy($im);
    return ob_get_clean();
}

echo "Funciones de generación gráfica registradas.\n";

