<?php

namespace App\Services\MachineQr;

use App\Models\Machine;
use Illuminate\Support\Collection;
use ZipStream\CompressionMethod;
use ZipStream\ZipStream;

/**
 * Builds the printable A4 label sheet (.docx) without needing ext-zip or a
 * Word library: a docx is a zip of a few XML parts, written with the
 * already-installed ZipStream. Each machine becomes one table cell holding the
 * QR PNG only (inline picture, fixed 720000 x 720000 EMU = exactly 2 x 2 cm,
 * aspect locked): a dense grid to cut out and stick on machines.
 */
final class MachineQrDocx
{
    public const QR_EMU = 720000; // 2 cm (360000 EMU per cm)

    /** 2.4 cm cell = 2 cm QR + 2 mm white quiet zone each side; cut along the dashed lines. */
    private const CELL = 1361;

    private const COLUMNS = 8;

    private const PAGE_W = 11906; // A4 portrait, twips

    private const PAGE_H = 16838;

    private const MARGIN = 454; // 0.8 cm

    public function __construct(private readonly MachineQrRenderer $renderer) {}

    public static function url(Machine $machine): string
    {
        return url('/m/'.rawurlencode($machine->machine_number));
    }

    /** @param  Collection<int, Machine>  $machines */
    public function build(Collection $machines): string
    {
        $media = [];
        $cells = [];

        foreach ($machines->values() as $i => $machine) {
            $n = $i + 1;
            $media["word/media/qr{$n}.png"] = $this->renderer->png(self::url($machine), $machine->machine_number);
            $cells[] = $this->cell($n, $machine);
        }

        $stream = fopen('php://temp', 'w+b');
        $zip = new ZipStream(
            outputStream: $stream,
            sendHttpHeaders: false,
            enableZip64: false,
            defaultCompressionMethod: CompressionMethod::DEFLATE,
        );

        $zip->addFile('[Content_Types].xml', $this->contentTypes());
        $zip->addFile('_rels/.rels', $this->rootRels());
        $zip->addFile('word/document.xml', $this->document($cells));
        $zip->addFile('word/_rels/document.xml.rels', $this->documentRels(count($media)));
        foreach ($media as $path => $png) {
            $zip->addFile($path, $png, compressionMethod: CompressionMethod::STORE);
        }
        $zip->finish();

        rewind($stream);
        $bytes = stream_get_contents($stream);
        fclose($stream);

        return $bytes;
    }

    private function e(string $s): string
    {
        return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private function cell(int $n, Machine $machine): string
    {
        $emu = self::QR_EMU;
        $alt = $this->e('QR '.$machine->machine_number);

        return '<w:tc><w:tcPr><w:tcW w:w="'.self::CELL.'" w:type="dxa"/><w:vAlign w:val="center"/></w:tcPr>'
            .'<w:p><w:pPr><w:spacing w:before="0" w:after="0" w:line="240" w:lineRule="auto"/><w:jc w:val="center"/></w:pPr><w:r><w:drawing>'
            .'<wp:inline distT="0" distB="0" distL="0" distR="0"><wp:extent cx="'.$emu.'" cy="'.$emu.'"/>'
            .'<wp:docPr id="'.$n.'" name="QR '.$n.'" descr="'.$alt.'"/>'
            .'<wp:cNvGraphicFramePr><a:graphicFrameLocks noChangeAspect="1"/></wp:cNvGraphicFramePr>'
            .'<a:graphic><a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/picture">'
            .'<pic:pic><pic:nvPicPr><pic:cNvPr id="'.$n.'" name="qr'.$n.'.png"/><pic:cNvPicPr><a:picLocks noChangeAspect="1"/></pic:cNvPicPr></pic:nvPicPr>'
            .'<pic:blipFill><a:blip r:embed="rIdQr'.$n.'"/><a:stretch><a:fillRect/></a:stretch></pic:blipFill>'
            .'<pic:spPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="'.$emu.'" cy="'.$emu.'"/></a:xfrm><a:prstGeom prst="rect"><a:avLst/></a:prstGeom></pic:spPr>'
            .'</pic:pic></a:graphicData></a:graphic></wp:inline></w:drawing></w:r></w:p>'
            .'</w:tc>';
    }

    private function cellWidth(): int
    {
        return self::CELL;
    }

    private function emptyCell(): string
    {
        return '<w:tc><w:tcPr><w:tcW w:w="'.$this->cellWidth().'" w:type="dxa"/></w:tcPr><w:p/></w:tc>';
    }

    /** @param  list<string>  $cells */
    private function document(array $cells): string
    {
        $w = $this->cellWidth();
        $rows = '';
        foreach (array_chunk($cells, self::COLUMNS) as $chunk) {
            while (count($chunk) < self::COLUMNS) {
                $chunk[] = $this->emptyCell();
            }
            // fixed minimum height keeps label spacing identical on every row
            $rows .= '<w:tr><w:trPr><w:cantSplit/><w:trHeight w:val="'.self::CELL.'" w:hRule="exact"/></w:trPr>'.implode('', $chunk).'</w:tr>';
        }

        if ($rows === '') {
            $rows = '<w:tr><w:tc><w:tcPr><w:tcW w:w="'.($w * self::COLUMNS).'" w:type="dxa"/><w:gridSpan w:val="'.self::COLUMNS.'"/></w:tcPr><w:p><w:r><w:t>No machines.</w:t></w:r></w:p></w:tc></w:tr>';
        }

        $border = static fn (string $side) => '<w:'.$side.' w:val="dashed" w:sz="4" w:space="0" w:color="BBBBBB"/>';
        $borders = '<w:tblBorders>'.implode('', array_map($border, ['top', 'left', 'bottom', 'right', 'insideH', 'insideV'])).'</w:tblBorders>';

        $grid = str_repeat('<w:gridCol w:w="'.$w.'"/>', self::COLUMNS);

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"'
            .' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"'
            .' xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing"'
            .' xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"'
            .' xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture"><w:body>'
            .'<w:tbl><w:tblPr><w:tblW w:w="'.($w * self::COLUMNS).'" w:type="dxa"/><w:jc w:val="left"/>'.$borders
            .'<w:tblLayout w:type="fixed"/><w:tblCellMar><w:top w:w="0" w:type="dxa"/><w:left w:w="0" w:type="dxa"/><w:bottom w:w="0" w:type="dxa"/><w:right w:w="0" w:type="dxa"/></w:tblCellMar></w:tblPr>'
            .'<w:tblGrid>'.$grid.'</w:tblGrid>'.$rows.'</w:tbl>'
            .'<w:p><w:pPr><w:spacing w:before="0" w:after="0" w:line="20" w:lineRule="exact"/></w:pPr></w:p>'
            .'<w:sectPr><w:pgSz w:w="'.self::PAGE_W.'" w:h="'.self::PAGE_H.'"/>'
            .'<w:pgMar w:top="'.self::MARGIN.'" w:right="'.self::MARGIN.'" w:bottom="'.self::MARGIN.'" w:left="'.self::MARGIN.'" w:header="0" w:footer="0" w:gutter="0"/></w:sectPr>'
            .'</w:body></w:document>';
    }

    private function contentTypes(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Default Extension="png" ContentType="image/png"/>'
            .'<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
            .'</Types>';
    }

    private function rootRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'
            .'</Relationships>';
    }

    private function documentRels(int $count): string
    {
        $rels = '';
        for ($n = 1; $n <= $count; $n++) {
            $rels .= '<Relationship Id="rIdQr'.$n.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/qr'.$n.'.png"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.$rels.'</Relationships>';
    }
}
