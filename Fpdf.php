<?php
/*******************************************************************************
* FPDF                                                                         *
*                                                                              *
* Version: 1.7 (mod 2)                                                           *
* Date:    2011-06-18                                                          *
* Author:  Olivier PLATHEY                                                     *
* Modified by Leonard Challis (@LeonardChallis) to PHP5                        *
*******************************************************************************/

define('FPDF_VERSION', '1.7 (mod 2)');

class Fpdf
{
    private $page; // current page number
    private $n; // current object number
    private $offsets; // array of object offsets
    private $buffer; // buffer holding in-memory PDF
    private $pages; // array containing pages
    private $state; // current document state
    private $compress; // compression flag
    private $k; // scale factor (number of points in user unit)
    private $defOrientation; // default orientation
    private $curOrientation; // current orientation
    private $stdPageSizes; // standard page sizes
    private $defPageSize; // default page size
    private $curPageSize; // current page size
    private $pageSizes; // used for pages with non default sizes or orientations
    private $wPt, $hPt; // dimensions of current page in points
    private $w, $h; // dimensions of current page in user unit
    private $lMargin; // left margin
    private $tMargin; // top margin
    private $rMargin; // right margin
    private $bMargin; // page break margin
    private $cMargin; // cell margin
    private $x, $y; // current position in user unit
    private $lastH; // height of last printed cell
    private $lineWidth; // line width in user unit
    private $fontPath; // path containing fonts
    private $coreFonts; // array of core font names
    private $fonts; // array of used fonts
    private $fontFiles; // array of font files
    private $diffs; // array of encoding differences
    private $fontFamily; // current font family
    private $fontStyle; // current font style
    private $underline; // underlining flag
    private $currentFont; // current font info
    private $fontSizePt; // current font size in points
    private $fontSize; // current font size in user unit
    private $drawColor; // commands for drawing color
    private $fillColor; // commands for filling color
    private $textColor; // commands for text color
    private $colorFlag; // indicates whether fill and text colors are different
    private $ws; // word spacing
    private $images; // array of used images
    private $PageLinks; // array of links in pages
    private $links; // array of internal links
    private $autoPageBreak; // automatic page breaking
    private $pageBreakTrigger; // threshold used to trigger page breaks
    private $inHeader; // flag set when processing header
    private $inFooter; // flag set when processing footer
    private $zoomMode; // zoom display mode
    private $layoutMode; // layout display mode
    private $title; // title
    private $subject; // subject
    private $author; // author
    private $keywords; // keywords
    private $creator; // creator
    private $aliasNbPages; // alias for total number of pages
    private $pdfVersion; // PDF version number

    public function __construct($orientation = 'P', $unit = 'mm', $size = 'A4')
    {
        // Some checks
        $this->doChecks();
        // Initialization of properties
        $this->page = 0;
        $this->n = 2;
        $this->buffer = '';
        $this->pages = array();
        $this->pageSizes = array();
        $this->state = 0;
        $this->fonts = array();
        $this->fontFiles = array();
        $this->diffs = array();
        $this->images = array();
        $this->links = array();
        $this->inHeader = false;
        $this->inFooter = false;
        $this->lastH = 0;
        $this->fontFamily = '';
        $this->fontStyle = '';
        $this->fontSizePt = 12;
        $this->underline = false;
        $this->drawColor = '0 G';
        $this->fillColor = '0 g';
        $this->textColor = '0 g';
        $this->colorFlag = false;
        $this->ws = 0;
        // Font path
        if (defined('FPDF_FONTPATH')) {
            $this->fontPath = FPDF_FONTPATH;
            if (substr($this->fontPath, -1) != '/' && substr($this->fontPath, -1) != '\\')
                $this->fontPath .= '/';
        } elseif (is_dir(dirname(__FILE__) . '/font')) {
            $this->fontPath = dirname(__FILE__) . '/font/';
        } else {
            $this->fontPath = '';
        }
        // Core fonts
        $this->coreFonts = array(
            'courier',
            'helvetica',
            'times',
            'symbol',
            'zapfdingbats'
        );
        // Scale factor
        if ($unit == 'pt') {
            $this->k = 1;
        } elseif ($unit == 'mm') {
            $this->k = 72 / 25.4;
        } elseif ($unit == 'cm') {
            $this->k = 72 / 2.54;
        } elseif ($unit == 'in') {
            $this->k = 72;
        } else {
            $this->error('Incorrect unit: ' . $unit);
        }
        // Page sizes
        $this->stdPageSizes = array(
            'a3' => array(
                841.89,
                1190.55
            ),
            'a4' => array(
                595.28,
                841.89
            ),
            'a5' => array(
                420.94,
                595.28
            ),
            'letter' => array(
                612,
                792
            ),
            'legal' => array(
                612,
                1008
            )
        );
        $size = $this->getPageSize($size);
        $this->defPageSize = $size;
        $this->curPageSize = $size;
        // Page orientation
        $orientation = strtolower($orientation);
        if ($orientation == 'p' || $orientation == 'portrait') {
            $this->defOrientation = 'P';
            $this->w = $size[0];
            $this->h = $size[1];
        } elseif ($orientation == 'l' || $orientation == 'landscape') {
            $this->defOrientation = 'L';
            $this->w = $size[1];
            $this->h = $size[0];
        } else {
            $this->error('Incorrect orientation: ' . $orientation);
        }
        $this->curOrientation = $this->defOrientation;
        $this->wPt = $this->w * $this->k;
        $this->hPt = $this->h * $this->k;
        // Page margins (1 cm)
        $margin = 28.35 / $this->k;
        $this->setMargins($margin, $margin);
        // Interior cell margin (1 mm)
        $this->cMargin = $margin / 10;
        // Line width (0.2 mm)
        $this->lineWidth = .567 / $this->k;
        // Automatic page break
        $this->setAutoPageBreak(true, 2 * $margin);
        // Default display mode
        $this->setDisplayMode('default');
        // Enable compression
        $this->setCompression(true);
        // Set default PDF version number
        $this->pdfVersion = '1.3';
    }

    public function __call($name, $arguments)
    {
        $name = lcfirst($name);
        $class = new ReflectionClass($this);

        $error = false;

        if (!method_exists($this, $name)) {
            $error = true;
        } elseif (get_called_class() === __CLASS__ && true !== $class->getMethod($name)->isPublic()) {
            $error = true;
        } elseif (get_called_class() !== __CLASS__ && true === $class->getMethod($name)->isPrivate()) {
            $error = true;
        }

        if ($error) {
            $class = get_class($this);
            $trace = debug_backtrace();
            $file = $trace[0]['file'];
            $line = $trace[0]['line'];
            trigger_error("Call to undefined method $class::$name() in $file on line $line", E_USER_ERROR);
            break;
        } else {
            return call_user_func_array(array(
                $this,
                $name
            ), $arguments);
        }
    }

    public function setMargins($left, $top, $right = null)
    {
        // Set left, top and right margins
        $this->lMargin = $left;
        $this->tMargin = $top;
        if ($right === null)
            $right = $left;
        $this->rMargin = $right;
    }

    public function setLeftMargin($margin)
    {
        // Set left margin
        $this->lMargin = $margin;
        if ($this->page > 0 && $this->x < $margin)
            $this->x = $margin;
    }

    public function setTopMargin($margin)
    {
        // Set top margin
        $this->tMargin = $margin;
    }

    public function setRightMargin($margin)
    {
        // Set right margin
        $this->rMargin = $margin;
    }

    public function setAutoPageBreak($auto, $margin = 0)
    {
        // Set auto page break mode and triggering margin
        $this->autoPageBreak = $auto;
        $this->bMargin = $margin;
        $this->pageBreakTrigger = $this->h - $margin;
    }

    public function setDisplayMode($zoom, $layout = 'default')
    {
        // Set display mode in viewer
        if ($zoom == 'fullpage' || $zoom == 'fullwidth' || $zoom == 'real' || $zoom == 'default' || !is_string($zoom)) {
            $this->zoomMode = $zoom;
        } else {
            $this->error('Incorrect zoom display mode: ' . $zoom);
        }

        if ($layout == 'single' || $layout == 'continuous' || $layout == 'two' || $layout == 'default') {
            $this->layoutMode = $layout;
        } else {
            $this->error('Incorrect layout display mode: ' . $layout);
        }
    }

    public function setCompression($compress)
    {
        // Set page compression
        if (function_exists('gzcompress')) {
            $this->compress = $compress;
        } else {
            $this->compress = false;
        }
    }

    public function setTitle($title, $isUTF8 = false)
    {
        // Title of document
        if ($isUTF8) {
            $title = $this->utf8ToUtf16($title);
        }
        $this->title = $title;
    }

    public function setSubject($subject, $isUTF8 = false)
    {
        // Subject of document
        if ($isUTF8) {
            $subject = $this->utf8ToUtf16($subject);
        }
        $this->subject = $subject;
    }

    public function setAuthor($author, $isUTF8 = false)
    {
        // Author of document
        if ($isUTF8) {
            $author = $this->utf8ToUtf16($author);
        }
        $this->author = $author;
    }

    public function setKeywords($keywords, $isUTF8 = false)
    {
        // Keywords of document
        if ($isUTF8) {
            $keywords = $this->utf8ToUtf16($keywords);
        }
        $this->keywords = $keywords;
    }

    public function setCreator($creator, $isUTF8 = false)
    {
        // Creator of document
        if ($isUTF8) {
            $creator = $this->utf8ToUtf16($creator);
        }
        $this->creator = $creator;
    }

    public function aliasNbPages($alias = '{nb}')
    {
        // Define an alias for total number of pages
        $this->aliasNbPages = $alias;
    }

    public function error($msg)
    {
        // Fatal error
        die('<b>FPDF error:</b> ' . $msg);
    }

    public function open()
    {
        // Begin document
        $this->state = 1;
    }

    public function close()
    {
        // Terminate document
        if ($this->state == 3) {
            return;
        }
        if ($this->page == 0) {
            $this->addPage();
        }
        // Page footer
        $this->inFooter = true;
        $this->footer();
        $this->inFooter = false;
        // Close page
        $this->endPage();
        // Close document
        $this->endDoc();
    }

    public function addPage($orientation = '', $size = '')
    {
        // Start a new page
        if ($this->state == 0) {
            $this->open();
        }
        $family = $this->fontFamily;
        $style = $this->fontStyle . ($this->underline ? 'U' : '');
        $fontsize = $this->fontSizePt;
        $lw = $this->lineWidth;
        $dc = $this->drawColor;
        $fc = $this->fillColor;
        $tc = $this->textColor;
        $cf = $this->colorFlag;
        if ($this->page > 0) {
            // Page footer
            $this->inFooter = true;
            $this->footer();
            $this->inFooter = false;
            // Close page
            $this->endPage();
        }
        // Start new page
        $this->beginPage($orientation, $size);
        // Set line cap style to square
        $this->out('2 J');
        // Set line width
        $this->lineWidth = $lw;
        $this->out(sprintf('%.2F w', $lw * $this->k));
        // Set font
        if ($family) {
            $this->setFont($family, $style, $fontsize);
        }
        // Set colors
        $this->drawColor = $dc;
        if ($dc != '0 G') {
            $this->out($dc);
        }
        $this->fillColor = $fc;
        if ($fc != '0 g') {
            $this->out($fc);
        }
        $this->textColor = $tc;
        $this->colorFlag = $cf;
        // Page header
        $this->inHeader = true;
        $this->header();
        $this->inHeader = false;
        // Restore line width
        if ($this->lineWidth != $lw) {
            $this->lineWidth = $lw;
            $this->out(sprintf('%.2F w', $lw * $this->k));
        }
        // Restore font
        if ($family) {
            $this->setFont($family, $style, $fontsize);
        }
        // Restore colors
        if ($this->drawColor != $dc) {
            $this->drawColor = $dc;
            $this->out($dc);
        }
        if ($this->fillColor != $fc) {
            $this->fillColor = $fc;
            $this->out($fc);
        }
        $this->textColor = $tc;
        $this->colorFlag = $cf;
    }

    public function header()
    {
        // To be implemented in your own inherited class
    }

    public function footer()
    {
        // To be implemented in your own inherited class
    }

    public function pageNo()
    {
        // Get current page number
        return $this->page;
    }

    public function setDrawColor($r, $g = null, $b = null)
    {
        // Set color for all stroking operations
        if (($r == 0 && $g == 0 && $b == 0) || $g === null) {
            $this->drawColor = sprintf('%.3F G', $r / 255);
        } else {
            $this->drawColor = sprintf('%.3F %.3F %.3F RG', $r / 255, $g / 255, $b / 255);
        }
        if ($this->page > 0) {
            $this->out($this->drawColor);
        }
    }

    public function setFillColor($r, $g = null, $b = null)
    {
        // Set color for all filling operations
        if (($r == 0 && $g == 0 && $b == 0) || $g === null) {
            $this->fillColor = sprintf('%.3F g', $r / 255);
        } else {
            $this->fillColor = sprintf('%.3F %.3F %.3F rg', $r / 255, $g / 255, $b / 255);
        }
        $this->colorFlag = ($this->fillColor != $this->textColor);
        if ($this->page > 0) {
            $this->out($this->fillColor);
        }
    }

    public function setTextColor($r, $g = null, $b = null)
    {
        // Set color for text
        if (($r == 0 && $g == 0 && $b == 0) || $g === null) {
            $this->textColor = sprintf('%.3F g', $r / 255);
        } else {
            $this->textColor = sprintf('%.3F %.3F %.3F rg', $r / 255, $g / 255, $b / 255);
        }
        $this->colorFlag = ($this->fillColor != $this->textColor);
    }

    public function getStringWidth($s)
    {
        // Get width of a string in the current font
        $s = (string)$s;
        $cw = &$this->currentfont['cw'];
        $w = 0;
        $l = strlen($s);
        for ($i = 0;$i < $l;$i++) {
            $w += $cw[$s[$i]];
        }
        return $w * $this->fontSize / 1000;
    }

    public function setLineWidth($width)
    {
        // Set line width
        $this->lineWidth = $width;
        if ($this->page > 0) {
            $this->out(sprintf('%.2F w', $width * $this->k));
        }
    }

    public function line($x1, $y1, $x2, $y2)
    {
        // Draw a line
        $this->out(sprintf('%.2F %.2F m %.2F %.2F l S', $x1 * $this->k, ($this->h - $y1) * $this->k, $x2 * $this->k, ($this->h - $y2) * $this->k));
    }

    public function rect($x, $y, $w, $h, $style = '')
    {
        // Draw a rectangle
        if ($style == 'F') {
            $op = 'f';
        } elseif ($style == 'FD' || $style == 'DF') {
            $op = 'B';
        } else {
            $op = 'S';
        }
        $this->out(sprintf('%.2F %.2F %.2F %.2F re %s', $x * $this->k, ($this->h - $y) * $this->k, $w * $this->k, -$h * $this->k, $op));
    }

    public function addFont($family, $style = '', $file = '')
    {
        // Add a TrueType, OpenType or Type1 font
        $family = strtolower($family);
        if ($file == '') {
            $file = str_replace(' ', '', $family) . strtolower($style) . '.php';
        }
        $style = strtoupper($style);
        if ($style == 'IB') {
            $style = 'BI';
        }
        $fontkey = $family . $style;
        if (isset($this->fonts[$fontkey])) {
            return;
        }
        $info = $this->loadFont($file);
        $info['i'] = count($this->fonts) + 1;
        if (!empty($info['diff'])) {
            // Search existing encodings
            $n = array_search($info['diff'], $this->diffs);
            if (!$n) {
                $n = count($this->diffs) + 1;
                $this->diffs[$n] = $info['diff'];
            }
            $info['diffn'] = $n;
        }
        if (!empty($info['file'])) {
            // Embedded font
            if ($info['type'] == 'TrueType') {
                $this->fontFiles[$info['file']] = array(
                    'length1' => $info['originalsize']
                );
            } else {
                $this->fontFiles[$info['file']] = array(
                    'length1' => $info['size1'],
                    'length2' => $info['size2']
                );
            }
        }
        $this->fonts[$fontkey] = $info;
    }

    public function setFont($family, $style = '', $size = 0)
    {
        // Select a font; size given in points
        if ($family == '') {
            $family = $this->fontFamily;
        } else {
            $family = strtolower($family);
        }
        $style = strtoupper($style);
        if (strpos($style, 'U') !== false) {
            $this->underline = true;
            $style = str_replace('U', '', $style);
        } else {
            $this->underline = false;
        }
        if ($style == 'IB') {
            $style = 'BI';
        }
        if ($size == 0) {
            $size = $this->fontSizePt;
        }
        // Test if font is already selected
        if ($this->fontFamily == $family && $this->fontStyle == $style && $this->fontSizePt == $size) {
            return;
        }
        // Test if font is already loaded
        $fontkey = $family . $style;
        if (!isset($this->fonts[$fontkey])) {
            // Test if one of the core fonts
            if ($family == 'arial') {
                $family = 'helvetica';
            }
            if (in_array($family, $this->coreFonts)) {
                if ($family == 'symbol' || $family == 'zapfdingbats') {
                    $style = '';
                }
                $fontkey = $family . $style;
                if (!isset($this->fonts[$fontkey])) {
                    $this->addFont($family, $style);
                }
            } else {
                $this->error('Undefined font: ' . $family . ' ' . $style);
            }
        }
        // Select it
        $this->fontFamily = $family;
        $this->fontStyle = $style;
        $this->fontSizePt = $size;
        $this->fontSize = $size / $this->k;
        $this->currentfont = &$this->fonts[$fontkey];
        if ($this->page > 0) {
            $this->out(sprintf('BT /F%d %.2F Tf ET', $this->currentfont['i'], $this->fontSizePt));
        }
    }

    public function setFontSize($size)
    {
        // Set font size in points
        if ($this->fontSizePt == $size) {
            return;
        }
        $this->fontSizePt = $size;
        $this->fontSize = $size / $this->k;
        if ($this->page > 0) {
            $this->out(sprintf('BT /F%d %.2F Tf ET', $this->currentfont['i'], $this->fontSizePt));
        }
    }

    public function addlink()
    {
        // Create a new internal link
        $n = count($this->links) + 1;
        $this->links[$n] = array(
            0,
            0
        );
        return $n;
    }

    public function setlink($link, $y = 0, $page = -1)
    {
        // Set destination of internal link
        if ($y == -1) {
            $y = $this->y;
        }
        if ($page == -1) {
            $page = $this->page;
        }
        $this->links[$link] = array(
            $page,
            $y
        );
    }

    public function link($x, $y, $w, $h, $link)
    {
        // Put a link on the page
        $this->pageLinks[$this->page][] = array(
            $x * $this->k,
            $this->hPt - $y * $this->k,
            $w * $this->k,
            $h * $this->k,
            $link
        );
    }

    public function text($x, $y, $txt)
    {
        // Output a string
        $s = sprintf('BT %.2F %.2F Td (%s) Tj ET', $x * $this->k, ($this->h - $y) * $this->k, $this->escape($txt));
        if ($this->underline && $txt != '') {
            $s .= ' ' . $this->doUnderline($x, $y, $txt);
        }
        if ($this->colorFlag) {
            $s = 'q ' . $this->textColor . ' ' . $s . ' Q';
        }
        $this->out($s);
    }

    public function acceptPageBreak()
    {
        // Accept automatic page break or not
        return $this->autoPageBreak;
    }

    public function cell($w, $h = 0, $txt = '', $border = 0, $ln = 0, $align = '', $fill = false, $link = '')
    {
        // Output a cell
        $k = $this->k;
        if ($this->y + $h > $this->pageBreakTrigger && !$this->inHeader && !$this->inFooter && $this->acceptPageBreak()) {
            // Automatic page break
            $x = $this->x;
            $ws = $this->ws;
            if ($ws > 0) {
                $this->ws = 0;
                $this->out('0 Tw');
            }
            $this->addPage($this->curOrientation, $this->curPageSize);
            $this->x = $x;
            if ($ws > 0) {
                $this->ws = $ws;
                $this->out(sprintf('%.3F Tw', $ws * $k));
            }
        }
        if ($w == 0) {
            $w = $this->w - $this->rMargin - $this->x;
        }
        $s = '';
        if ($fill || $border == 1) {
            if ($fill) {
                $op = ($border == 1) ? 'B' : 'f';
            } else {
                $op = 'S';
            }
            $s = sprintf('%.2F %.2F %.2F %.2F re %s ', $this->x * $k, ($this->h - $this->y) * $k, $w * $k, -$h * $k, $op);
        }
        if (is_string($border)) {
            $x = $this->x;
            $y = $this->y;
            if (strpos($border, 'L') !== false) {
                $s .= sprintf('%.2F %.2F m %.2F %.2F l S ', $x * $k, ($this->h - $y) * $k, $x * $k, ($this->h - ($y + $h)) * $k);
            }
            if (strpos($border, 'T') !== false) {
                $s .= sprintf('%.2F %.2F m %.2F %.2F l S ', $x * $k, ($this->h - $y) * $k, ($x + $w) * $k, ($this->h - $y) * $k);
            }
            if (strpos($border, 'R') !== false) {
                $s .= sprintf('%.2F %.2F m %.2F %.2F l S ', ($x + $w) * $k, ($this->h - $y) * $k, ($x + $w) * $k, ($this->h - ($y + $h)) * $k);
            }
            if (strpos($border, 'B') !== false) {
                $s .= sprintf('%.2F %.2F m %.2F %.2F l S ', $x * $k, ($this->h - ($y + $h)) * $k, ($x + $w) * $k, ($this->h - ($y + $h)) * $k);
            }
        }
        if ($txt !== '') {
            if ($align == 'R') {
                $dx = $w - $this->cMargin - $this->getStringWidth($txt);
            } elseif ($align == 'C') {
                $dx = ($w - $this->getStringWidth($txt)) / 2;
            } else {
                $dx = $this->cMargin;
            }
            if ($this->colorFlag) {
                $s .= 'q ' . $this->textColor . ' ';
            }
            $txt2 = str_replace(')', '\\)', str_replace('(', '\\(', str_replace('\\', '\\\\', $txt)));
            $s .= sprintf('BT %.2F %.2F Td (%s) Tj ET', ($this->x + $dx) * $k, ($this->h - ($this->y + .5 * $h + .3 * $this->fontSize)) * $k, $txt2);
            if ($this->underline) {
                $s .= ' ' . $this->doUnderline($this->x + $dx, $this->y + .5 * $h + .3 * $this->fontSize, $txt);
            }
            if ($this->colorFlag) {
                $s .= ' Q';
            }
            if ($link) {
                $this->link($this->x + $dx, $this->y + .5 * $h - .5 * $this->fontSize, $this->getStringWidth($txt), $this->fontSize, $link);
            }
        }
        if ($s) {
            $this->out($s);
        }
        $this->lastH = $h;
        if ($ln > 0) {
            // Go to next line
            $this->y += $h;
            if ($ln == 1) {
                $this->x = $this->lMargin;
            }
        } else {
            $this->x += $w;
        }
    }

    public function multiCell($w, $h, $txt, $border = 0, $align = 'J', $fill = false)
    {
        // Output text with automatic or explicit line breaks
        $cw = &$this->currentfont['cw'];
        if ($w == 0) {
            $w = $this->w - $this->rMargin - $this->x;
        }
        $wmax = ($w - 2 * $this->cMargin) * 1000 / $this->fontSize;
        $s = str_replace("\r", '', $txt);
        $nb = strlen($s);
        if ($nb > 0 && $s[$nb - 1] == "\n") {
            $nb--;
        }
        $b = 0;
        if ($border) {
            if ($border == 1) {
                $border = 'LTRB';
                $b = 'LRT';
                $b2 = 'LR';
            } else {
                $b2 = '';
                if (strpos($border, 'L') !== false)
                    $b2 .= 'L';
                if (strpos($border, 'R') !== false)
                    $b2 .= 'R';
                $b = (strpos($border, 'T') !== false) ? $b2 . 'T' : $b2;
            }
        }
        $sep = -1;
        $i = 0;
        $j = 0;
        $l = 0;
        $ns = 0;
        $nl = 1;
        while ($i < $nb) {
            // Get next character
            $c = $s[$i];
            if ($c == "\n") {
                // Explicit line break
                if ($this->ws > 0) {
                    $this->ws = 0;
                    $this->out('0 Tw');
                }
                $this->cell($w, $h, substr($s, $j, $i - $j), $b, 2, $align, $fill);
                $i++;
                $sep = -1;
                $j = $i;
                $l = 0;
                $ns = 0;
                $nl++;
                if ($border && $nl == 2) {
                    $b = $b2;
                }
                continue;
            }
            if ($c == ' ') {
                $sep = $i;
                $ls = $l;
                $ns++;
            }
            $l += $cw[$c];
            if ($l > $wmax) {
                // Automatic line break
                if ($sep == -1) {
                    if ($i == $j) {
                        $i++;
                    }
                    if ($this->ws > 0) {
                        $this->ws = 0;
                        $this->out('0 Tw');
                    }
                    $this->cell($w, $h, substr($s, $j, $i - $j), $b, 2, $align, $fill);
                } else {
                    if ($align == 'J') {
                        $this->ws = ($ns > 1) ? ($wmax - $ls) / 1000 * $this->fontSize / ($ns - 1) : 0;
                        $this->out(sprintf('%.3F Tw', $this->ws * $this->k));
                    }
                    $this->cell($w, $h, substr($s, $j, $sep - $j), $b, 2, $align, $fill);
                    $i = $sep + 1;
                }
                $sep = -1;
                $j = $i;
                $l = 0;
                $ns = 0;
                $nl++;
                if ($border && $nl == 2) {
                    $b = $b2;
                }
            } else {
                $i++;
            }
        }
        // Last chunk
        if ($this->ws > 0) {
            $this->ws = 0;
            $this->out('0 Tw');
        }
        if ($border && strpos($border, 'B') !== false) {
            $b .= 'B';
        }
        $this->cell($w, $h, substr($s, $j, $i - $j), $b, 2, $align, $fill);
        $this->x = $this->lMargin;
    }

    public function write($h, $txt, $link = '')
    {
        // Output text in flowing mode
        $cw = &$this->currentfont['cw'];
        $w = $this->w - $this->rMargin - $this->x;
        $wmax = ($w - 2 * $this->cMargin) * 1000 / $this->fontSize;
        $s = str_replace("\r", '', $txt);
        $nb = strlen($s);
        $sep = -1;
        $i = 0;
        $j = 0;
        $l = 0;
        $nl = 1;
        while ($i < $nb) {
            // Get next character
            $c = $s[$i];
            if ($c == "\n") {
                // Explicit line break
                $this->cell($w, $h, substr($s, $j, $i - $j), 0, 2, '', 0, $link);
                $i++;
                $sep = -1;
                $j = $i;
                $l = 0;
                if ($nl == 1) {
                    $this->x = $this->lMargin;
                    $w = $this->w - $this->rMargin - $this->x;
                    $wmax = ($w - 2 * $this->cMargin) * 1000 / $this->fontSize;
                }
                $nl++;
                continue;
            }
            if ($c == ' ') {
                $sep = $i;
            }
            $l += $cw[$c];
            if ($l > $wmax) {
                // Automatic line break
                if ($sep == -1) {
                    if ($this->x > $this->lMargin) {
                        // Move to next line
                        $this->x = $this->lMargin;
                        $this->y += $h;
                        $w = $this->w - $this->rMargin - $this->x;
                        $wmax = ($w - 2 * $this->cMargin) * 1000 / $this->fontSize;
                        $i++;
                        $nl++;
                        continue;
                    }
                    if ($i == $j) {
                        $i++;
                    }
                    $this->cell($w, $h, substr($s, $j, $i - $j), 0, 2, '', 0, $link);
                } else {
                    $this->cell($w, $h, substr($s, $j, $sep - $j), 0, 2, '', 0, $link);
                    $i = $sep + 1;
                }
                $sep = -1;
                $j = $i;
                $l = 0;
                if ($nl == 1) {
                    $this->x = $this->lMargin;
                    $w = $this->w - $this->rMargin - $this->x;
                    $wmax = ($w - 2 * $this->cMargin) * 1000 / $this->fontSize;
                }
                $nl++;
            } else {
                $i++;
            }
        }
        // Last chunk
        if ($i != $j) {
            $this->cell($l / 1000 * $this->fontSize, $h, substr($s, $j), 0, 0, '', 0, $link);
        }
    }

    public function ln($h = null)
    {
        // Line feed; default value is last cell height
        $this->x = $this->lMargin;
        if ($h === null) {
            $this->y += $this->lastH;
        } else {
            $this->y += $h;
        }
    }

    public function image($file, $x = null, $y = null, $w = 0, $h = 0, $type = '', $link = '')
    {
        // Put an image on the page
        if (!isset($this->images[$file])) {
            // First use of this image, get info
            if ($type == '') {
                $pos = strrpos($file, '.');
                if (!$pos) {
                    $this->error('Image file has no extension and no type was specified: ' . $file);
                }
                $type = substr($file, $pos + 1);
            }
            $type = strtolower($type);
            if ($type == 'jpeg') {
                $type = 'jpg';
            }
            $mtd = 'parse' . $type;
            if (!method_exists($this, $mtd)) {
                $this->error('Unsupported image type: ' . $type);
            }
            $info = $this->$mtd($file);
            $info['i'] = count($this->images) + 1;
            $this->images[$file] = $info;
        } else {
            $info = $this->images[$file];
        }
        // Automatic width and height calculation if needed
        if ($w == 0 && $h == 0) {
            // Put image at 96 dpi
            $w = -96;
            $h = -96;
        }
        if ($w < 0) {
            $w = -$info['w'] * 72 / $w / $this->k;
        }
        if ($h < 0) {
            $h = -$info['h'] * 72 / $h / $this->k;
        }
        if ($w == 0) {
            $w = $h * $info['w'] / $info['h'];
        }
        if ($h == 0) {
            $h = $w * $info['h'] / $info['w'];
        }

        // Flowing mode
        if ($y === null) {
            if ($this->y + $h > $this->pageBreakTrigger && !$this->inHeader && !$this->inFooter && $this->acceptPageBreak()) {
                // Automatic page break
                $x2 = $this->x;
                $this->addPage($this->curOrientation, $this->curPageSize);
                $this->x = $x2;
            }
            $y = $this->y;
            $this->y += $h;
        }

        if ($x === null) {
            $x = $this->x;
        }
        $this->out(sprintf('q %.2F 0 0 %.2F %.2F %.2F cm /I%d Do Q', $w * $this->k, $h * $this->k, $x * $this->k, ($this->h - ($y + $h)) * $this->k, $info['i']));
        if ($link) {
            $this->link($x, $y, $w, $h, $link);
        }
    }

    public function getX()
    {
        // Get x position
        return $this->x;
    }

    public function setX($x)
    {
        // Set x position
        if ($x >= 0) {
            $this->x = $x;
        } else {
            $this->x = $this->w + $x;
        }
    }

    public function getY()
    {
        // Get y position
        return $this->y;
    }

    public function setY($y)
    {
        // Set y position and reset x
        $this->x = $this->lMargin;
        if ($y >= 0) {
            $this->y = $y;
        } else {
            $this->y = $this->h + $y;
        }
    }

    public function setXY($x, $y)
    {
        // Set x and y positions
        $this->setY($y);
        $this->setX($x);
    }

    public function output($name = '', $dest = '')
    {
        // Output PDF to some destination
        if ($this->state < 3) {
            $this->close();
        }
        $dest = strtoupper($dest);
        if ($dest == '') {
            if ($name == '') {
                $name = 'doc.pdf';
                $dest = 'I';
            } else {
                $dest = 'F';
            }
        }
        switch ($dest) {
            case 'I':
                // Send to standard output
                $this->checkOutput();
                if (PHP_SAPI != 'cli') {
                    // We send to a browser
                    header('Content-Type: application/pdf');
                    header('Content-Disposition: inline; filename="' . $name . '"');
                    header('Cache-Control: private, max-age=0, must-revalidate');
                    header('Pragma: public');
                }
                echo $this->buffer;
                break;
            case 'D':
                // Download file
                $this->checkOutput();
                header('Content-Type: application/x-download');
                header('Content-Disposition: attachment; filename="' . $name . '"');
                header('Cache-Control: private, max-age=0, must-revalidate');
                header('Pragma: public');
                echo $this->buffer;
                break;
            case 'F':
                // Save to local file
                $f = fopen($name, 'wb');
                if (!$f) {
                    $this->error('Unable to create output file: ' . $name);
                }
                fwrite($f, $this->buffer, strlen($this->buffer));
                fclose($f);
                break;
            case 'S':
                // Return as a string
                return $this->buffer;
            default:
                $this->error('Incorrect output destination: ' . $dest);
        }
        return '';
    }

    protected function doChecks()
    {
        // Check availability of %F
        if (sprintf('%.1F', 1.0) != '1.0') {
            $this->error('This version of PHP is not supported');
        }
        // Check mbstring overloading
        if (ini_get('mbstring.func_overload') & 2) {
            $this->error('mbstring overloading must be disabled');
        }
        // Ensure runtime magic quotes are disabled
        if (get_magic_quotes_runtime()) {
            @set_magic_quotes_runtime(0);
        }
    }

    protected function checkOutput()
    {
        if (PHP_SAPI != 'cli') {
            if (headers_sent($file, $line)) {
                $this->error("Some data has already been output, can't send PDF file (output started at $file:$line)");
            }
        }
        if (ob_get_length()) {
            // The output buffer is not empty
            if (preg_match('/^(\xEF\xBB\xBF)?\s*$/', ob_get_contents())) {
                // It contains only a UTF-8 BOM and/or whitespace, let's clean
                // it
                ob_clean();
            } else {
                $this->error("Some data has already been output, can't send PDF file");
            }
        }
    }

    protected function getPageSize($size)
    {
        if (is_string($size)) {
            $size = strtolower($size);
            if (!isset($this->stdPageSizes[$size])) {
                $this->error('Unknown page size: ' . $size);
            }
            $a = $this->stdPageSizes[$size];
            return array(
                $a[0] / $this->k,
                $a[1] / $this->k
            );
        } else {
            if ($size[0] > $size[1]) {
                return array(
                    $size[1],
                    $size[0]
                );
            } else {
                return $size;
            }
        }
    }

    protected function beginPage($orientation, $size)
    {
        $this->page++;
        $this->pages[$this->page] = '';
        $this->state = 2;
        $this->x = $this->lMargin;
        $this->y = $this->tMargin;
        $this->fontFamily = '';
        // Check page size and orientation
        if ($orientation == '') {
            $orientation = $this->defOrientation;
        } else {
            $orientation = strtoupper($orientation[0]);
        }
        if ($size == '') {
            $size = $this->defPageSize;
        } else {
            $size = $this->getPageSize($size);
        }
        if ($orientation != $this->curOrientation || $size[0] != $this->curPageSize[0] || $size[1] != $this->curPageSize[1]) {
            // New size or orientation
            if ($orientation == 'P') {
                $this->w = $size[0];
                $this->h = $size[1];
            } else {
                $this->w = $size[1];
                $this->h = $size[0];
            }
            $this->wPt = $this->w * $this->k;
            $this->hPt = $this->h * $this->k;
            $this->pageBreakTrigger = $this->h - $this->bMargin;
            $this->curOrientation = $orientation;
            $this->curPageSize = $size;
        }
        if ($orientation != $this->defOrientation || $size[0] != $this->defPageSize[0] || $size[1] != $this->defPageSize[1]) {
            $this->pageSizes[$this->page] = array(
                $this->wPt,
                $this->hPt
            );
        }
    }

    protected function endPage()
    {
        $this->state = 1;
    }

    protected function loadFont($font)
    {
        // Load a font definition file from the font directory
        include $this->fontPath . $font;
        $a = get_defined_vars();
        if (!isset($a['name'])) {
            $this->error('Could not include font definition file');
        }
        return $a;
    }

    protected function escape($s)
    {
        // Escape special characters in strings
        $s = str_replace('\\', '\\\\', $s);
        $s = str_replace('(', '\\(', $s);
        $s = str_replace(')', '\\)', $s);
        $s = str_replace("\r", '\\r', $s);
        return $s;
    }

    protected function textString($s)
    {
        // Format a text string
        return '(' . $this->escape($s) . ')';
    }

    protected function utf8ToUtf16($s)
    {
        // Convert UTF-8 to UTF-16BE with BOM
        $res = "\xFE\xFF";
        $nb = strlen($s);
        $i = 0;
        while ($i < $nb) {
            $c1 = ord($s[$i++]);
            if ($c1 >= 224) {
                // 3-byte character
                $c2 = ord($s[$i++]);
                $c3 = ord($s[$i++]);
                $res .= chr((($c1 & 0x0F) << 4) + (($c2 & 0x3C) >> 2));
                $res .= chr((($c2 & 0x03) << 6) + ($c3 & 0x3F));
            } elseif ($c1 >= 192) {
                // 2-byte character
                $c2 = ord($s[$i++]);
                $res .= chr(($c1 & 0x1C) >> 2);
                $res .= chr((($c1 & 0x03) << 6) + ($c2 & 0x3F));
            } else {
                // Single-byte character
                $res .= "\0" . chr($c1);
            }
        }
        return $res;
    }

    protected function doUnderline($x, $y, $txt)
    {
        // Underline text
        $up = $this->currentfont['up'];
        $ut = $this->currentfont['ut'];
        $w = $this->getStringWidth($txt) + $this->ws * substr_count($txt, ' ');
        return sprintf('%.2F %.2F %.2F %.2F re f', $x * $this->k, ($this->h - ($y - $up / 1000 * $this->fontSize)) * $this->k, $w * $this->k, -$ut / 1000 * $this->fontSizePt);
    }

    protected function parseJpg($file)
    {
        // Extract info from a JPEG file
        $a = getimagesize($file);
        if (!$a) {
            $this->error('Missing or incorrect image file: ' . $file);
        }
        if ($a[2] != 2) {
            $this->error('Not a JPEG file: ' . $file);
        }
        if (!isset($a['channels']) || $a['channels'] == 3) {
            $colspace = 'DeviceRGB';
        } elseif ($a['channels'] == 4) {
            $colspace = 'DeviceCMYK';
        } else {
            $colspace = 'DeviceGray';
        }
        $bpc = isset($a['bits']) ? $a['bits'] : 8;
        $data = file_get_contents($file);
        return array(
            'w' => $a[0],
            'h' => $a[1],
            'cs' => $colspace,
            'bpc' => $bpc,
            'f' => 'DCTDecode',
            'data' => $data
        );
    }

    protected function parsePng($file)
    {
        // Extract info from a PNG file
        $f = fopen($file, 'rb');
        if (!$f) {
            $this->error('Can\'t open image file: ' . $file);
        }
        $info = $this->parsePngStream($f, $file);
        fclose($f);
        return $info;
    }

    protected function parsePngStream($f, $file)
    {
        // Check signature
        if ($this->readStream($f, 8) != chr(137) . 'PNG' . chr(13) . chr(10) . chr(26) . chr(10)) {
            $this->error('Not a PNG file: ' . $file);
        }
        // Read header chunk
        $this->readStream($f, 4);
        if ($this->readStream($f, 4) != 'IHDR') {
            $this->error('Incorrect PNG file: ' . $file);
        }
        $w = $this->readInt($f);
        $h = $this->readInt($f);
        $bpc = ord($this->readStream($f, 1));
        if ($bpc > 8) {
            $this->error('16-bit depth not supported: ' . $file);
        }
        $ct = ord($this->readStream($f, 1));
        if ($ct == 0 || $ct == 4) {
            $colspace = 'DeviceGray';
        } elseif ($ct == 2 || $ct == 6) {
            $colspace = 'DeviceRGB';
        } elseif ($ct == 3) {
            $colspace = 'Indexed';
        } else {
            $this->error('Unknown color type: ' . $file);
        }
        if (ord($this->readStream($f, 1)) != 0) {
            $this->error('Unknown compression method: ' . $file);
        }
        if (ord($this->readStream($f, 1)) != 0) {
            $this->error('Unknown filter method: ' . $file);
        }
        if (ord($this->readStream($f, 1)) != 0) {
            $this->error('Interlacing not supported: ' . $file);
        }
        $this->readStream($f, 4);
        $dp = '/Predictor 15 /Colors ' . ($colspace == 'DeviceRGB' ? 3 : 1) . ' /BitsPerComponent ' . $bpc . ' /Columns ' . $w;

        // Scan chunks looking for palette, transparency and image data
        $pal = '';
        $trns = '';
        $data = '';
        do {
            $n = $this->readInt($f);
            $type = $this->readStream($f, 4);
            if ($type == 'PLTE') {
                // Read palette
                $pal = $this->readStream($f, $n);
                $this->readStream($f, 4);
            } elseif ($type == 'tRNS') {
                // Read transparency info
                $t = $this->readStream($f, $n);
                if ($ct == 0) {
                    $trns = array(
                        ord(substr($t, 1, 1))
                    );
                } elseif ($ct == 2) {
                    $trns = array(
                        ord(substr($t, 1, 1)),
                        ord(substr($t, 3, 1)),
                        ord(substr($t, 5, 1))
                    );
                } else {
                    $pos = strpos($t, chr(0));
                    if ($pos !== false) {
                        $trns = array(
                            $pos
                        );
                    }
                }
                $this->readStream($f, 4);
            } elseif ($type == 'IDAT') {
                // Read image data block
                $data .= $this->readStream($f, $n);
                $this->readStream($f, 4);
            } elseif ($type == 'IEND') {
                break;
            } else {
                $this->readStream($f, $n + 4);
            }
        } while ($n);

        if ($colspace == 'Indexed' && empty($pal)) {
            $this->error('Missing palette in ' . $file);
        }
        $info = array(
            'w' => $w,
            'h' => $h,
            'cs' => $colspace,
            'bpc' => $bpc,
            'f' => 'FlateDecode',
            'dp' => $dp,
            'pal' => $pal,
            'trns' => $trns
        );
        if ($ct >= 4) {
            // Extract alpha channel
            if (!function_exists('gzuncompress')) {
                $this->error('Zlib not available, can\'t handle alpha channel: ' . $file);
            }
            $data = gzuncompress($data);
            $color = '';
            $alpha = '';
            if ($ct == 4) {
                // Gray image
                $len = 2 * $w;
                for ($i = 0;$i < $h;$i++) {
                    $pos = (1 + $len) * $i;
                    $color .= $data[$pos];
                    $alpha .= $data[$pos];
                    $line = substr($data, $pos + 1, $len);
                    $color .= preg_replace('/(.)./s', '$1', $line);
                    $alpha .= preg_replace('/.(.)/s', '$1', $line);
                }
            } else {
                // RGB image
                $len = 4 * $w;
                for ($i = 0;$i < $h;$i++) {
                    $pos = (1 + $len) * $i;
                    $color .= $data[$pos];
                    $alpha .= $data[$pos];
                    $line = substr($data, $pos + 1, $len);
                    $color .= preg_replace('/(.{3})./s', '$1', $line);
                    $alpha .= preg_replace('/.{3}(.)/s', '$1', $line);
                }
            }
            unset($data);
            $data = gzcompress($color);
            $info['smask'] = gzcompress($alpha);
            if ($this->pdfVersion < '1.4') {
                $this->pdfVersion = '1.4';
            }
        }
        $info['data'] = $data;
        return $info;
    }

    protected function readStream($f, $n)
    {
        // Read n bytes from stream
        $res = '';
        while ($n > 0 && !feof($f)) {
            $s = fread($f, $n);
            if ($s === false) {
                $this->error('Error while reading stream');
            }
            $n -= strlen($s);
            $res .= $s;
        }
        if ($n > 0) {
            $this->error('Unexpected end of stream');
        }
        return $res;
    }

    protected function readInt($f)
    {
        // Read a 4-byte integer from stream
        $a = unpack('Ni', $this->readStream($f, 4));
        return $a['i'];
    }

    protected function parseGif($file)
    {
        // Extract info from a GIF file (via PNG conversion)
        if (!function_exists('imagepng')) {
            $this->error('GD extension is required for GIF support');
        }
        if (!function_exists('imagecreatefromgif')) {
            $this->error('GD has no GIF read support');
        }
        $im = imagecreatefromgif($file);
        if (!$im) {
            $this->error('Missing or incorrect image file: ' . $file);
        }
        imageinterlace($im, 0);
        $f = @fopen('php://temp', 'rb+');
        if ($f) {
            // Perform conversion in memory
            ob_start();
            imagepng($im);
            $data = ob_get_clean();
            imagedestroy($im);
            fwrite($f, $data);
            rewind($f);
            $info = $this->parsePngStream($f, $file);
            fclose($f);
        } else {
            // Use temporary file
            $tmp = tempnam('.', 'gif');
            if (!$tmp) {
                $this->error('Unable to create a temporary file');
            }
            if (!imagepng($im, $tmp)) {
                $this->error('Error while saving to temporary file');
            }
            imagedestroy($im);
            $info = $this->parsePng($tmp);
            unlink($tmp);
        }
        return $info;
    }

    protected function newObj()
    {
        // Begin a new object
        $this->n++;
        $this->offsets[$this->n] = strlen($this->buffer);
        $this->out($this->n . ' 0 obj');
    }

    protected function putStream($s)
    {
        $this->out('stream');
        $this->out($s);
        $this->out('endstream');
    }

    protected function out($s)
    {
        // Add a line to the document
        if ($this->state == 2) {
            $this->pages[$this->page] .= $s . "\n";
        } else {
            $this->buffer .= $s . "\n";
        }
    }

    protected function putPages()
    {
        $nb = $this->page;
        if (!empty($this->aliasNbPages)) {
            // Replace number of pages
            for ($n = 1;$n <= $nb;$n++) {
                $this->pages[$n] = str_replace($this->aliasNbPages, $nb, $this->pages[$n]);
            }
        }
        if ($this->defOrientation == 'P') {
            $wPt = $this->defPageSize[0] * $this->k;
            $hPt = $this->defPageSize[1] * $this->k;
        } else {
            $wPt = $this->defPageSize[1] * $this->k;
            $hPt = $this->defPageSize[0] * $this->k;
        }
        $filter = ($this->compress) ? '/Filter /FlateDecode ' : '';
        for ($n = 1;$n <= $nb;$n++) {
            // Page
            $this->newObj();
            $this->out('<</Type /Page');
            $this->out('/Parent 1 0 R');
            if (isset($this->pageSizes[$n])) {
                $this->out(sprintf('/MediaBox [0 0 %.2F %.2F]', $this->pageSizes[$n][0], $this->pageSizes[$n][1]));
            }
            $this->out('/Resources 2 0 R');
            if (isset($this->pageLinks[$n])) {
                // Links
                $annots = '/Annots [';
                foreach ($this->pageLinks[$n] as $pl) {
                    $rect = sprintf('%.2F %.2F %.2F %.2F', $pl[0], $pl[1], $pl[0] + $pl[2], $pl[1] - $pl[3]);
                    $annots .= '<</Type /Annot /Subtype /Link /Rect [' . $rect . '] /Border [0 0 0] ';
                    if (is_string($pl[4])) {
                        $annots .= '/A <</S /URI /URI ' . $this->textString($pl[4]) . '>>>>';
                    } else {
                        $l = $this->links[$pl[4]];
                        $h = isset($this->pageSizes[$l[0]]) ? $this->pageSizes[$l[0]][1] : $hPt;
                        $annots .= sprintf('/Dest [%d 0 R /XYZ 0 %.2F null]>>', 1 + 2 * $l[0], $h - $l[1] * $this->k);
                    }
                }
                $this->out($annots . ']');
            }
            if ($this->pdfVersion > '1.3') {
                $this->out('/Group <</Type /Group /S /Transparency /CS /DeviceRGB>>');
            }
            $this->out('/Contents ' . ($this->n + 1) . ' 0 R>>');
            $this->out('endobj');
            // Page content
            $p = ($this->compress) ? gzcompress($this->pages[$n]) : $this->pages[$n];
            $this->newObj();
            $this->out('<<' . $filter . '/Length ' . strlen($p) . '>>');
            $this->putStream($p);
            $this->out('endobj');
        }
        // Pages root
        $this->offsets[1] = strlen($this->buffer);
        $this->out('1 0 obj');
        $this->out('<</Type /Pages');
        $kids = '/Kids [';
        for ($i = 0;$i < $nb;$i++) {
            $kids .= (3 + 2 * $i) . ' 0 R ';
        }
        $this->out($kids . ']');
        $this->out('/Count ' . $nb);
        $this->out(sprintf('/MediaBox [0 0 %.2F %.2F]', $wPt, $hPt));
        $this->out('>>');
        $this->out('endobj');
    }

    protected function putFonts()
    {
        $nf = $this->n;
        foreach ($this->diffs as $diff) {
            // Encodings
            $this->newObj();
            $this->out('<</Type /Encoding /BaseEncoding /WinAnsiEncoding /Differences [' . $diff . ']>>');
            $this->out('endobj');
        }
        foreach ($this->fontFiles as $file => $info) {
            // Font file embedding
            $this->newObj();
            $this->fontFiles[$file]['n'] = $this->n;
            $font = file_get_contents($this->fontPath . $file, true);
            if (!$font) {
                $this->error('Font file not found: ' . $file);
            }
            $compressed = (substr($file, -2) == '.z');
            if (!$compressed && isset($info['length2'])) {
                $font = substr($font, 6, $info['length1']) . substr($font, 6 + $info['length1'] + 6, $info['length2']);
            }
            $this->out('<</Length ' . strlen($font));
            if ($compressed) {
                $this->out('/Filter /FlateDecode');
            }
            $this->out('/Length1 ' . $info['length1']);
            if (isset($info['length2'])) {
                $this->out('/Length2 ' . $info['length2'] . ' /Length3 0');
            }
            $this->out('>>');
            $this->putStream($font);
            $this->out('endobj');
        }
        foreach ($this->fonts as $k => $font) {
            // Font objects
            $this->fonts[$k]['n'] = $this->n + 1;
            $type = $font['type'];
            $name = $font['name'];
            if ($type == 'Core') {
                // Core font
                $this->newObj();
                $this->out('<</Type /Font');
                $this->out('/BaseFont /' . $name);
                $this->out('/Subtype /Type1');
                if ($name != 'Symbol' && $name != 'ZapfDingbats') {
                    $this->out('/Encoding /WinAnsiEncoding');
                }
                $this->out('>>');
                $this->out('endobj');
            } elseif ($type == 'Type1' || $type == 'TrueType') {
                // Additional Type1 or TrueType/OpenType font
                $this->newObj();
                $this->out('<</Type /Font');
                $this->out('/BaseFont /' . $name);
                $this->out('/Subtype /' . $type);
                $this->out('/FirstChar 32 /LastChar 255');
                $this->out('/Widths ' . ($this->n + 1) . ' 0 R');
                $this->out('/FontDescriptor ' . ($this->n + 2) . ' 0 R');
                if (isset($font['diffn'])) {
                    $this->out('/Encoding ' . ($nf + $font['diffn']) . ' 0 R');
                } else {
                    $this->out('/Encoding /WinAnsiEncoding');
                }
                $this->out('>>');
                $this->out('endobj');
                // Widths
                $this->newObj();
                $cw = &$font['cw'];
                $s = '[';
                for ($i = 32;$i <= 255;$i++) {
                    $s .= $cw[chr($i)] . ' ';
                }
                $this->out($s . ']');
                $this->out('endobj');
                // Descriptor
                $this->newObj();
                $s = '<</Type /FontDescriptor /FontName /' . $name;
                foreach ($font['desc'] as $k => $v) {
                    $s .= ' /' . $k . ' ' . $v;
                }
                if (!empty($font['file'])) {
                    $s .= ' /FontFile' . ($type == 'Type1' ? '' : '2') . ' ' . $this->fontFiles[$font['file']]['n'] . ' 0 R';
                }
                $this->out($s . '>>');
                $this->out('endobj');
            } else {
                // Allow for additional types
                $mtd = 'put' . strtolower($type);
                if (!method_exists($this, $mtd)) {
                    $this->error('Unsupported font type: ' . $type);
                }
                $this->$mtd($font);
            }
        }
    }

    protected function putImages()
    {
        foreach (array_keys($this->images) as $file) {
            $this->putImage($this->images[$file]);
            unset($this->images[$file]['data']);
            unset($this->images[$file]['smask']);
        }
    }

    protected function putImage(&$info)
    {
        $this->newObj();
        $info['n'] = $this->n;
        $this->out('<</Type /XObject');
        $this->out('/Subtype /Image');
        $this->out('/Width ' . $info['w']);
        $this->out('/Height ' . $info['h']);
        if ($info['cs'] == 'Indexed') {
            $this->out('/ColorSpace [/Indexed /DeviceRGB ' . (strlen($info['pal']) / 3 - 1) . ' ' . ($this->n + 1) . ' 0 R]');
        } else {
            $this->out('/ColorSpace /' . $info['cs']);
            if ($info['cs'] == 'DeviceCMYK') {
                $this->out('/Decode [1 0 1 0 1 0 1 0]');
            }
        }
        $this->out('/BitsPerComponent ' . $info['bpc']);
        if (isset($info['f'])) {
            $this->out('/Filter /' . $info['f']);
        }
        if (isset($info['dp'])) {
            $this->out('/DecodeParms <<' . $info['dp'] . '>>');
        }
        if (isset($info['trns']) && is_array($info['trns'])) {
            $trns = '';
            for ($i = 0;$i < count($info['trns']);$i++) {
                $trns .= $info['trns'][$i] . ' ' . $info['trns'][$i] . ' ';
            }
            $this->out('/Mask [' . $trns . ']');
        }
        if (isset($info['smask'])) {
            $this->out('/SMask ' . ($this->n + 1) . ' 0 R');
        }
        $this->out('/Length ' . strlen($info['data']) . '>>');
        $this->putStream($info['data']);
        $this->out('endobj');
        // Soft mask
        if (isset($info['smask'])) {
            $dp = '/Predictor 15 /Colors 1 /BitsPerComponent 8 /Columns ' . $info['w'];
            $smask = array(
                'w' => $info['w'],
                'h' => $info['h'],
                'cs' => 'DeviceGray',
                'bpc' => 8,
                'f' => $info['f'],
                'dp' => $dp,
                'data' => $info['smask']
            );
            $this->putImage($smask);
        }
        // Palette
        if ($info['cs'] == 'Indexed') {
            $filter = ($this->compress) ? '/Filter /FlateDecode ' : '';
            $pal = ($this->compress) ? gzcompress($info['pal']) : $info['pal'];
            $this->newObj();
            $this->out('<<' . $filter . '/Length ' . strlen($pal) . '>>');
            $this->putStream($pal);
            $this->out('endobj');
        }
    }

    protected function putXObjectDict()
    {
        foreach ($this->images as $image) {
            $this->out('/I' . $image['i'] . ' ' . $image['n'] . ' 0 R');
        }
    }

    protected function putResourceDict()
    {
        $this->out('/ProcSet [/PDF /Text /ImageB /ImageC /ImageI]');
        $this->out('/Font <<');
        foreach ($this->fonts as $font) {
            $this->out('/F' . $font['i'] . ' ' . $font['n'] . ' 0 R');
        }
        $this->out('>>');
        $this->out('/XObject <<');
        $this->putXObjectDict();
        $this->out('>>');
    }

    protected function putResources()
    {
        $this->putFonts();
        $this->putImages();
        // Resource dictionary
        $this->offsets[2] = strlen($this->buffer);
        $this->out('2 0 obj');
        $this->out('<<');
        $this->putResourceDict();
        $this->out('>>');
        $this->out('endobj');
    }

    protected function putInfo()
    {
        $this->out('/Producer ' . $this->textString('FPDF ' . FPDF_VERSION));
        if (!empty($this->title)) {
            $this->out('/Title ' . $this->textString($this->title));
        }
        if (!empty($this->subject)) {
            $this->out('/Subject ' . $this->textString($this->subject));
        }
        if (!empty($this->author)) {
            $this->out('/Author ' . $this->textString($this->author));
        }
        if (!empty($this->keywords)) {
            $this->out('/Keywords ' . $this->textString($this->keywords));
        }
        if (!empty($this->creator)) {
            $this->out('/Creator ' . $this->textString($this->creator));
        }
        $this->out('/CreationDate ' . $this->textString('D:' . @date('YmdHis')));
    }

    protected function putCatalog()
    {
        $this->out('/Type /Catalog');
        $this->out('/Pages 1 0 R');
        if ($this->zoomMode == 'fullpage') {
            $this->out('/OpenAction [3 0 R /Fit]');
        } elseif ($this->zoomMode == 'fullwidth') {
            $this->out('/OpenAction [3 0 R /FitH null]');
        } elseif ($this->zoomMode == 'real') {
            $this->out('/OpenAction [3 0 R /XYZ null null 1]');
        } elseif (!is_string($this->zoomMode)) {
            $this->out('/OpenAction [3 0 R /XYZ null null ' . sprintf('%.2F', $this->zoomMode / 100) . ']');
        }
        if ($this->layoutMode == 'single') {
            $this->out('/PageLayout /SinglePage');
        } elseif ($this->layoutMode == 'continuous') {
            $this->out('/PageLayout /OneColumn');
        } elseif ($this->layoutMode == 'two') {
            $this->out('/PageLayout /TwoColumnLeft');
        }
    }

    protected function putHeader()
    {
        $this->out('%PDF-' . $this->pdfVersion);
    }

    protected function putTrailer()
    {
        $this->out('/Size ' . ($this->n + 1));
        $this->out('/Root ' . $this->n . ' 0 R');
        $this->out('/Info ' . ($this->n - 1) . ' 0 R');
    }

    protected function endDoc()
    {
        $this->putHeader();
        $this->putPages();
        $this->putResources();
        // Info
        $this->newObj();
        $this->out('<<');
        $this->putInfo();
        $this->out('>>');
        $this->out('endobj');
        // Catalog
        $this->newObj();
        $this->out('<<');
        $this->putCatalog();
        $this->out('>>');
        $this->out('endobj');
        // Cross-ref
        $o = strlen($this->buffer);
        $this->out('xref');
        $this->out('0 ' . ($this->n + 1));
        $this->out('0000000000 65535 f ');
        for ($i = 1;$i <= $this->n;$i++) {
            $this->out(sprintf('%010d 00000 n ', $this->offsets[$i]));
        }
        // Trailer
        $this->out('trailer');
        $this->out('<<');
        $this->putTrailer();
        $this->out('>>');
        $this->out('startxref');
        $this->out($o);
        $this->out('%%EOF');
        $this->state = 3;
    }
    // End of class
}

// Handle special IE contype request
if (isset($_SERVER['HTTP_USER_AGENT']) && $_SERVER['HTTP_USER_AGENT'] == 'contype') {
    header('Content-Type: application/pdf');
    exit();
}
