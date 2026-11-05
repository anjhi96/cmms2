<?php

namespace App\Services\MachineQr;

use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;

/**
 * Renders a Machine History QR code as a PNG with the machine number printed
 * in a white box at the dead centre.
 *
 * Drawn straight from the encoder's module matrix (no GD/Imagick needed) so
 * the centre label is part of the raster, not an overlay: every module outside
 * the label box is pixel-exact, and the box is sized to stay well inside the
 * error-correction budget of level H (30% of codewords). The PNG carries a
 * pHYs chunk so its physical size is exactly 2 cm x 2 cm at any renderer
 * that honours DPI metadata.
 *
 * The QR symbol itself has no built-in quiet zone: the 4-module quiet zone is
 * supplied by the white margin around it (label cell / sticker), which keeps
 * the symbol exactly 2 x 2 cm.
 */
final class MachineQrRenderer
{
    public const SIZE_CM = 2.0;

    /** Target raster width in pixels (about 1200 dpi over 2 cm). */
    private const TARGET_PX = 940;

    /** Label box may cover at most this fraction of the symbol width / height. */
    private const MAX_TEXT_WIDTH = 0.42;

    private const MAX_TEXT_HEIGHT = 0.20;

    /** @var array<string, list<string>> 5x7 glyphs, '#' = ink */
    private const FONT = [
        'A' => ['.###.', '#...#', '#...#', '#####', '#...#', '#...#', '#...#'],
        'B' => ['####.', '#...#', '#...#', '####.', '#...#', '#...#', '####.'],
        'C' => ['.####', '#....', '#....', '#....', '#....', '#....', '.####'],
        'D' => ['####.', '#...#', '#...#', '#...#', '#...#', '#...#', '####.'],
        'E' => ['#####', '#....', '#....', '####.', '#....', '#....', '#####'],
        'F' => ['#####', '#....', '#....', '####.', '#....', '#....', '#....'],
        'G' => ['.####', '#....', '#....', '#.###', '#...#', '#...#', '.###.'],
        'H' => ['#...#', '#...#', '#...#', '#####', '#...#', '#...#', '#...#'],
        'I' => ['#####', '..#..', '..#..', '..#..', '..#..', '..#..', '#####'],
        'J' => ['..###', '...#.', '...#.', '...#.', '...#.', '#..#.', '.##..'],
        'K' => ['#...#', '#..#.', '#.#..', '##...', '#.#..', '#..#.', '#...#'],
        'L' => ['#....', '#....', '#....', '#....', '#....', '#....', '#####'],
        'M' => ['#...#', '##.##', '#.#.#', '#.#.#', '#...#', '#...#', '#...#'],
        'N' => ['#...#', '##..#', '#.#.#', '#..##', '#...#', '#...#', '#...#'],
        'O' => ['.###.', '#...#', '#...#', '#...#', '#...#', '#...#', '.###.'],
        'P' => ['####.', '#...#', '#...#', '####.', '#....', '#....', '#....'],
        'Q' => ['.###.', '#...#', '#...#', '#...#', '#.#.#', '#..#.', '.##.#'],
        'R' => ['####.', '#...#', '#...#', '####.', '#.#..', '#..#.', '#...#'],
        'S' => ['.####', '#....', '#....', '.###.', '....#', '....#', '####.'],
        'T' => ['#####', '..#..', '..#..', '..#..', '..#..', '..#..', '..#..'],
        'U' => ['#...#', '#...#', '#...#', '#...#', '#...#', '#...#', '.###.'],
        'V' => ['#...#', '#...#', '#...#', '#...#', '#...#', '.#.#.', '..#..'],
        'W' => ['#...#', '#...#', '#...#', '#.#.#', '#.#.#', '##.##', '#...#'],
        'X' => ['#...#', '#...#', '.#.#.', '..#..', '.#.#.', '#...#', '#...#'],
        'Y' => ['#...#', '#...#', '.#.#.', '..#..', '..#..', '..#..', '..#..'],
        'Z' => ['#####', '....#', '...#.', '..#..', '.#...', '#....', '#####'],
        '0' => ['.###.', '#...#', '#..##', '#.#.#', '##..#', '#...#', '.###.'],
        '1' => ['..#..', '.##..', '..#..', '..#..', '..#..', '..#..', '.###.'],
        '2' => ['.###.', '#...#', '....#', '...#.', '..#..', '.#...', '#####'],
        '3' => ['####.', '....#', '....#', '.###.', '....#', '....#', '####.'],
        '4' => ['...#.', '..##.', '.#.#.', '#..#.', '#####', '...#.', '...#.'],
        '5' => ['#####', '#....', '####.', '....#', '....#', '#...#', '.###.'],
        '6' => ['.###.', '#....', '#....', '####.', '#...#', '#...#', '.###.'],
        '7' => ['#####', '....#', '...#.', '..#..', '.#...', '.#...', '.#...'],
        '8' => ['.###.', '#...#', '#...#', '.###.', '#...#', '#...#', '.###.'],
        '9' => ['.###.', '#...#', '#...#', '.####', '....#', '....#', '.###.'],
        '-' => ['.....', '.....', '.....', '#####', '.....', '.....', '.....'],
        '.' => ['.....', '.....', '.....', '.....', '.....', '.##..', '.##..'],
        '/' => ['....#', '....#', '...#.', '..#..', '.#...', '#....', '#....'],
        '_' => ['.....', '.....', '.....', '.....', '.....', '.....', '#####'],
        ' ' => ['.....', '.....', '.....', '.....', '.....', '.....', '.....'],
    ];

    /**
     * Encode $url and return the module matrix, level H.
     *
     * @return list<list<int>> rows of 0/1
     */
    public function matrix(string $url): array
    {
        $matrix = Encoder::encode($url, ErrorCorrectionLevel::H(), 'UTF-8')->getMatrix();

        $rows = [];
        for ($y = 0; $y < $matrix->getHeight(); $y++) {
            $row = [];
            for ($x = 0; $x < $matrix->getWidth(); $x++) {
                $row[] = $matrix->get($x, $y);
            }
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * @return array{pixels: list<string>, size: int, modules: int, scale: int, box: array{x0:int,y0:int,x1:int,y1:int}, matrix: list<list<int>>}
     *                                                                                                                                            pixels: one string per row, byte 0 = ink / 255 = paper
     */
    public function raster(string $url, string $label): array
    {
        $matrix = $this->matrix($url);
        $modules = count($matrix);
        $scale = max(1, (int) ceil(self::TARGET_PX / $modules));
        $size = $modules * $scale;

        $rows = [];
        foreach ($matrix as $row) {
            $line = '';
            foreach ($row as $bit) {
                $line .= str_repeat($bit ? "\x00" : "\xFF", $scale);
            }
            for ($i = 0; $i < $scale; $i++) {
                $rows[] = $line;
            }
        }

        $box = $this->drawLabel($rows, $size, $label);

        return [
            'pixels' => $rows,
            'size' => $size,
            'modules' => $modules,
            'scale' => $scale,
            'box' => $box,
            'matrix' => $matrix,
        ];
    }

    /** PNG bytes, physically 2 x 2 cm (pHYs). */
    public function png(string $url, string $label): string
    {
        $r = $this->raster($url, $label);

        return $this->encodePng($r['pixels'], $r['size']);
    }

    /**
     * Wraps long numbers onto two lines (at a '-' near the middle when there
     * is one) so the glyphs stay large enough to read at 2 cm.
     *
     * @return list<string>
     */
    public function labelLines(string $label): array
    {
        $label = strtoupper(trim(preg_replace('/\s+/', ' ', $label)));
        $label = preg_replace('/[^A-Z0-9\-\.\/_ ]/', '-', $label);

        if ($label === '') {
            return ['-'];
        }

        if (strlen($label) <= 8) {
            return [$label];
        }

        $mid = intdiv(strlen($label), 2);
        $best = null;
        foreach ([' ', '-', '/', '_', '.'] as $sep) {
            for ($i = 1; $i < strlen($label) - 1; $i++) {
                if ($label[$i] === $sep && ($best === null || abs($i - $mid) < abs($best - $mid))) {
                    $best = $i;
                }
            }
        }

        if ($best === null) {
            return [substr($label, 0, $mid), substr($label, $mid)];
        }

        // keep the separator on the first line (except a space, which is dropped)
        $first = $label[$best] === ' ' ? substr($label, 0, $best) : substr($label, 0, $best + 1);

        return [$first, ltrim(substr($label, $best + 1))];
    }

    /**
     * @param  list<string>  $rows
     * @return array{x0:int,y0:int,x1:int,y1:int}
     */
    private function drawLabel(array &$rows, int $size, string $label): array
    {
        $lines = $this->labelLines($label);
        $maxChars = max(array_map('strlen', $lines));
        $lineCount = count($lines);

        // unit u = pixels per font cell. Glyph advance = 6u, glyph height = 7u,
        // line gap = 2u, padding = 1.5u on every side.
        $widthUnits = 6 * $maxChars - 1;
        $heightUnits = 7 * $lineCount + 2 * ($lineCount - 1);

        $u = (int) floor(min(
            self::MAX_TEXT_WIDTH * $size / $widthUnits,
            self::MAX_TEXT_HEIGHT * $size / $heightUnits,
        ));
        $u = max(1, $u);

        $pad = (int) round(1.5 * $u);
        $textW = $widthUnits * $u;
        $textH = $heightUnits * $u;
        $boxW = $textW + 2 * $pad;
        $boxH = $textH + 2 * $pad;

        $x0 = intdiv($size - $boxW, 2);
        $y0 = intdiv($size - $boxH, 2);
        $x1 = $x0 + $boxW;
        $y1 = $y0 + $boxH;

        $this->fillRect($rows, $x0, $y0, $x1, $y1, "\xFF");

        // bold: each ink cell is widened/heightened by ~1/3 unit into the gap
        $extra = max(1, (int) round($u / 3));
        $cellSize = $u + $extra;

        foreach ($lines as $li => $line) {
            $lineW = (6 * strlen($line) - 1) * $u;
            $tx = $x0 + $pad + intdiv($textW - $lineW, 2);
            $ty = $y0 + $pad + $li * 9 * $u;

            foreach (str_split($line) as $ci => $ch) {
                $glyph = self::FONT[$ch] ?? self::FONT['-'];
                foreach ($glyph as $gy => $glyphRow) {
                    for ($gx = 0; $gx < 5; $gx++) {
                        if ($glyphRow[$gx] !== '#') {
                            continue;
                        }
                        $px = $tx + ($ci * 6 + $gx) * $u;
                        $py = $ty + $gy * $u;
                        $this->fillRect($rows, $px, $py, min($px + $cellSize, $x1), min($py + $cellSize, $y1), "\x00");
                    }
                }
            }
        }

        return ['x0' => $x0, 'y0' => $y0, 'x1' => $x1, 'y1' => $y1];
    }

    /** @param  list<string>  $rows */
    private function fillRect(array &$rows, int $x0, int $y0, int $x1, int $y1, string $byte): void
    {
        $len = $x1 - $x0;
        if ($len <= 0) {
            return;
        }
        $fill = str_repeat($byte, $len);
        for ($y = $y0; $y < $y1; $y++) {
            $rows[$y] = substr_replace($rows[$y], $fill, $x0, $len);
        }
    }

    /** @param  list<string>  $rows */
    private function encodePng(array $rows, int $size): string
    {
        $raw = '';
        foreach ($rows as $row) {
            $raw .= "\x00".$row; // filter type 0 per scanline
        }

        $ppm = (int) round($size / (self::SIZE_CM / 100)); // pixels per metre

        return "\x89PNG\r\n\x1a\n"
            .$this->chunk('IHDR', pack('NNCCCCC', $size, $size, 8, 0, 0, 0, 0))
            .$this->chunk('pHYs', pack('NNC', $ppm, $ppm, 1))
            .$this->chunk('IDAT', gzcompress($raw, 9))
            .$this->chunk('IEND', '');
    }

    private function chunk(string $type, string $data): string
    {
        return pack('N', strlen($data)).$type.$data.pack('N', crc32($type.$data));
    }
}
