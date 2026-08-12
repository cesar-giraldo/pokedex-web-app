<?php

declare(strict_types=1);

namespace App\Admin\Service\Excel;

use App\Entity\Pokemon;
use DateTimeImmutable;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

use function chr;
use function is_resource;
use function range;
use function strtoupper;
use function unlink;

class PokemonListExcelExporter
{
    private const int HEADER_ROW = 8;

    private const int SPRITE_HEIGHT = 48;

    private const float DATA_ROW_HEIGHT = 52;

    public function __construct(
        private PokemonSpriteImageLoader $spriteImageLoader,
    ) {
    }

    /**
     * @param list<Pokemon> $pokemons
     * @param array{
     *     current_page: int,
     *     total_pages: int,
     *     total_results: int,
     *     max_per_page: int
     * } $pagination
     */
    public function export(
        array $pokemons,
        ?string $searchTerm = null,
        string $sort = 'p.listOrder',
        string $direction = 'asc',
        array $pagination = [
            'current_page' => 1,
            'total_pages' => 1,
            'total_results' => 0,
            'max_per_page' => 0,
        ],
    ): string {
        $generatedAt = new DateTimeImmutable();
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Pokémon');

        $sheet->setCellValue('A1', 'Listado de Pokémon');
        $sheet->setCellValue('A2', 'Generado: ' . $generatedAt->format('d/m/Y H:i'));
        $sheet->setCellValue('A3', 'Total registros: ' . $pagination['total_results']);
        $sheet->setCellValue(
            'A4',
            'Página: ' . $pagination['current_page'] . ' de ' . $pagination['total_pages'],
        );
        $sheet->setCellValue('A5', 'Búsqueda: ' . ($searchTerm ?: 'Sin filtro'));
        $sheet->setCellValue('A6', 'Orden: ' . $sort . ' (' . strtoupper($direction) . ')');

        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

        $headers = [
            'Sprite',
            'Nombre',
            'Tipo',
            'Altura',
            'Peso',
            'Speed',
            'Attack',
            'Defense',
            'HP',
            'Estado',
        ];
        foreach ($headers as $columnIndex => $header) {
            $columnLetter = $this->columnLetter($columnIndex + 1);
            $sheet->setCellValue($columnLetter . self::HEADER_ROW, $header);
        }

        $headerRange = 'A' . self::HEADER_ROW . ':J' . self::HEADER_ROW;
        $sheet->getStyle($headerRange)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '6366F1'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
            ],
        ]);

        $temporarySpriteFiles = [];
        $row = self::HEADER_ROW + 1;
        foreach ($pokemons as $pokemon) {
            $this->embedSprite($sheet, $pokemon, $row, $temporarySpriteFiles);

            $sheet->setCellValue('B' . $row, $pokemon->getName());
            $sheet->setCellValue('C' . $row, $pokemon->getType()?->getName() ?? '—');
            $sheet->setCellValue('D' . $row, $pokemon->getHeight());
            $sheet->setCellValue('E' . $row, $pokemon->getWeight());
            $sheet->setCellValue('F' . $row, $pokemon->getSpeed() ?? '—');
            $sheet->setCellValue('G' . $row, $pokemon->getAttack() ?? '—');
            $sheet->setCellValue('H' . $row, $pokemon->getDefense() ?? '—');
            $sheet->setCellValue('I' . $row, $pokemon->getHealthPoints() ?? '—');
            $sheet->setCellValue('J' . $row, $pokemon->isHidden() ? 'Oculto' : 'Visible');
            $sheet->getRowDimension($row)->setRowHeight(self::DATA_ROW_HEIGHT);
            ++$row;
        }

        $sheet->getColumnDimension('A')->setWidth(12);
        foreach (range('B', 'J') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $sheet->freezePane('A' . (self::HEADER_ROW + 1));

        try {
            return $this->writeSpreadsheetToString($spreadsheet);
        } finally {
            foreach ($temporarySpriteFiles as $temporarySpriteFile) {
                unlink($temporarySpriteFile);
            }
        }
    }

    /**
     * @param list<string> $temporarySpriteFiles
     */
    private function embedSprite(
        \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet,
        Pokemon $pokemon,
        int $row,
        array &$temporarySpriteFiles,
    ): void {
        $spriteImage = $this->spriteImageLoader->load($pokemon);
        if (null === $spriteImage) {
            return;
        }

        if ($spriteImage['temporary']) {
            $temporarySpriteFiles[] = $spriteImage['path'];
        }

        $drawing = new Drawing();
        $drawing->setPath($spriteImage['path']);
        $drawing->setCoordinates('A' . $row);
        $drawing->setHeight(self::SPRITE_HEIGHT);
        $drawing->setOffsetX(8);
        $drawing->setOffsetY(4);
        $drawing->setWorksheet($sheet);
    }

    private function writeSpreadsheetToString(Spreadsheet $spreadsheet): string
    {
        $writer = new Xlsx($spreadsheet);
        $stream = fopen('php://temp', 'r+');

        if (false === $stream) {
            throw new ExcelGenerationException('No se pudo crear el archivo temporal para la exportación Excel.');
        }

        try {
            $writer->save($stream);
            rewind($stream);
            $content = stream_get_contents($stream);

            if (false === $content) {
                throw new ExcelGenerationException('No se pudo leer el contenido del archivo Excel generado.');
            }

            return $content;
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }

    private function columnLetter(int $columnIndex): string
    {
        $letter = '';
        while ($columnIndex > 0) {
            $remainder = ($columnIndex - 1) % 26;
            $letter = chr(65 + $remainder) . $letter;
            $columnIndex = intdiv($columnIndex - 1, 26);
        }

        return $letter;
    }
}
