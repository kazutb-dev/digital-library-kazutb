<?php

namespace Database\Seeders\Support;

/**
 * Builds a small but genuinely valid multi-page PDF for demo and test data.
 *
 * The reading room needs a file it can actually paginate: page count, page
 * turning, zoom and the table of contents are all read out of the document, so a
 * one-page stub cannot exercise any of them. Rather than commit a binary
 * fixture, this assembles the file byte by byte with a correct cross-reference
 * table — a PDF with wrong xref offsets loads in some viewers and fails in
 * pdf.js, which is exactly the trap this avoids.
 *
 * Text is deliberately ASCII: the base-14 Helvetica font used here is
 * WinAnsi-encoded and cannot render Cyrillic without an embedded font, which is
 * far more machinery than demo data warrants.
 */
final class DemoPdfBuilder
{
    /** A4 at 72 dpi. */
    private const PAGE_WIDTH = 595;

    private const PAGE_HEIGHT = 842;

    /**
     * @param  list<array{title: string, lines: list<string>}>  $pages
     */
    public function build(string $documentTitle, array $pages): string
    {
        if ($pages === []) {
            $pages = [['title' => $documentTitle, 'lines' => ['(empty document)']]];
        }

        $pageCount = count($pages);

        // Object numbering is fixed up front so references can be written before
        // the objects they point at exist.
        //   1 catalog, 2 page tree, 3 font, 4 outline root,
        //   then per page: content stream, page, outline item.
        $firstPageObject = 5;
        $contentId = fn (int $index): int => $firstPageObject + ($index * 3);
        $pageId = fn (int $index): int => $firstPageObject + ($index * 3) + 1;
        $outlineItemId = fn (int $index): int => $firstPageObject + ($index * 3) + 2;

        $kids = implode(' ', array_map(
            fn (int $index): string => $pageId($index).' 0 R',
            range(0, $pageCount - 1),
        ));

        $objects = [];

        $objects[1] = '<< /Type /Catalog /Pages 2 0 R /Outlines 4 0 R /PageMode /UseOutlines >>';
        $objects[2] = "<< /Type /Pages /Kids [{$kids}] /Count {$pageCount} >>";
        $objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objects[4] = sprintf(
            '<< /Type /Outlines /First %d 0 R /Last %d 0 R /Count %d >>',
            $outlineItemId(0),
            $outlineItemId($pageCount - 1),
            $pageCount,
        );

        foreach ($pages as $index => $page) {
            $stream = $this->contentStream(
                (string) ($page['title'] ?? ''),
                array_values((array) ($page['lines'] ?? [])),
                $index + 1,
                $pageCount,
            );

            $objects[$contentId($index)] = sprintf(
                "<< /Length %d >>\nstream\n%s\nendstream",
                strlen($stream),
                $stream,
            );

            $objects[$pageId($index)] = sprintf(
                '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %d %d] '
                .'/Resources << /Font << /F1 3 0 R >> >> /Contents %d 0 R >>',
                self::PAGE_WIDTH,
                self::PAGE_HEIGHT,
                $contentId($index),
            );

            $links = '';
            if ($index > 0) {
                $links .= sprintf(' /Prev %d 0 R', $outlineItemId($index - 1));
            }
            if ($index < $pageCount - 1) {
                $links .= sprintf(' /Next %d 0 R', $outlineItemId($index + 1));
            }

            $objects[$outlineItemId($index)] = sprintf(
                '<< /Title (%s) /Parent 4 0 R /Dest [%d 0 R /Fit]%s >>',
                $this->escapeText((string) ($page['title'] ?? 'Page '.($index + 1))),
                $pageId($index),
                $links,
            );
        }

        return $this->assemble($objects, $documentTitle);
    }

    /**
     * A ready-made sample: a title page plus enough numbered pages that paging,
     * the page-number field and the outline all have something to act on.
     *
     * @return list<array{title: string, lines: list<string>}>
     */
    public static function samplePages(string $title, int $pageCount = 12): array
    {
        $pages = [[
            'title' => 'Title page',
            'lines' => [
                $title,
                '',
                'KazUTB',
                'Sample digital material for the controlled reading room.',
                '',
                'This file is generated demo data. It is not a real publication.',
            ],
        ]];

        for ($number = 2; $number <= $pageCount; $number++) {
            $chapter = (int) ceil(($number - 1) / 3);

            $pages[] = [
                'title' => "Chapter {$chapter}, page {$number}",
                'lines' => [
                    "Chapter {$chapter}",
                    '',
                    "This is page {$number} of {$pageCount}.",
                    '',
                    'Use the arrow keys or the page controls in the toolbar to',
                    'move through the document. The zoom controls scale the page,',
                    'and the contents panel jumps straight to a chapter.',
                    '',
                    'A signed-in reader has their position saved automatically, so',
                    'reopening this material returns to the last page read.',
                ],
            ];
        }

        return $pages;
    }

    /**
     * @param  list<string>  $lines
     */
    private function contentStream(string $heading, array $lines, int $pageNumber, int $pageCount): string
    {
        $parts = ['BT', '/F1 20 Tf', '1 0 0 1 64 760 Tm', '24 TL'];
        $parts[] = sprintf('(%s) Tj', $this->escapeText($heading));
        $parts[] = 'ET';

        $parts[] = 'BT';
        $parts[] = '/F1 12 Tf';
        $parts[] = '1 0 0 1 64 700 Tm';
        $parts[] = '18 TL';

        foreach ($lines as $line) {
            // An empty Tj still advances the line, which is how blank lines in
            // the source text turn into vertical space.
            $parts[] = sprintf('(%s) Tj T*', $this->escapeText($line));
        }

        $parts[] = 'ET';

        $parts[] = 'BT';
        $parts[] = '/F1 10 Tf';
        $parts[] = '1 0 0 1 64 60 Tm';
        $parts[] = sprintf('(%s) Tj', $this->escapeText("Page {$pageNumber} / {$pageCount}"));
        $parts[] = 'ET';

        return implode("\n", $parts);
    }

    /**
     * Serialise the objects and record where each one starts — the xref table
     * has to hold exact byte offsets or pdf.js rejects the file.
     *
     * @param  array<int, string>  $objects
     */
    private function assemble(array $objects, string $documentTitle): string
    {
        ksort($objects);

        $pdf = "%PDF-1.4\n";
        // A binary comment marks the file as binary for tools that sniff it.
        $pdf .= "%\xE2\xE3\xCF\xD3\n";

        $offsets = [];

        foreach ($objects as $id => $body) {
            $offsets[$id] = strlen($pdf);
            $pdf .= "{$id} 0 obj\n{$body}\nendobj\n";
        }

        $infoId = max(array_keys($objects)) + 1;
        $offsets[$infoId] = strlen($pdf);
        $pdf .= sprintf(
            "%d 0 obj\n<< /Title (%s) /Producer (KazUTB demo seeder) >>\nendobj\n",
            $infoId,
            $this->escapeText($documentTitle),
        );

        $size = $infoId + 1;
        $xrefOffset = strlen($pdf);

        $pdf .= "xref\n0 {$size}\n";
        $pdf .= "0000000000 65535 f \n";

        for ($id = 1; $id < $size; $id++) {
            // Every id in 1..size-1 is allocated by the numbering scheme above,
            // but guard anyway: a gap written as an in-use entry corrupts the file.
            $pdf .= isset($offsets[$id])
                ? sprintf("%010d 00000 n \n", $offsets[$id])
                : "0000000000 65535 f \n";
        }

        $pdf .= sprintf(
            "trailer\n<< /Size %d /Root 1 0 R /Info %d 0 R >>\nstartxref\n%d\n%%%%EOF\n",
            $size,
            $infoId,
            $xrefOffset,
        );

        return $pdf;
    }

    /**
     * Escape the three characters that are structural inside a PDF literal
     * string, and drop anything outside WinAnsi that Helvetica cannot draw.
     */
    private function escapeText(string $text): string
    {
        $ascii = preg_replace('/[^\x20-\x7E]/', '', $text) ?? '';

        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $ascii);
    }
}
