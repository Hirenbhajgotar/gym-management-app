<?php

declare(strict_types=1);

namespace App\Interfaces;

interface ShareableReport
{
    /**
     * Get the data required for the shared report view.
     */
    public function getSharedReportData(): array;

    /**
     * Get the title of the shared report.
     */
    public function getSharedReportTitle(): string;
}
