<?php

namespace App\Filament\Widgets;

use App\Filament\Support\AdminResource;
use App\Models\AuditLog;
use Carbon\CarbonImmutable;
use Filament\Widgets\ChartWidget;

class ActivityTrendChart extends ChartWidget
{
    protected ?string $heading = 'Activity trend';

    protected ?string $description = 'Recorded platform and admin events over the last seven days';

    protected string $color = 'primary';

    public static function canView(): bool
    {
        return AdminResource::userHasPermission('view activity logs');
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $start = CarbonImmutable::today()->subDays(6);
        $labels = [];
        $counts = [];

        for ($day = $start; $day->lessThanOrEqualTo(CarbonImmutable::today()); $day = $day->addDay()) {
            $labels[] = $day->format('D');
            $counts[] = AuditLog::query()
                ->where('created_at', '>=', $day->startOfDay())
                ->where('created_at', '<', $day->addDay()->startOfDay())
                ->count();
        }

        return [
            'datasets' => [
                [
                    'label' => 'Activity events',
                    'data' => $counts,
                    'borderColor' => '#0f766e',
                    'backgroundColor' => 'rgba(15, 118, 110, 0.12)',
                    'fill' => true,
                    'tension' => 0.35,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => ['legend' => ['display' => false]],
            'scales' => ['y' => ['beginAtZero' => true, 'ticks' => ['precision' => 0]]],
        ];
    }
}
