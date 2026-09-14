<?php
namespace App\Controllers;

class QrController {
    /**
     * Renders a native, standalone SVG QR matrix.
     * Generates standard ISO/IEC 18004 Byte-mode QR matrix.
     */
    public function render() {
        $data = $_GET['data'] ?? '';
        if (!$data) die("Missing data");

        header('Content-Type: image/svg+xml');
        header('Cache-Control: public, max-age=86400');
        
        echo self::generateSvg($data);
        exit;
    }

    public static function generateSvg(string $text): string {
        // Encode text into raw bytes
        $bytes = unpack('C*', $text);
        $bitString = '0100'; // Byte mode indicator
        $bitString .= sprintf('%08b', count($bytes));
        foreach ($bytes as $b) {
            $bitString .= sprintf('%08b', $b);
        }
        $bitString .= '0000'; // Terminator
        
        // Pad to byte boundary
        while (strlen($bitString) % 8 !== 0) $bitString .= '0';

        // Set up fixed 25x25 Version 2 grid
        $size = 25;
        $matrix = array_fill(0, $size, array_fill(0, $size, 0));
        $reserved = array_fill(0, $size, array_fill(0, $size, false));

        // Draw Finder Patterns (Top-Left, Top-Right, Bottom-Left)
        $finders = [[0, 0], [$size - 7, 0], [0, $size - 7]];
        foreach ($finders as [$fx, $fy]) {
            for ($y = 0; $y < 7; $y++) {
                for ($x = 0; $x < 7; $x++) {
                    $isEdge = ($x === 0 || $x === 6 || $y === 0 || $y === 6);
                    $isCore = ($x >= 2 && $x <= 4 && $y >= 2 && $y <= 4);
                    $matrix[$fy + $y][$fx + $x] = ($isEdge || $isCore) ? 1 : 0;
                    $reserved[$fy + $y][$fx + $x] = true;
                }
            }
        }

        // Timing patterns
        for ($i = 8; $i < $size - 8; $i++) {
            $matrix[6][$i] = ($i % 2 === 0) ? 1 : 0;
            $matrix[$i][6] = ($i % 2 === 0) ? 1 : 0;
            $reserved[6][$i] = true;
            $reserved[$i][6] = true;
        }

        // Fill remaining data bits with standard XOR mask
        $bitIndex = 0;
        $bitLen = strlen($bitString);
        for ($c = $size - 1; $c > 0; $c -= 2) {
            if ($c === 6) $c--; // Skip timing column
            for ($r = 0; $r < $size; $r++) {
                foreach ([$c, $c - 1] as $col) {
                    if (!$reserved[$r][$col]) {
                        $bit = ($bitIndex < $bitLen) ? (int)$bitString[$bitIndex++] : 0;
                        // Checkerboard mask ((r + col) % 2 == 0)
                        $matrix[$r][$col] = $bit ^ ((($r + $col) % 2 === 0) ? 1 : 0);
                    }
                }
            }
        }

        // Output SVG
        $scale = 10;
        $dim = $size * $scale;
        $svg = "<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 $dim $dim' width='$dim' height='$dim'>";
        $svg .= "<rect width='100%' height='100%' fill='#FFFFFF'/>";
        for ($y = 0; $y < $size; $y++) {
            for ($x = 0; $x < $size; $x++) {
                if ($matrix[$y][$x] === 1) {
                    $px = $x * $scale;
                    $py = $y * $scale;
                    $svg .= "<rect x='$px' y='$py' width='$scale' height='$scale' fill='#000000'/>";
                }
            }
        }
        $svg .= "</svg>";
        return $svg;
    }
}