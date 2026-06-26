<?php

namespace App\Http\Controllers;

use App\Interfaces\ShareableReport;
use App\Models\ShareableReportLink;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class SharedReportController extends Controller
{
    public function show(string $token): View|Response
    {
        $link = ShareableReportLink::with(['gym', 'user'])
            ->where('token', $token)
            ->valid()
            ->first();

        abort_unless($link, 404, 'Shared report not found or has expired');

        // Get the report class
        $reportClass = $link->report_type;

        try {
            $page = resolve($reportClass);
        } catch (\Throwable $e) {
            abort(404, 'Report type not found');
        }

        // Set context for the report
        if (property_exists($page, 'manualGymId')) {
            $page->manualGymId = $link->gym_id;
        }

        if (property_exists($page, 'filters')) {
            // $page->filters = $link->filters ?? [];

            if (method_exists($page, 'mount')) {
                $page->mount();
                if ($link->filters) {
                    $page->filters = array_merge($page->filters ?? [], $link->filters ?? []);
                }
            }
        }

        // Prepare data for the view
        $data = $this->getReportData($page);
        $title = $page instanceof ShareableReport ? $page->getSharedReportTitle() : 'Report';

        return view('shared-reports.show', [
            'link' => $link,
            'report' => $page,
            'data' => $data,
            'title' => $title,
            'generatedAt' => now(),
        ]);
    }

    private function getReportData($page): array
    {
        if ($page instanceof ShareableReport) {
            return $page->getSharedReportData();
        }

        return ['stats' => []];
    }
}
