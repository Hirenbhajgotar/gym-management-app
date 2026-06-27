<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Facades\Excel;

class ImportController extends Controller
{
    public function downloadSample($type)
    {
        $headings = [
            'name',
            'email',
            'password',
            'address',
            'phone',
            'gender',
            'date_of_birth',
        ];

        $data = [
            $headings,
            ['John Doe', 'john.doe@example.com', 'password', '123 Main St', '1234567890', 'male', '1990-01-01'],
        ];

        $export = new class($data) implements FromArray
        {
            public function __construct(private readonly array $data) {}

            public function array(): array
            {
                return $this->data;
            }
        };

        return Excel::download($export, $type . '_sample.xlsx');
    }
}
