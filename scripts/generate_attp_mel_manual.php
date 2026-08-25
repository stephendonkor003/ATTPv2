<?php

declare(strict_types=1);

final class WordManual
{
    private array $body = [];

    private static function e(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    public function paragraph(string $text = '', string $style = 'Normal', array $options = []): void
    {
        $alignment = isset($options['align']) ? '<w:jc w:val="'.self::e($options['align']).'"/>' : '';
        $before = isset($options['before']) ? '<w:spacing w:before="'.(int) $options['before'].'"/>' : '';
        $after = isset($options['after']) ? '<w:spacing w:after="'.(int) $options['after'].'"/>' : '';
        $keep = ! empty($options['keep']) ? '<w:keepNext/>' : '';
        $bold = ! empty($options['bold']) ? '<w:b/>' : '';
        $italic = ! empty($options['italic']) ? '<w:i/>' : '';
        $color = isset($options['color']) ? '<w:color w:val="'.self::e($options['color']).'"/>' : '';
        $size = isset($options['size']) ? '<w:sz w:val="'.(int) $options['size'].'"/><w:szCs w:val="'.(int) $options['size'].'"/>' : '';
        $break = ! empty($options['page_break_before']) ? '<w:pageBreakBefore/>' : '';
        $this->body[] = '<w:p><w:pPr><w:pStyle w:val="'.self::e($style).'"/>'.$alignment.$before.$after.$keep.$break.'</w:pPr><w:r><w:rPr>'.$bold.$italic.$color.$size.'</w:rPr><w:t xml:space="preserve">'.self::e($text).'</w:t></w:r></w:p>';
    }

    public function rich(array $runs, string $style = 'Normal', array $options = []): void
    {
        $alignment = isset($options['align']) ? '<w:jc w:val="'.self::e($options['align']).'"/>' : '';
        $xml = '<w:p><w:pPr><w:pStyle w:val="'.self::e($style).'"/>'.$alignment.'</w:pPr>';
        foreach ($runs as $run) {
            $props = (! empty($run['bold']) ? '<w:b/>' : '')
                .(! empty($run['italic']) ? '<w:i/>' : '')
                .(isset($run['color']) ? '<w:color w:val="'.self::e($run['color']).'"/>' : '');
            $xml .= '<w:r><w:rPr>'.$props.'</w:rPr><w:t xml:space="preserve">'.self::e((string) $run['text']).'</w:t></w:r>';
        }
        $this->body[] = $xml.'</w:p>';
    }

    public function heading(string $text, int $level = 1): void
    {
        $this->paragraph($text, 'Heading'.$level, ['keep' => true]);
    }

    public function bullet(string $text, int $level = 0): void
    {
        $this->body[] = '<w:p><w:pPr><w:pStyle w:val="ListBullet"/><w:numPr><w:ilvl w:val="'.max(0, min(2, $level)).'"/><w:numId w:val="1"/></w:numPr></w:pPr><w:r><w:t xml:space="preserve">'.self::e($text).'</w:t></w:r></w:p>';
    }

    public function numbered(string $text, int $level = 0): void
    {
        $this->body[] = '<w:p><w:pPr><w:pStyle w:val="ListNumber"/><w:numPr><w:ilvl w:val="'.max(0, min(2, $level)).'"/><w:numId w:val="2"/></w:numPr></w:pPr><w:r><w:t xml:space="preserve">'.self::e($text).'</w:t></w:r></w:p>';
    }

    public function pageBreak(): void
    {
        $this->body[] = '<w:p><w:r><w:br w:type="page"/></w:r></w:p>';
    }

    public function callout(string $title, string $text, string $fill = 'EAF5F0'): void
    {
        $this->table([[$title.' — '.$text]], [9360], true, $fill);
    }

    public function table(array $rows, array $widths = [], bool $firstRowHeader = true, string $headerFill = 'DCEFE7'): void
    {
        if ($rows === []) {
            return;
        }
        $columnCount = max(array_map('count', $rows));
        if ($widths === []) {
            $widths = array_fill(0, $columnCount, (int) floor(9360 / $columnCount));
        }
        $xml = '<w:tbl><w:tblPr><w:tblW w:w="9360" w:type="dxa"/><w:tblBorders><w:top w:val="single" w:sz="4" w:color="AFC6BC"/><w:left w:val="single" w:sz="4" w:color="AFC6BC"/><w:bottom w:val="single" w:sz="4" w:color="AFC6BC"/><w:right w:val="single" w:sz="4" w:color="AFC6BC"/><w:insideH w:val="single" w:sz="3" w:color="D5E2DC"/><w:insideV w:val="single" w:sz="3" w:color="D5E2DC"/></w:tblBorders><w:tblCellMar><w:top w:w="80" w:type="dxa"/><w:left w:w="100" w:type="dxa"/><w:bottom w:w="80" w:type="dxa"/><w:right w:w="100" w:type="dxa"/></w:tblCellMar></w:tblPr>';
        foreach ($rows as $rowIndex => $row) {
            $xml .= '<w:tr>'.($rowIndex === 0 && $firstRowHeader ? '<w:trPr><w:tblHeader/></w:trPr>' : '');
            for ($column = 0; $column < $columnCount; $column++) {
                $value = (string) ($row[$column] ?? '');
                $shade = $rowIndex === 0 && $firstRowHeader ? '<w:shd w:fill="'.self::e($headerFill).'"/>' : '';
                $bold = $rowIndex === 0 && $firstRowHeader ? '<w:b/>' : '';
                $xml .= '<w:tc><w:tcPr><w:tcW w:w="'.(int) ($widths[$column] ?? 2000).'" w:type="dxa"/>'.$shade.'<w:vAlign w:val="top"/></w:tcPr><w:p><w:r><w:rPr>'.$bold.'</w:rPr><w:t xml:space="preserve">'.self::e($value).'</w:t></w:r></w:p></w:tc>';
            }
            $xml .= '</w:tr>';
        }
        $this->body[] = $xml.'</w:tbl><w:p/>';
    }

    public function toc(): void
    {
        $this->body[] = '<w:p><w:pPr><w:pStyle w:val="TOCHeading"/></w:pPr><w:r><w:t>Table of Contents</w:t></w:r></w:p>'
            .'<w:p><w:r><w:fldChar w:fldCharType="begin" w:dirty="true"/></w:r><w:r><w:instrText xml:space="preserve"> TOC \\o "1-3" \\h \\z \\u </w:instrText></w:r><w:r><w:fldChar w:fldCharType="separate"/></w:r><w:r><w:t>Open in Microsoft Word, right-click this line, and select Update Field → Update entire table.</w:t></w:r><w:r><w:fldChar w:fldCharType="end"/></w:r></w:p>';
    }

    public function save(string $path): void
    {
        $directory = dirname($path);
        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new RuntimeException('Unable to create '.$directory);
        }
        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to create '.$path);
        }

        $document = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><w:body>'
            .implode('', $this->body)
            .'<w:sectPr><w:headerReference w:type="default" r:id="rId3"/><w:footerReference w:type="default" r:id="rId4"/><w:pgSz w:w="12240" w:h="15840"/><w:pgMar w:top="1080" w:right="1080" w:bottom="1080" w:left="1080" w:header="500" w:footer="500"/><w:cols w:space="720"/><w:docGrid w:linePitch="360"/></w:sectPr></w:body></w:document>';

        $zip->addFromString('[Content_Types].xml', $this->contentTypes());
        $zip->addFromString('_rels/.rels', $this->rootRelationships());
        $zip->addFromString('docProps/core.xml', $this->coreProperties());
        $zip->addFromString('docProps/app.xml', $this->appProperties());
        $zip->addFromString('word/document.xml', $document);
        $zip->addFromString('word/styles.xml', $this->styles());
        $zip->addFromString('word/numbering.xml', $this->numbering());
        $zip->addFromString('word/settings.xml', $this->settings());
        $zip->addFromString('word/header1.xml', $this->header());
        $zip->addFromString('word/footer1.xml', $this->footer());
        $zip->addFromString('word/_rels/document.xml.rels', $this->documentRelationships());
        $zip->close();
    }

    private function contentTypes(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/><Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/><Override PartName="/word/numbering.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.numbering+xml"/><Override PartName="/word/settings.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.settings+xml"/><Override PartName="/word/header1.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.header+xml"/><Override PartName="/word/footer1.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.footer+xml"/><Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/><Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/></Types>';
    }

    private function rootRelationships(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/><Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/></Relationships>';
    }

    private function documentRelationships(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/numbering" Target="numbering.xml"/><Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/header" Target="header1.xml"/><Relationship Id="rId4" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/footer" Target="footer1.xml"/><Relationship Id="rId5" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/settings" Target="settings.xml"/></Relationships>';
    }

    private function coreProperties(): string
    {
        $date = gmdate('Y-m-d\TH:i:s\Z');

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:dcmitype="http://purl.org/dc/dcmitype/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"><dc:title>ATTP MEL Platform Complete User Manual</dc:title><dc:subject>Unified Indicator Performance Tracking, Evidence, Verification and Consolidation</dc:subject><dc:creator>ATTP Secretariat</dc:creator><cp:keywords>ATTP; MEL; M&amp;E; think tanks; indicator tracking</cp:keywords><dc:description>Operational manual for Secretariat M&amp;E Officers, focal persons, reviewers, approvers and think tanks.</dc:description><cp:lastModifiedBy>ATTP Platform</cp:lastModifiedBy><dcterms:created xsi:type="dcterms:W3CDTF">'.$date.'</dcterms:created><dcterms:modified xsi:type="dcterms:W3CDTF">'.$date.'</dcterms:modified></cp:coreProperties>';
    }

    private function appProperties(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes"><Application>Microsoft Office Word</Application><DocSecurity>0</DocSecurity><ScaleCrop>false</ScaleCrop><Company>ATTP</Company><AppVersion>16.0000</AppVersion></Properties>';
    }

    private function settings(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:settings xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:updateFields w:val="true"/><w:defaultTabStop w:val="720"/><w:compat><w:compatSetting w:name="compatibilityMode" w:uri="http://schemas.microsoft.com/office/word" w:val="15"/></w:compat></w:settings>';
    }

    private function header(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:hdr xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:p><w:pPr><w:pBdr><w:bottom w:val="single" w:sz="5" w:color="0B6D50"/></w:pBdr></w:pPr><w:r><w:rPr><w:b/><w:color w:val="0B6D50"/><w:sz w:val="18"/></w:rPr><w:t>ATTP MEL Platform — Complete User Manual</w:t></w:r></w:p></w:hdr>';
    }

    private function footer(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:ftr xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:p><w:pPr><w:jc w:val="center"/></w:pPr><w:r><w:rPr><w:color w:val="64748B"/><w:sz w:val="16"/></w:rPr><w:t>Controlled operational guide · Page </w:t></w:r><w:r><w:fldChar w:fldCharType="begin"/></w:r><w:r><w:instrText> PAGE </w:instrText></w:r><w:r><w:fldChar w:fldCharType="separate"/></w:r><w:r><w:t>1</w:t></w:r><w:r><w:fldChar w:fldCharType="end"/></w:r></w:p></w:ftr>';
    }

    private function styles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:docDefaults><w:rPrDefault><w:rPr><w:rFonts w:ascii="Aptos" w:hAnsi="Aptos" w:eastAsia="Aptos"/><w:sz w:val="21"/><w:szCs w:val="21"/><w:lang w:val="en-GB"/></w:rPr></w:rPrDefault><w:pPrDefault><w:pPr><w:spacing w:after="120" w:line="276" w:lineRule="auto"/></w:pPr></w:pPrDefault></w:docDefaults>'
            .$this->style('Normal', 'Normal', 'paragraph', '')
            .$this->style('Title', 'Title', 'paragraph', '<w:pPr><w:jc w:val="center"/><w:spacing w:before="480" w:after="240"/></w:pPr><w:rPr><w:b/><w:color w:val="073F30"/><w:sz w:val="44"/><w:szCs w:val="44"/></w:rPr>')
            .$this->style('Subtitle', 'Subtitle', 'paragraph', '<w:pPr><w:jc w:val="center"/><w:spacing w:after="180"/></w:pPr><w:rPr><w:color w:val="0B6D50"/><w:sz w:val="26"/><w:szCs w:val="26"/></w:rPr>')
            .$this->style('Heading1', 'Heading 1', 'paragraph', '<w:basedOn w:val="Normal"/><w:next w:val="Normal"/><w:qFormat/><w:pPr><w:keepNext/><w:pageBreakBefore/><w:spacing w:before="240" w:after="140"/><w:outlineLvl w:val="0"/></w:pPr><w:rPr><w:b/><w:color w:val="073F30"/><w:sz w:val="32"/><w:szCs w:val="32"/></w:rPr>')
            .$this->style('Heading2', 'Heading 2', 'paragraph', '<w:basedOn w:val="Normal"/><w:next w:val="Normal"/><w:qFormat/><w:pPr><w:keepNext/><w:spacing w:before="240" w:after="100"/><w:outlineLvl w:val="1"/></w:pPr><w:rPr><w:b/><w:color w:val="0B6D50"/><w:sz w:val="26"/><w:szCs w:val="26"/></w:rPr>')
            .$this->style('Heading3', 'Heading 3', 'paragraph', '<w:basedOn w:val="Normal"/><w:next w:val="Normal"/><w:qFormat/><w:pPr><w:keepNext/><w:spacing w:before="180" w:after="80"/><w:outlineLvl w:val="2"/></w:pPr><w:rPr><w:b/><w:color w:val="176B4B"/><w:sz w:val="23"/><w:szCs w:val="23"/></w:rPr>')
            .$this->style('ListBullet', 'List Bullet', 'paragraph', '<w:basedOn w:val="Normal"/><w:pPr><w:ind w:left="720" w:hanging="360"/></w:pPr>')
            .$this->style('ListNumber', 'List Number', 'paragraph', '<w:basedOn w:val="Normal"/><w:pPr><w:ind w:left="720" w:hanging="360"/></w:pPr>')
            .$this->style('TOCHeading', 'TOC Heading', 'paragraph', '<w:basedOn w:val="Heading1"/><w:next w:val="Normal"/><w:qFormat/><w:pPr><w:keepNext/><w:spacing w:after="200"/><w:outlineLvl w:val="9"/></w:pPr><w:rPr><w:b/><w:color w:val="073F30"/><w:sz w:val="32"/></w:rPr>')
            .'</w:styles>';
    }

    private function style(string $id, string $name, string $type, string $content): string
    {
        return '<w:style w:type="'.$type.'" w:styleId="'.$id.'"><w:name w:val="'.$name.'"/>'.$content.'</w:style>';
    }

    private function numbering(): string
    {
        $levels = '';
        for ($i = 0; $i < 3; $i++) {
            $levels .= '<w:lvl w:ilvl="'.$i.'"><w:start w:val="1"/><w:numFmt w:val="bullet"/><w:lvlText w:val="•"/><w:lvlJc w:val="left"/><w:pPr><w:tabs><w:tab w:val="num" w:pos="'.(720 + $i * 360).'"/></w:tabs><w:ind w:left="'.(720 + $i * 360).'" w:hanging="360"/></w:pPr><w:rPr><w:rFonts w:ascii="Symbol" w:hAnsi="Symbol"/></w:rPr></w:lvl>';
        }
        $numbers = '';
        for ($i = 0; $i < 3; $i++) {
            $numbers .= '<w:lvl w:ilvl="'.$i.'"><w:start w:val="1"/><w:numFmt w:val="decimal"/><w:lvlText w:val="%'.($i + 1).'."/><w:lvlJc w:val="left"/><w:pPr><w:tabs><w:tab w:val="num" w:pos="'.(720 + $i * 360).'"/></w:tabs><w:ind w:left="'.(720 + $i * 360).'" w:hanging="360"/></w:pPr></w:lvl>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:numbering xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:abstractNum w:abstractNumId="1"><w:multiLevelType w:val="multilevel"/>'.$levels.'</w:abstractNum><w:abstractNum w:abstractNumId="2"><w:multiLevelType w:val="multilevel"/>'.$numbers.'</w:abstractNum><w:num w:numId="1"><w:abstractNumId w:val="1"/></w:num><w:num w:numId="2"><w:abstractNumId w:val="2"/></w:num></w:numbering>';
    }
}

$doc = new WordManual;
$doc->paragraph('AFRICA THINK TANK PLATFORM', 'Subtitle', ['align' => 'center', 'size' => 26, 'bold' => true]);
$doc->paragraph('ATTP MEL PLATFORM', 'Title');
$doc->paragraph('Complete User Manual', 'Title');
$doc->paragraph('Unified Indicator Performance Tracking, Achievement Reporting, Evidence Management, Verification and Consolidation', 'Subtitle');
$doc->paragraph('For Secretariat M&E Officers, Reviewers, Approvers, Think Tank Focal Persons and Platform Administrators', 'Normal', ['align' => 'center', 'size' => 23, 'bold' => true, 'color' => '334155', 'before' => 240]);
$doc->paragraph('Version 1.1 · 2 August 2026', 'Normal', ['align' => 'center', 'color' => '64748B', 'before' => 300]);
$doc->paragraph('Platform: https://africathinktankplatform.africa', 'Normal', ['align' => 'center', 'color' => '0B6D50']);
$doc->callout('Document control', 'This manual describes the role-based operating procedure for the implemented ATTP MEL module. Keep it with the active M&E Matrix and update it whenever the approved reporting process changes.');
$doc->pageBreak();
$doc->toc();

$doc->heading('1. Purpose, Scope and Intended Users');
$doc->paragraph('This manual explains the complete MEL operating cycle, from platform preparation and indicator configuration through reporting by all 13 think tanks, M&E verification, final approval, consolidation, export and archival. It translates the unified Indicator Performance Tracking workbook into a controlled database workflow.');
$doc->heading('1.1 Who should use this manual', 2);
$doc->table([
    ['User', 'Primary responsibility', 'Main workspaces'],
    ['Secretariat M&E Officer', 'Configure indicators, periods, assignments and disaggregation; verify values and evidence; monitor completeness.', 'Indicator Register, Data Entry, Focal Units, Repository, Reporting Dashboard'],
    ['Think Tank M&E Focal Person', 'Enter organization results, record one or more achievements, disaggregate beneficiaries, upload evidence and submit.', 'Think Tank → M&E Performance Reports'],
    ['Authorized Approver', 'Review a verified report and give independent final approval or return it for correction.', 'Performance Report lifecycle'],
    ['Platform Administrator', 'Deploy migrations, manage accounts/permissions, maintain focal mappings and troubleshoot access.', 'User management and M&E configuration'],
    ['Programme/Management User', 'View approved organization and indicator reports; export governed dossiers and consolidated registers.', 'Think Tank M&E Reports, Indicator Report'],
]);
$doc->heading('1.2 What changed from the Excel tracker', 2);
$doc->bullet('The platform stores an indicator result once per organization and period, then stores every contributing achievement as a separate child record.');
$doc->bullet('Beneficiary disaggregation is stored as unique combined rows—not as independent totals that can be accidentally added twice.');
$doc->bullet('Evidence is uploaded once to the canonical Evidence Repository and linked to reports or achievements.');
$doc->bullet('Indicator numbers/codes are user-defined, editable by authorized users and retained in code history.');
$doc->bullet('Quarterly, semi-annual and annual reporting are explicit period types, rather than being forced into Q1–Q4.');
$doc->bullet('Verification and final approval are separate lifecycle decisions with named actors and timestamps.');
$doc->bullet('Only approved or archived think-tank data enters the consolidated report.');
$doc->callout('Important control', 'Do not reproduce the Excel design as one flat database row. Repeating an indicator for each achievement would repeat its target and actual value and could overstate consolidated performance.');

$doc->heading('2. End-to-End Workflow at a Glance');
$doc->table([
    ['Step', 'Actor', 'Action', 'Result'],
    ['1', 'Administrator / M&E Officer', 'Apply deployment migration and confirm permissions.', 'Unified data model is ready.'],
    ['2', 'M&E Officer', 'Review Focal Unit Register and link existing accounts.', 'Each organization has a reporting focal point.'],
    ['3', 'M&E Officer', 'Upload and activate the controlled M&E Matrix.', 'Current matrix is viewable and versioned.'],
    ['4', 'M&E Officer', 'Configure indicator code, definition, targets, cadence, roll-up and disaggregation.', 'Indicator is report-ready.'],
    ['5', 'M&E Officer', 'Publish a form, open a collection and assign the 13 organizations.', 'Think tanks can create reports.'],
    ['6', 'Think Tank Focal Person', 'Create the correct quarterly, semi-annual or annual draft.', 'Only indicators due in that cadence appear.'],
    ['7', 'Think Tank Focal Person', 'Enter result, achievement details, combined beneficiary rows and evidence.', 'Unified tracker record is complete.'],
    ['8', 'Think Tank Focal Person', 'Complete seven narrative sections, save and submit.', 'Report locks in Submitted status.'],
    ['9', 'M&E Officer', 'Perform data-quality and evidence checks; return or verify.', 'Report becomes Draft or Verified.'],
    ['10', 'Authorized Approver', 'Approve verified report or return it.', 'Report becomes Approved or Draft.'],
    ['11', 'Secretariat', 'View all 13 separately and generate consolidated Excel/PDF.', 'Approved ATTP portfolio result is produced.'],
    ['12', 'Authorized Archivist', 'Archive final report.', 'Read-only historical record is retained.'],
]);

$doc->heading('3. Access, Roles and Security');
$doc->heading('3.1 Account prerequisites', 2);
$doc->numbered('The user account must be active and not blacklisted or disabled.');
$doc->numbered('A think-tank user must be mapped to exactly one active consortium think tank.');
$doc->numbered('The access level must be M&E Officer or Think Tank Admin for report preparation.');
$doc->numbered('The account must have view/manage/submit permissions appropriate to the task.');
$doc->numbered('Reviewers need performance-report review permission; archivists need archive permission.');
$doc->heading('3.2 Separation of duties', 2);
$doc->bullet('The report author cannot verify or approve their own report, except system administrators acting under their explicit override authority.');
$doc->bullet('A non-administrator verifier cannot also provide final approval for the same report.');
$doc->bullet('Think tanks can see and edit only their own organization’s draft reports.');
$doc->bullet('Portfolio-scoped Secretariat users see only assigned portfolios.');
$doc->bullet('Repository files linked to reports, achievements, indicators or matrices cannot be deleted.');
$doc->table([
    ['Lifecycle status', 'Who can change data?', 'Next authorized action'],
    ['Draft', 'Assigned author', 'Save or Submit'],
    ['Submitted', 'Read-only for author', 'M&E Officer Returns or Verifies'],
    ['Verified', 'Read-only', 'Independent reviewer Approves or Returns'],
    ['Approved', 'Read-only', 'Authorized user Archives'],
    ['Archived', 'Nobody', 'View/download only'],
]);

$doc->heading('4. First-Time Secretariat Setup');
$doc->heading('4.1 Review the M&E Focal Unit Register', 2);
$doc->paragraph('Navigate to Monitoring & Evaluation → M&E Focal Unit Register, or /budget/me/focal-units. The supplied workbook creates 18 contact records mapped across 13 organizations.');
$doc->numbered('Confirm the dashboard says 13 / 13 think tanks mapped.');
$doc->numbered('Confirm each focal person’s name and email against the approved register.');
$doc->numbered('Where the platform finds an account with the exact email, select Link M&E account.');
$doc->numbered('Read the confirmation carefully: linking assigns the account to that organization with M&E Officer access.');
$doc->numbered('If no account exists, create it through the authorized user-management process; do not invent or share a password. Return to this page and link it.');
$doc->numbered('Use Edit to correct consortium, short label, mapped organization, primary-contact flag or notes.');
$doc->callout('Expected readiness', 'All 13 organizations should be mapped. Account-linked readiness can be increased as approved accounts are created; it is correct for missing accounts to remain visibly incomplete.');

$doc->heading('4.2 Use the live reporting-readiness audit', 2);
$doc->paragraph('Open Monitoring & Evaluation → Data Entry and Performance Tracking. The Think-tank reporting readiness panel reads the current database and prevents an empty reporting screen from being mistaken for a platform failure. It does not create or alter official programme data.');
$doc->table([
    ['Readiness gate', 'Completion standard'],
    ['Think-tank reporting access', 'Every active organization has an enabled, non-blacklisted Think Tank Admin or M&E Officer account.'],
    ['Controlled M&E Matrix', 'At least one approved matrix version is Active.'],
    ['Report-ready indicators', 'Each reporting indicator has a component, code, baseline, targets, cadence, unit, responsible officer and linked means of verification.'],
    ['Published reporting form', 'The form has sections/questions and is linked to the correct component and indicators.'],
    ['Active reporting period', 'The approved quarterly, semi-annual or annual window is Active.'],
    ['Open collection', 'A published form and active period are joined and every active think tank is assigned.'],
]);
$doc->numbered('Review every red Action required card; the count and explanation come from the current database.');
$doc->numbered('Select the card action to open the exact configuration page that is blocking reporting.');
$doc->numbered('If Focal Units shows Login disabled, an authorized user administrator must confirm the account and select Enable login. Never enable an unverified or departed user merely to improve the readiness percentage.');
$doc->numbered('Return to Data Entry and Performance Tracking after each correction. The assessment recalculates automatically on the next page load.');
$doc->numbered('Open the collection only when all six controls are complete and the reporting dates have been formally approved.');

$doc->heading('4.3 Upload and activate the M&E Matrix', 2);
$doc->paragraph('Navigate to Monitoring & Evaluation → M&E Matrix Manager, or /budget/me/matrices.');
$doc->numbered('Select the portfolio.');
$doc->numbered('Enter a meaningful Document Title, for example “ATTP Unified Indicator Performance Tracking Matrix”.');
$doc->numbered('Enter a stable Matrix Code, for example ATTP-MEL-MATRIX.');
$doc->numbered('Leave Version blank to assign the next version automatically, or enter the approved version number.');
$doc->numbered('Set effective dates and describe exactly what changed.');
$doc->numbered('Upload XLSX, XLS, CSV or PDF (maximum 30 MB).');
$doc->numbered('Review the workbook inspection: format, sheet count, data dimensions, formula cells and validated cells.');
$doc->numbered('Select Activate only after review. Activating a version retires the previous active version with the same code.');
$doc->paragraph('Deleting is permitted only for a draft. Active versions must be retired, preserving their audit history. Every matrix upload is synchronized with the Knowledge Repository.');

$doc->heading('4.4 Configure the indicator register', 2);
$doc->paragraph('Navigate to Monitoring & Evaluation → Results Framework and Indicator Management, or /budget/me/indicators.');
$doc->heading('Required configuration sequence', 3);
$doc->numbered('Choose the portfolio and project component.');
$doc->numbered('Enter the user-defined Indicator Code. Codes accept letters, numbers, periods, underscores and hyphens and are stored in uppercase. Example: PDO-2.');
$doc->numbered('Enter the full indicator name and definition. Example: “Number of policy-relevant research products generated”.');
$doc->numbered('Set results level, unit of measure, baseline and targets.');
$doc->numbered('Choose the reporting frequency: Monthly, Quarterly, Semi-Annual or Annual.');
$doc->numbered('Choose the time aggregation method for cumulative results: Sum, Latest, Average, Minimum, Maximum, Percentage/Ratio or Non-additive as appropriate.');
$doc->numbered('Choose the organization roll-up method for combining the 13 think tanks.');
$doc->numbered('Describe the data-collection method and link a Means of Verification from the Repository.');
$doc->numbered('Save, then configure applicable disaggregation dimensions. Mark only genuinely mandatory dimensions as Required.');
$doc->heading('Changing an indicator code', 3);
$doc->paragraph('Authorized users may edit a code. When changing an existing code, enter a change reason. The old code, new code, actor, reason and timestamp are retained. Never reuse a retired code for a different indicator.');

$doc->heading('4.5 Configure disaggregation', 2);
$doc->paragraph('The platform supports multiple simultaneous factors. Select all dimensions applicable to an indicator; there is no three-dimension limit.');
$doc->table([
    ['Dimension', 'Standard options / use'],
    ['Geographic scope', 'Country, National, REC, Regional / multi-country'],
    ['Country', 'African Union Member States'],
    ['REC', 'AMU, CEN-SAD, COMESA, EAC, ECCAS, ECOWAS, IGAD, SADC'],
    ['Implementing institution type', 'Think tank, Consortium, Partner institution'],
    ['ATTP priority thematic area', 'Economic Transformation and Governance; Climate Change; Regional Trade; Food Security; Human Capital; Digitalization'],
    ['Gender', 'Female; Male; Other / not reported'],
    ['Age group', 'Youth below 35; Adults 35 and above; Not reported'],
    ['Stakeholder category', 'Government; Parliament; Regional organization; Think tank; Academia; Civil society; Private sector; Development partner; Media; Other'],
]);
$doc->callout('Required versus available', 'A dimension can be available without being mandatory. Make it Required only when the indicator methodology expects that field for every beneficiary combination.');

$doc->heading('4.6 Select aggregation controls correctly', 2);
$doc->table([
    ['Organization roll-up', 'Use when', 'Example'],
    ['Sum', 'Each organization reports distinct additive units.', 'Number of separate research products.'],
    ['Latest approved value', 'Only the most recent organization value is meaningful.', 'Current institutional status.'],
    ['Simple average', 'Each organization has equal weight.', 'Average satisfaction score by think tank.'],
    ['Weighted average', 'Rates have different denominators.', '130 successful outcomes ÷ 200 cases = 65%.'],
    ['Minimum / Maximum', 'The programme needs the lowest or highest organization result.', 'Minimum compliance score.'],
    ['Non-additive', 'A single numeric total would be misleading.', 'Qualitative milestone or policy status.'],
]);
$doc->callout('Financial and statistical control', 'Never sum percentages, ratios, cumulative stock values or the same beneficiary across repeated rows. Use weighted average, latest or non-additive as specified in the indicator methodology.');

$doc->heading('4.7 Create forms, periods and collections', 2);
$doc->paragraph('Navigate to Monitoring & Evaluation → Data Entry and Performance Tracking.');
$doc->numbered('Create a reporting form and link it to the correct portfolio, project component and indicators.');
$doc->numbered('Define the seven report sections and any additional form instructions.');
$doc->numbered('Publish the form. Draft forms are not available to think tanks.');
$doc->numbered('Create or confirm the reporting period and dates.');
$doc->numbered('Create a collection, set opening/due/closing dates, and assign all 13 active organizations.');
$doc->numbered('Open the collection. Think tanks then see the assigned form.');
$doc->table([
    ['Indicator cadence', 'Report type', 'Available labels'],
    ['Monthly or Quarterly', 'Quarterly', 'Q1, Q2, Q3, Q4'],
    ['Semi-Annual', 'Semi-Annual', 'H1 (Jan–Jun), H2 (Jul–Dec)'],
    ['Annual', 'Annual', 'ANNUAL (Jan–Dec)'],
]);
$doc->paragraph('A report contains only indicators due for its selected type. A semi-annual indicator does not appear in a quarterly report merely because H1 ends in Q2.');

$doc->heading('5. Think Tank Guide: Create and Complete a Report');
$doc->heading('5.1 Open the reporting workspace', 2);
$doc->numbered('Sign in with the assigned M&E Officer or Think Tank Admin account.');
$doc->numbered('Open Think Tank → M&E Performance Reports, or /think-tank/me-data/performance-reports.');
$doc->numbered('Review Draft, Submitted, Verified, Approved and Archived counts.');
$doc->numbered('In Create a Draft Report, choose the assigned form, frequency, period and year.');
$doc->numbered('Select Create Draft Report. If no form appears, contact the Secretariat to check publication, collection dates and organization assignment.');

$doc->heading('5.2 Section 1: enter the indicator result', 2);
$doc->paragraph('Enter the organization result for the selected period. The platform displays reporting frequency, period target, cumulative year result, programme cumulative result, annual target, life target and progress.');
$doc->bullet('For a normal additive count, enter the actual result.');
$doc->bullet('For a weighted-average indicator, enter numerator and denominator. The platform calculates the percentage.');
$doc->bullet('Do not enter commas or narrative text in numeric fields.');
$doc->bullet('If zero is a real measured result, enter 0; do not leave it blank. Blank means not reported.');

$doc->heading('5.3 Add detailed achievements', 2);
$doc->paragraph('Below the seven-section report form, open the Unified achievement and beneficiary tracker. At least one achievement record is required for every due indicator.');
$doc->numbered('Enter a short, specific achievement title.');
$doc->numbered('Choose a date inside the report period.');
$doc->numbered('Describe the output/outcome, users reached and why it matters.');
$doc->numbered('Choose geographic scope; provide country for Country/National, or REC for REC scope.');
$doc->numbered('Confirm the lead think tank and list collaborators separated by commas or semicolons.');
$doc->numbered('Select one or more ATTP priority themes.');
$doc->numbered('Select Add achievement. A unique achievement reference is generated.');
$doc->callout('One achievement per record', 'If an indicator has three different policy products, create three achievement records. Do not hide them in one long narrative and do not repeat the indicator’s period result.');

$doc->heading('5.4 Record combined beneficiary disaggregation', 2);
$doc->paragraph('Add one row for each unique intersection of applicable factors. Example: Ghana + Think tank + Regional Trade + Female + Youth + Government. The achievement total is automatically recalculated from these rows.');
$doc->table([
    ['Country', 'Institution', 'Theme', 'Gender', 'Age', 'Stakeholder', 'Count'],
    ['Ghana', 'ACET', 'Regional Trade', 'Female', 'Youth below 35', 'Government', '8'],
    ['Ghana', 'ACET', 'Regional Trade', 'Female', 'Adult 35+', 'Government', '10'],
    ['Ghana', 'ACET', 'Regional Trade', 'Male', 'Youth below 35', 'Government', '9'],
    ['Ghana', 'ACET', 'Regional Trade', 'Male', 'Adult 35+', 'Government', '13'],
    ['Calculated total', '', '', '', '', '', '40'],
]);
$doc->paragraph('This example yields Female = 18, Male = 22 and total = 40. Each person belongs to one row. The platform blocks an exact duplicate combination.');
$doc->callout('Double-counting warning', 'Do not add a separate Female 18 row and then also add Female Youth 8 plus Female Adult 10. That would count the same 18 women twice. Use the most detailed mutually exclusive combinations available.');

$doc->heading('5.5 Upload achievement evidence', 2);
$doc->numbered('Under Evidence Repository links, enter a clear Document Title, not only the filename.');
$doc->numbered('Choose the supporting file and select Upload and link.');
$doc->numbered('The platform computes a checksum. If the exact file already exists in the same portfolio, it links the existing repository item instead of creating a duplicate.');
$doc->numbered('Confirm the document appears with its repository validation status.');
$doc->numbered('Unlink removes only the relationship; the repository copy remains for audit history.');

$doc->heading('5.6 Complete the seven mandatory report sections', 2);
$doc->table([
    ['Section', 'Required content'],
    ['1. Indicator results', 'Actual result and linked central result; detailed achievement; weighted numerator/denominator where applicable.'],
    ['2. Achievements and variance', 'Summary of key delivery and explanation for difference from target.'],
    ['3. MOV and supporting documents', 'MOV notes and at least one titled attachment.'],
    ['4. Overall assessment', 'Assessment, performance rating and conclusion.'],
    ['5. Challenges and mitigation', 'Specific challenges and actions taken.'],
    ['6. Lessons and adaptive management', 'Learning and the resulting management change.'],
    ['7. Next-period priorities', 'Specific priorities, planned outputs and management focus.'],
]);
$doc->paragraph('Select Save Draft regularly. The mandatory-section summary changes from Not started to In progress to Complete. Submission remains disabled until all seven sections are complete.');

$doc->heading('5.7 Submit, correct and resubmit', 2);
$doc->numbered('Review every green Complete status.');
$doc->numbered('Open evidence once to confirm the correct file was uploaded.');
$doc->numbered('Select Submit Report and confirm. The report becomes read-only.');
$doc->numbered('If Returned, open review notes, correct every point, save and submit again. The transition history preserves the earlier return.');
$doc->numbered('If Verified, wait for final approval; do not create a duplicate report.');
$doc->numbered('If Approved or Archived, use View/download only.');

$doc->heading('6. Secretariat M&E Officer Guide: Verification');
$doc->heading('6.1 Open a submitted report', 2);
$doc->paragraph('Use Reporting and Dashboard, the Data Entry report list, or Think Tank M&E Reports. Open the organization’s Submitted report.');
$doc->heading('6.2 Verification checklist', 2);
foreach ([
    'Identity: correct think tank, form, component, period type, label and year.',
    'Cadence: every displayed indicator is due for the selected reporting type.',
    'Result: numeric actual agrees with the source and unit of measure.',
    'Target: period and annual targets match the active M&E Matrix.',
    'Calculation: progress = actual ÷ target × 100 where the target is non-zero.',
    'Cumulative logic: stock values, ratios and percentages have not been summed incorrectly.',
    'Achievement: title, date, location, theme, lead and collaborators are plausible and complete.',
    'Disaggregation: combined rows are mutually exclusive; totals reconcile across gender and age.',
    'Evidence: every material claim is traceable to a titled file or repository item.',
    'Narrative: variance, challenges, mitigation, lessons and next steps are specific and internally consistent.',
    'Data protection: no unnecessary sensitive personal data is exposed.',
] as $item) {
    $doc->bullet($item);
}
$doc->heading('6.3 Return or verify', 2);
$doc->bullet('Return Report: mandatory when correction is needed. Enter actionable notes. The report reopens as Draft for its author.');
$doc->bullet('Verify Report: select only after checking calculations, disaggregation and evidence. Enter verification notes. Indicator results and report attachments become validated.');
$doc->paragraph('Verification is not final approval. The status becomes Verified and the report waits for an authorized approver.');

$doc->heading('7. Final Approval and Archival');
$doc->heading('7.1 Final approval', 2);
$doc->numbered('Open a Verified report.');
$doc->numbered('Review the verifier’s name, date and notes.');
$doc->numbered('Confirm any management or programme-level judgement.');
$doc->numbered('Select Approve Report and record approval notes, or Return Report with required corrections.');
$doc->paragraph('A non-administrator cannot verify and approve the same report. This separation protects the credibility of the programme result.');
$doc->heading('7.2 Archive', 2);
$doc->numbered('Open the Approved report.');
$doc->numbered('Add optional retention/closure notes.');
$doc->numbered('Select Archive Report.');
$doc->paragraph('Archived reports remain included in consolidation and become read-only historical records.');

$doc->heading('8. Secretariat Reporting: Separate and Consolidated Views');
$doc->heading('8.1 View all 13 submissions separately', 2);
$doc->paragraph('Navigate to Monitoring & Evaluation → Think Tank M&E Reports, or /budget/me/consolidated-reports. Select a Think Tank, year, frequency, period and optional portfolio.');
$doc->bullet('Every active organization is listed, including “No submission”.');
$doc->bullet('Each form/report remains separately openable for review.');
$doc->bullet('Draft, Submitted, Verified, Approved and Archived badges expose readiness.');
$doc->bullet('Use this register for follow-up before generating the consolidated report.');
$doc->heading('8.2 Generate the consolidated report', 2);
$doc->paragraph('The consolidated section uses only Approved, Archived and legacy-approved reports. It groups by indicator and applies the indicator’s organization roll-up method.');
$doc->bullet('Excel exports a structured row per indicator with result, target, organizations, achievements, beneficiaries, geographic scope, country, REC, theme, institution, stakeholder, gender and age fields.');
$doc->bullet('PDF produces a management-ready landscape summary.');
$doc->bullet('The screen shows gender, age, stakeholder, theme, country and REC snapshots.');
$doc->bullet('If overlapping approved forms contain the same indicator for one organization and period, only the most recently approved result is counted; the suppressed duplicate count is displayed for audit follow-up.');
$doc->callout('Inclusion rule', 'A merely Submitted or Verified result is not official and is excluded from consolidation. Approve all valid organization reports before issuing the final consolidated report.');
$doc->heading('8.3 Use the Consolidations Engine', 2);
$doc->paragraph('Navigate to Monitoring & Evaluation → Consolidations Engine, or /budget/me/consolidation-engine. Use Indicator level for detailed target-versus-actual analysis and Project level for comparable project/component scorecards.');
$doc->bullet('Filter by target project year, reporting year or period, portfolio, project/component, indicator, Think Tank, results level, performance status, country and thematic area.');
$doc->bullet('Indicator consolidation exposes the approved contribution sources, organization coverage, aggregation method, target, actual, variance, attainment, trend, evidence links, achievements and participant/beneficiary instances.');
$doc->bullet('Project consolidation never adds unlike raw indicator values. Its score is the average of rated indicator attainment percentages, with each indicator capped at 100%, alongside coverage and status distribution.');
$doc->bullet('Download a complete multi-sheet Excel workbook, the selected level as CSV, the selected level as a landscape PDF, or use the print-ready screen.');
$doc->callout('Interpretation control', 'Participant and beneficiary totals are reporting instances, not deduplicated unique people. Evidence totals are indicator-evidence links. Apply the approved indicator methodology when interpreting either measure.');
$doc->heading('8.4 Build an Indicator Report', 2);
$doc->paragraph('Navigate to Monitoring & Evaluation → Indicator Report, or /budget/me/indicator-reports. Choose Individual indicator report for a focused indicator dossier, or Consolidated indicator report for the complete authorized indicator register.');
$doc->bullet('An individual dossier requires one indicator and presents its definition, approved Indicator Reference Sheet, measurement rules, approved target, actual, attainment, trend, reporting coverage, source contributions and evidence.');
$doc->bullet('The consolidated report keeps every filtered indicator in its own unit and applies its configured time aggregation and organization roll-up. It does not add unlike indicator values.');
$doc->bullet('Use the same reporting-period, portfolio, project, contributor, country and thematic filters for the on-screen report and its Excel, CSV and PDF downloads. Print produces a clean hard-copy view.');
$doc->bullet('Excel includes Summary & Scope, an Indicator Profile or Indicator Consolidation sheet, Approved Contributions and Evidence Links. Individual CSV is source-level; consolidated CSV is indicator-level.');
$doc->callout('Official-data guardrail', 'Indicator Reports use finally approved, deduplicated indicator results and approved targets only. A report with no approved contribution remains visible as not reported, while draft, submitted, returned and rejected values remain excluded.');

$doc->heading('9. Calculations and Financial/Statistical Controls');
$doc->heading('9.1 Core calculations', 2);
$doc->table([
    ['Measure', 'Formula / rule', 'Example'],
    ['Period progress', 'Actual for period ÷ period target × 100', '7 ÷ 8 = 87.5%'],
    ['Annual target achievement', 'Verified cumulative achievement ÷ annual target × 100', '7 ÷ 20 = 35.0%'],
    ['Beneficiary total', 'Sum of unique combined beneficiary rows', '8 + 10 + 9 + 13 = 40'],
    ['Weighted cross-organization rate', 'Σ numerator ÷ Σ denominator × 100', '(8 + 5) ÷ (10 + 10) = 65%'],
]);
$doc->heading('9.2 Time aggregation versus organization roll-up', 2);
$doc->paragraph('These are different controls. Time aggregation explains how one organization’s Q1, Q2, Q3 and Q4 values become a year/cumulative result. Organization roll-up explains how ACET, AFIDEP and the other think tanks become one ATTP value. Configure both.');
$doc->heading('9.3 Zero, blank and not applicable', 2);
$doc->bullet('0 = measured and no achievement/result occurred.');
$doc->bullet('Blank = not reported or not yet known.');
$doc->bullet('Not applicable = the field does not apply under the indicator methodology. Use the appropriate option, not zero.');
$doc->bullet('A zero/missing target produces no percentage to avoid division by zero.');

$doc->heading('10. Worked ATTP Example: PDO 2, ACET, H1 2026');
$doc->paragraph('The following example reproduces the completed example supplied in the workbook and shows how to enter it in the normalized platform.');
$doc->table([
    ['Field', 'Example value'],
    ['Indicator code', 'PDO 2'],
    ['Indicator name', 'Number of policy-relevant research products generated'],
    ['Reporting type / period', 'Semi-Annual / H1 2026 (1 Jan–30 Jun 2026)'],
    ['Baseline', '0'],
    ['Annual target', '20'],
    ['H1 target', '8'],
    ['Result this period', '7'],
    ['Cumulative achievement', '7'],
    ['Unit', 'Number'],
    ['Annual target achievement', '35.0%'],
    ['H1 target achievement', '87.5%'],
    ['Performance explanation', 'Seven of eight planned products completed; one delayed pending stakeholder validation.'],
]);
$doc->heading('10.1 Achievement record', 2);
$doc->table([
    ['Field', 'Example value'],
    ['Title', 'Policy study on AfCFTA implementation'],
    ['Description', 'A policy-relevant study assessing barriers to AfCFTA implementation was completed and validated, with recommendations for cross-border trade and regional market integration.'],
    ['Date achieved', '20 June 2026'],
    ['Country / location', 'Ghana / Accra'],
    ['Theme', 'Regional Trade'],
    ['Lead think tank', 'ACET'],
    ['Collaborators', 'Ministry of Trade and Industry; AfCFTA Secretariat'],
    ['Stakeholder group', 'Policymakers; Government officials'],
    ['Beneficiaries', 'Female 18; Male 22; total 40'],
    ['Evidence title', 'Validated AfCFTA Implementation Policy Study'],
    ['Workbook reference', 'PDO2-ACET-2026-001'],
]);
$doc->heading('10.2 Exact platform steps', 2);
$doc->numbered('M&E Officer configures PDO 2 as Semi-Annual, unit Number, time aggregation Sum and organization roll-up Sum, unless the indicator protocol states otherwise.');
$doc->numbered('The Secretariat assigns the published form to ACET.');
$doc->numbered('ACET creates H1 2026, enters actual result 7 and saves.');
$doc->numbered('ACET adds the AfCFTA policy-study achievement with Ghana, Accra, Regional Trade, ACET and the two collaborators.');
$doc->numbered('ACET adds four mutually exclusive gender × age × stakeholder rows totalling 40.');
$doc->numbered('ACET uploads the validated study with the Document Title and verifies the repository link.');
$doc->numbered('ACET completes all narrative sections and submits.');
$doc->numbered('M&E Officer checks 7/8 = 87.5%, 7/20 = 35%, the evidence and the 40-person disaggregation, then verifies.');
$doc->numbered('An authorized approver approves. The result becomes eligible for ATTP consolidation.');

$doc->heading('11. Knowledge and Evidence Repository');
$doc->heading('11.1 Add a document', 2);
$doc->numbered('Open Knowledge and Evidence Repository.');
$doc->numbered('Select portfolio, enter Document Title, choose type and add a description.');
$doc->numbered('Upload a file or enter an external URL.');
$doc->numbered('The system rejects an exact duplicate file in the same active portfolio.');
$doc->heading('11.2 Edit metadata and replace a file', 2);
$doc->numbered('Select Edit on the repository row.');
$doc->numbered('Correct title, type, description or URL and save.');
$doc->numbered('To replace content, upload a replacement and state What changed.');
$doc->numbered('A new version is created; prior versions remain in audit history. Validation returns to Pending.');
$doc->heading('11.3 Delete versus retain', 2);
$doc->paragraph('An unlinked item can be deleted by an authorized manager. A document linked to an indicator, report, achievement or M&E Matrix cannot be deleted. Remove an incorrect draft link, or retain/retire the document according to records policy.');

$doc->heading('12. Data Quality, Audit Trail and Governance');
$doc->heading('12.1 Audit records retained', 2);
foreach ([
    'Indicator code changes and reason.', 'Report status transitions, actor, timestamp and notes.',
    'Verifier and approver identities and timestamps.', 'Repository document versions and change notes.',
    'Canonical evidence links to reports and achievements.', 'Matrix versions, active/retired status and approval.',
    'Calculated achievement beneficiary totals and unique combination hashes.',
] as $item) {
    $doc->bullet($item);
}
$doc->heading('12.2 Minimum data-quality rules', 2);
$doc->bullet('Achievement date must fall within the report period.');
$doc->bullet('Country is required for Country or National scope; REC is required for REC scope.');
$doc->bullet('At least one priority theme is required for an achievement.');
$doc->bullet('Indicator-required disaggregation dimensions must be completed.');
$doc->bullet('Exact duplicate beneficiary combinations are blocked.');
$doc->bullet('Exact duplicate file uploads are linked, not duplicated.');
$doc->bullet('Only complete seven-section reports can be submitted or advanced.');
$doc->heading('12.3 Privacy and evidence handling', 2);
$doc->bullet('Record aggregated beneficiary counts; do not upload personal lists unless the approved evidence protocol requires and protects them.');
$doc->bullet('Use descriptive document titles without exposing confidential personal details.');
$doc->bullet('Apply least privilege to M&E, review, approval and archive permissions.');
$doc->bullet('Use external URLs only for trusted, access-controlled repositories.');

$doc->heading('13. Dashboard and Management Use');
$doc->paragraph('The Reporting and Dashboard page provides workflow distribution, submission timeliness, overdue items, average review time, indicator completeness, reports by think tank, component and reporting period, and permission-scoped drill-down.');
$doc->bullet('Use Submitted to identify reports awaiting verification.');
$doc->bullet('Use Verified to identify reports awaiting approval.');
$doc->bullet('Use Returned to monitor corrective action.');
$doc->bullet('Use Indicator completeness to detect missing results before the deadline.');
$doc->bullet('Use the consolidated page—not manual spreadsheet copying—to issue the official cross-think-tank result.');

$doc->heading('14. Troubleshooting Guide');
$doc->table([
    ['Message / symptom', 'Likely cause', 'Resolution'],
    ['No assigned form', 'Form not published, collection not open, organization not assigned or account mapped incorrectly.', 'Secretariat checks form, dates, assignment and Focal Unit mapping.'],
    ['Setup incomplete / 0 of 6', 'The database has not yet been commissioned for a reporting cycle.', 'Use each readiness-card action in order; do not create reports by direct database editing.'],
    ['Login disabled', 'The focal account was disabled through user access management.', 'An authorized user administrator confirms the owner, enables login and verifies M&E access.'],
    ['No indicators due', 'Selected report type does not match indicator cadence.', 'Choose Quarterly, Semi-Annual or Annual as configured; do not force Q2 for H1.'],
    ['Achievement date rejected', 'Date lies outside reporting period.', 'Correct date or create the correct report period.'],
    ['Country / REC required', 'Geographic scope demands that classification.', 'Enter country for Country/National or select REC for REC.'],
    ['Disaggregation field required', 'Indicator register marks that dimension mandatory.', 'Complete the field or have the authorized officer correct the indicator methodology.'],
    ['Duplicate combination', 'Same combined beneficiary dimensions already exist.', 'Use a different mutually exclusive intersection or remove the erroneous row.'],
    ['Exact file already exists', 'Checksum matches a repository item.', 'Link/use the existing item; do not rename and re-upload.'],
    ['Submit button disabled', 'At least one mandatory section item is missing.', 'Read the Mandatory section check, save corrections and reload.'],
    ['Report read-only', 'It is Submitted, Verified, Approved or Archived.', 'Reviewer returns it if correction is required; otherwise no edit is allowed.'],
    ['Cannot approve after verifying', 'Separation-of-duties control.', 'Ask a different authorized reviewer or an accountable administrator to approve.'],
    ['Repository delete disabled', 'Document has an indicator/report/achievement/matrix link.', 'Retain for audit; remove only an incorrect draft link.'],
    ['13 organizations not shown', 'Duplicate/inactive master data or a missing organization mapping.', 'Review active consortium members and the Focal Unit Register before opening the collection.'],
    ['Consolidated result blank', 'Non-additive method, no approved values, or missing weighted denominator.', 'Approve valid reports; configure correct roll-up; complete numerator/denominator.'],
]);

$doc->heading('15. Operational Checklists');
$doc->heading('15.1 Secretariat pre-opening checklist', 2);
foreach (['13 organizations active and mapped', 'Focal contacts confirmed', 'M&E Matrix active', 'Indicator codes/definitions approved', 'Targets and cadence approved', 'Roll-up methods reviewed', 'Disaggregation configured', 'MOV links valid', 'Form published', 'Collection dates set', 'All 13 assigned', 'Notifications tested'] as $item) {
    $doc->bullet('☐ '.$item);
}
$doc->heading('15.2 Think tank pre-submission checklist', 2);
foreach (['Correct period and year', 'All actuals saved', 'At least one achievement per indicator', 'Combined beneficiary rows reconcile', 'Themes/geography/collaborators complete', 'Evidence titles meaningful', 'Evidence opens successfully', 'Seven sections complete', 'Variance and next steps specific', 'Internal organizational review completed'] as $item) {
    $doc->bullet('☐ '.$item);
}
$doc->heading('15.3 M&E verification checklist', 2);
foreach (['Identity and cadence checked', 'Actuals agree to source', 'Targets agree to active matrix', 'Calculations checked', 'No double counting', 'Evidence sufficient', 'Required disaggregation complete', 'Narratives consistent', 'Return/verify notes recorded'] as $item) {
    $doc->bullet('☐ '.$item);
}
$doc->heading('15.4 Approval and issue checklist', 2);
foreach (['Verifier is independent', 'All corrections closed', 'Final approval notes recorded', '13-organization completeness reviewed', 'Only approved/archived data consolidated', 'Excel/PDF reviewed', 'Report archived after issue'] as $item) {
    $doc->bullet('☐ '.$item);
}

$doc->heading('16. Excel-to-Platform Field Map');
$doc->table([
    ['Workbook field', 'Platform location / handling'],
    ['Indicator ID / Name', 'Indicator Register; editable controlled code with history.'],
    ['Period Start / End', 'Derived from Quarterly, Semi-Annual or Annual period.'],
    ['Baseline / Annual Target / Period Target', 'Indicator setup and target records; copied into report snapshot.'],
    ['Achievement for This Period', 'Report indicator actual result.'],
    ['Cumulative Achievement', 'Calculated through authorized time aggregation.'],
    ['Percentage Achievement', 'Automatically calculated where target is non-zero.'],
    ['Progress Status / Explanation', 'Performance rating and report narratives; verified by officer.'],
    ['Achievement Title / Description / Date', 'Separate achievement record under the indicator result.'],
    ['Country / Location / Theme / Lead / Collaborators', 'Achievement classification fields.'],
    ['Beneficiary Group / Female / Male / Other / Total', 'Combined disaggregation rows; total calculated.'],
    ['Evidence link / Reference', 'Canonical Evidence Repository item and generated achievement reference.'],
    ['Verification Status', 'Report lifecycle plus repository validation status.'],
]);

$doc->heading('17. Focal Unit Reference from Supplied Workbook');
$doc->heading('17.1 Official organization directory', 2);
$doc->paragraph('The platform currently recognizes the following 13 active think-tank organizations. Workbook abbreviations are shown in parentheses where applicable. Each report remains separated by this organization identity before consolidation.');
$doc->table([
    ['Workbook label', 'Official platform organization name'],
    ['ACET', 'African Center for Economic Transformation (ACET)'],
    ['AFIDEP', 'African Institute for Development Policy (AFIDEP)'],
    ['APHRC', 'African Population and Health Research Center (APHRC)'],
    ['CPED', 'Centre for Population and Environmental Development (CPED)'],
    ['CIP', 'Centro de Integridade Publica'],
    ['Foretia Foundation', 'Denis and Lenora Foretia Foundation (Nkafu Policy Institute)'],
    ['ERF', 'Economic Research Forum'],
    ['IPAR', 'Initiative Prospective Agricole et Rurale'],
    ['PEP', 'Partnership for Economic Policy (PEP)'],
    ['Policy Center', 'Policy Center for the New South (PCNS)'],
    ['REPRC-UNN', 'Resource and Environmental Policy Research Centre (REPRC), Environment for Development (EfD) Nigeria'],
    ['SAIIA', 'South Africa Institute of International Affairs (SAIIA)'],
    ['ECES', 'The Egyptian Center for Economic Studies (ECES)'],
], [2300, 7060]);
$doc->heading('17.2 Focal-person register', 2);
$doc->table([
    ['Consortium', 'Think tank', 'Focal person', 'Email', 'Primary / note'],
    ['RAISE AFRICA', 'ERF', 'Ms. Yasmine Fahim', 'yfahim@erf.org.eg', ''],
    ['RAISE AFRICA', 'ERF', 'Ms. Yasmeen Oraby', 'yoraby@erf.org.eg', ''],
    ['RAISE AFRICA', 'PEP', 'Mr. Dickson Otiangala', 'dickson.otiangala@pep-net.org', ''],
    ['RAISE AFRICA', 'PEP', 'Ms. Ana Badillo', 'ana.badillo@pep-net.org', ''],
    ['RAISE AFRICA', 'REPRC-UNN', 'Prof. Innocent Ifelunini', 'innocent.ifelunini@unn.edu.ng', ''],
    ['CACEPS', 'APHRC', 'Meshack Johnson', 'mjohnson@aphrc.org', 'Primary contact'],
    ['CACEPS', 'APHRC', 'Judy Ihiga', 'jihiga@aphrc.org', ''],
    ['CACEPS', 'APHRC', 'Billy Lubuya', 'blubuya@aphrc.org', ''],
    ['CACEPS', 'CPED', 'Mercy Edejeghwro', 'omueromercy21@gmail.com', ''],
    ['CACEPS', 'CIP', 'Julia Zita', 'julia.zita@cipmoz.org', ''],
    ['CACEPS', 'IPAR', 'Awa Dia', 'awa.dia@ipar.sn', 'Primary contact'],
    ['CACEPS', 'IPAR', 'Oumar Gueye', 'oumar.gueye@ipar.sn', ''],
    ['CACEPS', 'ECES', 'Seif Khawanky', 'seif.khawanky@gmail.com', ''],
    ['BRIDGE', 'ACET', 'Iana Dadzie', 'ddadzie@acetforafrica.org', ''],
    ['BRIDGE', 'AFIDEP', 'Lwama Kamanga', 'lwama.kamanga@afidep.org', ''],
    ['BRIDGE', 'Policy Center', 'Asmaa Tahraoui', 'a.tahraoui@policycenter.ma', ''],
    ['BRIDGE', 'Foretia Foundation', 'Bruno Ittia', 'bachuo@foretiafoundation.org', ''],
    ['BRIDGE', 'SAIIA', 'Goodwill Kachingwe', 'goodwill.kachingwe@saiia.org.za', 'Primary; ATTP Project Coordinator'],
], [1400, 1500, 1900, 2700, 1860]);
$doc->callout('Data protection', 'This contact register came from the user-supplied workbook. Confirm accuracy and authorization before distributing the manual outside the ATTP reporting network.');

$doc->heading('18. Glossary');
$doc->table([
    ['Term', 'Meaning'],
    ['ATTP', 'Africa Think Tank Platform.'],
    ['M&E / MEL', 'Monitoring and Evaluation / Monitoring, Evaluation and Learning.'],
    ['Indicator', 'Defined measure used to track output, outcome or programme objective.'],
    ['MOV', 'Means of Verification supporting an indicator or reported result.'],
    ['Achievement', 'Specific output/outcome contributing to an indicator result in a period.'],
    ['Disaggregation', 'Breaking a result into meaningful classifications such as geography, gender, age and stakeholder.'],
    ['REC', 'Regional Economic Community.'],
    ['Period target', 'Target approved for the selected reporting period.'],
    ['Cumulative result', 'Result to date, calculated according to the indicator’s time aggregation rule.'],
    ['Roll-up', 'Rule for combining organization results into one ATTP result.'],
    ['Checksum', 'Digital fingerprint used to recognize an identical uploaded file.'],
    ['Repository link', 'Auditable relationship between one canonical document and a report, achievement, indicator or matrix.'],
    ['Verified', 'M&E quality/evidence review completed; awaiting final approval.'],
    ['Approved', 'Final authorization completed; eligible for consolidation.'],
]);

$doc->heading('19. Deployment and Support Notes for Administrators');
$doc->numbered('Back up the production database and uploaded-file storage before deployment.');
$doc->numbered('Deploy application files and run php artisan migrate --force. No manual SQL editing is required.');
$doc->numbered('Clear/rebuild application caches using the established deployment procedure.');
$doc->numbered('Confirm active think tanks = 13 and focal contacts = 18 in the Focal Unit Register.');
$doc->numbered('Confirm the legacy exact ERF duplicate is inactivated only when it has no business records; if records exist, resolve it through controlled master-data review.');
$doc->numbered('Test internal and think-tank permissions with non-administrator accounts.');
$doc->numbered('Upload the approved M&E Matrix and activate it.');
$doc->numbered('Run a demonstration using PDO 2, ACET and H1 2026, then delete only the draft demonstration data or use a staging environment.');
$doc->numbered('Confirm Excel and PDF consolidated exports download and open.');
$doc->numbered('Retain migration and application logs under the project’s deployment policy.');

$doc->heading('20. Frequently Asked Questions');
$doc->heading('Can one indicator have several achievements?', 2);
$doc->paragraph('Yes. Add every distinct achievement under the same indicator result. The indicator actual is entered once.');
$doc->heading('Can an achievement have more than one theme or collaborator?', 2);
$doc->paragraph('Yes. Select multiple themes and list collaborators separated by commas or semicolons.');
$doc->heading('Can beneficiaries be disaggregated by gender, age and stakeholder together?', 2);
$doc->paragraph('Yes. Each beneficiary row represents one combined intersection. This is the preferred method because it reconciles totals and limits double counting.');
$doc->heading('Can an uploaded file be edited or deleted?', 2);
$doc->paragraph('Metadata can be edited. A replacement creates a retained new version. Only unlinked items can be deleted; linked items are protected evidence.');
$doc->heading('Why is my report missing an indicator?', 2);
$doc->paragraph('The report type must match the indicator cadence. Check the indicator’s reporting frequency and the form relationship.');
$doc->heading('Why does consolidation exclude a valid-looking report?', 2);
$doc->paragraph('Only finally Approved, Archived or migrated legacy-approved reports are official. A Verified report still needs approval.');
$doc->heading('Can authorized users add or edit indicator codes later?', 2);
$doc->paragraph('Yes. Enter a reason when changing an existing code; the full history remains available for audit.');

$doc->heading('21. Final Operating Principle');
$doc->paragraph('The ATTP MEL Platform should be treated as the authoritative operational record. The M&E Matrix controls the approved framework; the Indicator Register controls definitions and calculations; think tanks report organization-owned data; evidence is stored once and linked; M&E verification confirms quality; final approval authorizes use; and the consolidated report combines only approved data under explicit statistical rules.', 'Normal', ['bold' => true, 'size' => 23, 'color' => '073F30']);
$doc->callout('Completion standard', 'A report is complete only when its number, narrative, achievement detail, disaggregation, evidence, verification and approval all tell the same traceable story.');

$output = dirname(__DIR__).'/docs/ATTP_MEL_Platform_Complete_User_Manual.docx';
$doc->save($output);
echo $output.PHP_EOL;
