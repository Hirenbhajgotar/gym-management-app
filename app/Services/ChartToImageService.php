<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

class ChartToImageService
{
    protected ImageManager $manager;

    public function __construct()
    {
        $this->manager = new ImageManager(new Driver());
    }

    /**
     * Convert chart data to base64 encoded image for PDF inclusion.
     */
    public function convertChartToBase64(array $chartData, string $chartType = 'bar', int $width = 800, int $height = 400): string
    {
        // Create a canvas for the chart
        $image = $this->manager->create($width, $height);
        $image->fill('#ffffff');

        // Generate chart visualization
        $this->drawChart($image, $chartData, $chartType, $width, $height);

        // Convert to base64
        return $image->toPng()->toDataUri();
    }

    /**
     * Save chart as image file and return path.
     */
    public function saveChartAsImage(array $chartData, string $filename, string $chartType = 'bar', int $width = 800, int $height = 400): string
    {
        $image = $this->manager->create($width, $height);
        $image->fill('#ffffff');

        $this->drawChart($image, $chartData, $chartType, $width, $height);

        $path = "charts/{$filename}.png";
        Storage::disk('public')->put($path, (string) $image->toPng());

        return Storage::url($path);
    }

    /**
     * Draw chart on image canvas.
     */
    private function drawChart($image, array $chartData, string $chartType, int $width, int $height): void
    {
        if (blank($chartData)) {
            // Draw "No Data Available" background
            $image->drawRectangle(0, 0, function ($rectangle) use ($width, $height) {
                $rectangle->size($width, $height);
                $rectangle->background('#f3f4f6');
            });

            return;
        }

        $margin = 60;
        $chartWidth = $width - (2 * $margin);
        $chartHeight = $height - (2 * $margin);

        // Draw axes using width() instead of thickness()
        $image->drawLine(function ($line) use ($margin, $height) {
            $line->from($margin, $margin);
            $line->to($margin, $height - $margin);
            $line->color('#333333');
            $line->width(2);
        });

        $image->drawLine(function ($line) use ($margin, $width, $height) {
            $line->from($margin, $height - $margin);
            $line->to($width - $margin, $height - $margin);
            $line->color('#333333');
            $line->width(2);
        });

        switch ($chartType) {
            case 'bar':
                $this->drawBarChart($image, $chartData, $margin, $chartWidth, $chartHeight);
                break;
            case 'line':
                $this->drawLineChart($image, $chartData, $margin, $chartWidth, $chartHeight);
                break;
            case 'pie':
            case 'doughnut':
                $this->drawDoughnutChart($image, $chartData, $width, $height);
                break;
            default:
                $this->drawBarChart($image, $chartData, $margin, $chartWidth, $chartHeight);
        }
    }

    /**
     * Draw bar chart with labels.
     */
    private function drawBarChart($image, array $chartData, int $margin, int $chartWidth, int $chartHeight): void
    {
        $values = $this->extractValues($chartData);
        $labels = $this->extractLabels($chartData);

        if (blank($values)) {
            return;
        }

        $maxValue = max($values);
        $barCount = count($values);
        $barWidth = $chartWidth / $barCount * 0.7;
        $barSpacing = $chartWidth / $barCount * 0.3;

        $colors = ['#2563eb', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899', '#06b6d4', '#84cc16'];

        foreach ($values as $index => $value) {
            $barHeight = $maxValue > 0 ? ($value / $maxValue) * $chartHeight : 0;
            $x = $margin + ($index * ($barWidth + $barSpacing)) + $barSpacing / 2;
            $y = $margin + $chartHeight - $barHeight;

            // Draw bar
            $image->drawRectangle($x, $y, function ($rectangle) use ($barWidth, $barHeight, $colors, $index) {
                $rectangle->size($barWidth, $barHeight);
                $rectangle->background($colors[$index % count($colors)]);
            });

            // Draw value label on top of bar
            if ($barHeight > 20) {
                $valueLabel = is_numeric($value) ? number_format($value) : (string) $value;
                $this->drawText($image, $valueLabel, $x + $barWidth / 2, $y - 8, 12, '#333333', 'center');
            }

            // Draw x-axis label
            $label = $labels[$index] ?? 'Item ' . ($index + 1);
            $this->drawText($image, $label, $x + $barWidth / 2, $margin + $chartHeight + 15, 11, '#666666', 'center');
        }
    }

    /**
     * Draw line chart with labels.
     */
    private function drawLineChart($image, array $chartData, int $margin, int $chartWidth, int $chartHeight): void
    {
        $values = $this->extractValues($chartData);
        $labels = $this->extractLabels($chartData);

        if (blank($values) || count($values) < 2) {
            return;
        }

        $maxValue = max($values);

        // Guard against division by zero when all values are 0
        if ($maxValue === 0) {
            $this->drawNoDataMessage($image, $margin * 2 + $chartWidth, $margin * 2 + $chartHeight, 'No data available');

            return;
        }
        $pointSpacing = $chartWidth / (count($values) - 1);

        $points = [];
        foreach ($values as $index => $value) {
            $x = $margin + ($index * $pointSpacing);
            $y = $margin + $chartHeight - (($value / $maxValue) * $chartHeight);
            $points[] = ['x' => $x, 'y' => $y];

            // Draw point as small circle
            $image->drawCircle($x, $y, function ($circle) {
                $circle->radius(4);
                $circle->background('#2563eb');
            });

            // Draw value label above point
            $valueLabel = is_numeric($value) ? number_format($value) : (string) $value;
            $this->drawText($image, $valueLabel, $x, $y - 15, 10, '#2563eb', 'center');

            // Draw x-axis label
            $label = $labels[$index] ?? 'P' . ($index + 1);
            $this->drawText($image, $label, $x, $margin + $chartHeight + 15, 10, '#666666', 'center');
        }

        // Draw lines between points using width() instead of thickness()
        for ($i = 0; $i < count($points) - 1; $i++) {
            $image->drawLine(function ($line) use ($points, $i) {
                $line->from($points[$i]['x'], $points[$i]['y']);
                $line->to($points[$i + 1]['x'], $points[$i + 1]['y']);
                $line->color('#2563eb');
                $line->width(2);
            });
        }
    }

    /**
     * Draw doughnut chart with labels and percentages.
     */
    private function drawDoughnutChart($image, array $chartData, int $width, int $height): void
    {
        $values = $this->extractValues($chartData);
        $labels = $this->extractLabels($chartData);

        if (blank($values)) {
            return;
        }

        $total = array_sum($values);

        // Guard against division by zero when all values are 0
        if ($total === 0) {
            $this->drawNoDataMessage($image, $width, $height, 'No data available');

            return;
        }

        $centerX = $width / 2 - 100;
        $centerY = $height / 2;
        $outerRadius = min($width, $height) / 3;
        $innerRadius = $outerRadius * 0.6;

        $colors = ['#2563eb', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899', '#06b6d4', '#84cc16'];

        $startAngle = 0;

        foreach ($values as $index => $value) {
            $percentage = $total > 0 ? ($value / $total) * 100 : 0;
            $color = $colors[$index % count($colors)];

            // Draw segment as rectangle around the doughnut
            $angle = 2 * M_PI * ($value / $total);
            $midAngle = $startAngle + $angle / 2;

            $segmentX = $centerX + cos($midAngle) * (($outerRadius + $innerRadius) / 2);
            $segmentY = $centerY + sin($midAngle) * (($outerRadius + $innerRadius) / 2);

            // Draw colored arc segment
            $this->drawArcSegment($image, $centerX, $centerY, $innerRadius, $outerRadius, $startAngle, $startAngle + $angle, $color);

            $startAngle += $angle;
        }

        // Draw inner white circle for doughnut effect
        $image->drawCircle($centerX, $centerY, function ($circle) use ($innerRadius) {
            $circle->radius($innerRadius);
            $circle->background('#ffffff');
        });

        // Draw legend with labels and percentages on the right side
        $legendX = $width - 180;
        $legendY = 40;
        $legendBoxSize = 18;
        $legendSpacing = 35;

        foreach ($values as $index => $value) {
            $label = $labels[$index] ?? 'Item ' . ($index + 1);
            $percentage = $total > 0 ? round(($value / $total) * 100, 1) : 0;
            $color = $colors[$index % count($colors)];

            // Draw color box
            $image->drawRectangle($legendX, $legendY + ($index * $legendSpacing), function ($rectangle) use ($legendBoxSize, $color) {
                $rectangle->size($legendBoxSize, $legendBoxSize);
                $rectangle->background($color);
            });

            // Draw label
            $this->drawText($image, $label, $legendX + $legendBoxSize + 8, $legendY + ($index * $legendSpacing) + 6, 12, '#333333', 'left');

            // Draw percentage
            $percentageText = $percentage . '%';
            $this->drawText($image, $percentageText, $legendX + $legendBoxSize + 8, $legendY + ($index * $legendSpacing) + 20, 10, '#666666', 'left');
        }

        // Draw total in center
        $totalLabel = is_numeric($total) ? number_format($total) : (string) $total;
        $this->drawText($image, $totalLabel, $centerX, $centerY - 8, 16, '#333333', 'center');
        $this->drawText($image, 'Total', $centerX, $centerY + 12, 12, '#666666', 'center');
    }

    /**
     * Draw arc segment for doughnut chart.
     */
    private function drawArcSegment($image, float $centerX, float $centerY, float $innerRadius, float $outerRadius, float $startAngle, float $endAngle, string $color): void
    {
        $points = [];
        $steps = 20;

        for ($i = 0; $i <= $steps; $i++) {
            $angle = $startAngle + ($endAngle - $startAngle) * ($i / $steps);
            $points[] = [
                'x' => $centerX + cos($angle) * $outerRadius,
                'y' => $centerY + sin($angle) * $outerRadius,
            ];
        }

        for ($i = $steps; $i >= 0; $i--) {
            $angle = $startAngle + ($endAngle - $startAngle) * ($i / $steps);
            $points[] = [
                'x' => $centerX + cos($angle) * $innerRadius,
                'y' => $centerY + sin($angle) * $innerRadius,
            ];
        }

        if (count($points) >= 3) {
            $image->drawPolygon(function ($polygon) use ($points, $color) {
                foreach ($points as $point) {
                    $polygon->point((int) $point['x'], (int) $point['y']);
                }
                $polygon->background($color);
            });
        }
    }

    /**
     * Draw text on image with a font.
     */
    private function drawText($image, string $text, float $x, float $y, int $size, string $color, string $align): void
    {
        $text = trim(substr($text, 0, 20));

        if (blank($text)) {
            return;
        }

        // Try to use a font file if available, otherwise use default
        $fontPaths = [
            storage_path('fonts/arial.ttf'),
            base_path('resources/fonts/arial.ttf'),
            '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
        ];

        $fontFile = null;
        foreach ($fontPaths as $path) {
            if (file_exists($path)) {
                $fontFile = $path;
                break;
            }
        }

        $image->text($text, $x, $y, function ($font) use ($size, $color, $align, $fontFile) {
            if ($fontFile) {
                $font->filename($fontFile);
            }
            $font->size($size);
            $font->align($align);
            $font->color($color);
        });
    }

    /**
     * Extract numeric values from chart data.
     */
    private function extractValues(array $chartData): array
    {
        $values = [];

        foreach ($chartData as $item) {
            if (is_array($item)) {
                if (isset($item['value'])) {
                    $values[] = is_numeric($item['value']) ? $item['value'] : 0;
                } elseif (isset($item['count'])) {
                    $values[] = is_numeric($item['count']) ? $item['count'] : 0;
                } elseif (isset($item['amount'])) {
                    $values[] = is_numeric($item['amount']) ? $item['amount'] : 0;
                } elseif (isset($item['revenue'])) {
                    $values[] = is_numeric($item['revenue']) ? $item['revenue'] : 0;
                } elseif (isset($item['percentage'])) {
                    $values[] = is_numeric($item['percentage']) ? $item['percentage'] : 0;
                } elseif (isset($item['conversion_rate'])) {
                    $values[] = is_numeric($item['conversion_rate']) ? $item['conversion_rate'] : 0;
                } elseif (isset($item['new_leads']) && isset($item['converted'])) {
                    $total = $item['new_leads'] + $item['converted'];
                    $values[] = $total > 0 ? round(($item['converted'] / $total) * 100, 2) : 0;
                } elseif (isset($item['new_leads'])) {
                    $values[] = is_numeric($item['new_leads']) ? $item['new_leads'] : 0;
                } elseif (isset($item['converted'])) {
                    $values[] = is_numeric($item['converted']) ? $item['converted'] : 0;
                } else {
                    foreach ($item as $key => $val) {
                        if (is_numeric($val) && ! in_array($key, ['month', 'label', 'name', 'risk_level', 'hour', 'date', 'period'])) {
                            $values[] = (float) $val;
                            break;
                        }
                    }
                }
            }
        }

        return $values;
    }

    /**
     * Extract labels from chart data.
     */
    private function extractLabels(array $chartData): array
    {
        $labels = [];

        foreach ($chartData as $item) {
            if (is_array($item)) {
                if (isset($item['label'])) {
                    $labels[] = $item['label'];
                } elseif (isset($item['name'])) {
                    $labels[] = $item['name'];
                } elseif (isset($item['month'])) {
                    $labels[] = $item['month'];
                } elseif (isset($item['risk_level'])) {
                    $labels[] = $item['risk_level'];
                } elseif (isset($item['status'])) {
                    $labels[] = $item['status'];
                } else {
                    $labels[] = 'Item ' . (count($labels) + 1);
                }
            }
        }

        return $labels;
    }

    /**
     * Draw "No Data" message on the image when chart has no data to display.
     */
    private function drawNoDataMessage($image, int $width, int $height, string $message = 'No data available'): void
    {
        $image->text($message, $width / 2, $height / 2, function ($font) {
            $font->size(24);
            $font->color('#666666');
            $font->align('center');
            $font->valign('middle');
        });
    }
}
