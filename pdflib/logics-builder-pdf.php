<?php
define('FPDF_FONTPATH', __DIR__ . '/font/');
require_once(__DIR__ . '/fpdf.php');

class LB_PDF extends FPDF {
    private $widths;
    private $aligns;
    private $clinicName;

    function __construct($orientation, $unit, $size, $logo, $name) {
        parent::__construct($orientation, $unit, $size);
        $this->clinicName = $name;
    }

    function Header() {
        $this->SetFont('Arial', 'B', 15);
        $this->Cell(0, 10, $this->clinicName, 0, 1, 'C');
        $this->Ln(5);
    }

    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, 'Page ' . $this->PageNo(), 0, 0, 'C');
    }

    // --- ADD THESE MISSING FUNCTIONS BELOW ---

    function SetWidths($w) {
        // Sets the array of column widths
        $this->widths = $w;
    }

    function SetAligns($a) {
        // Sets the array of column alignments
        $this->aligns = $a;
    }

    function AddTableHeader($header) {
        $this->SetFont('Arial', 'B', 10);
        $this->SetFillColor(230, 230, 230);
        for($i=0; $i<count($header); $i++) {
            $this->Cell($this->widths[$i], 10, $header[$i], 1, 0, 'C', true);
        }
        $this->Ln();
    }

    function AddRow($data) {
        // Calculate the height of the row
        $nb = 0;
        for($i=0; $i<count($data); $i++)
            $nb = max($nb, $this->NbLines($this->widths[$i], $data[$i]));
        $h = 5 * $nb;
        
        // Issue a page break first if needed
        $this->CheckPageBreak($h);
        
        // Draw the cells of the row
        for($i=0; $i<count($data); $i++) {
            $w = $this->widths[$i];
            $a = isset($this->aligns[$i]) ? $this->aligns[$i] : 'L';
            $x = $this->GetX();
            $y = $this->GetY();
            $this->Rect($x, $y, $w, $h);
            $this->MultiCell($w, 5, $data[$i], 0, $a);
            $this->SetXY($x + $w, $y);
        }
        $this->Ln($h);
    }

    function CheckPageBreak($h) {
        if($this->GetY() + $h > $this->PageBreakTrigger)
            $this->AddPage($this->CurOrientation);
    }

    function NbLines($w, $txt) {
        // Computes the number of lines a MultiCell of width w will take
        $cw = &$this->CurrentFont['cw'];
        if($w == 0)
            $w = $this->w - $this->rMargin - $this->x;
        $wmax = ($w - 2 * $this->cMargin) * 1000 / $this->FontSize;
        $s = str_replace("\r", '', $txt);
        $nb = strlen($s);
        if($nb > 0 and $s[$nb-1] == "\n")
            $nb--;
        $sep = -1;
        $i = 0;
        $j = 0;
        $l = 0;
        $nl = 1;
        while($i < $nb) {
            $c = $s[$i];
            if($c == "\n") {
                $i++;
                $sep = -1;
                $j = $i;
                $l = 0;
                $nl++;
                continue;
            }
            if($c == ' ')
                $sep = $i;
            $l += $cw[$c];
            if($l > $wmax) {
                if($sep == -1) {
                    if($i == $j)
                        $i++;
                }
                else
                    $i = $sep + 1;
                $sep = -1;
                $j = $i;
                $l = 0;
                $nl++;
            }
            else
                $i++;
        }
        return $nl;
    }
}
?>