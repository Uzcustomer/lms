<?php

use App\Exports\TimetableUnplacedDiagnosticsExport;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

it('builds a readable unplaced-card diagnostics worksheet', function () {
    $row = array_fill(0, 23, 'qiymat');
    $export = new TimetableUnplacedDiagnosticsExport([$row]);
    $sheet = (new Spreadsheet())->getActiveSheet();

    $export->styles($sheet);

    expect($export->title())->toBe('Joylashmagan kartalar')
        ->and($export->headings())->toHaveCount(23)
        ->and($export->array())->toBe([$row])
        ->and($export->columnWidths())->toHaveCount(23)
        ->and($sheet->getFreezePane())->toBe('A2')
        ->and($sheet->getAutoFilter()->getRange())->toBe('A1:W2');
});
