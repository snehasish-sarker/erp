<?php

declare(strict_types=1);

namespace App\Services\Operations;

use App\Services\Management\ProductionReadinessService;
use App\Support\Operations\OperationsChecklistRegistry;
use Carbon\CarbonImmutable;

final class DeploymentPreflightService
{
    public function __construct(
        private readonly ProductionReadinessService $productionReadinessService,
        private readonly OperationsHealthService $operationsHealthService,
        private readonly DatabasePerformanceDiagnosticsService $performanceDiagnosticsService,
        private readonly SecurityHardeningService $securityHardeningService,
        private readonly OperationsChecklistRegistry $checklistRegistry,
    ) {
    }

    /** @return array<string, mixed> */
    public function run(): array
    {
        $readiness = $this->productionReadinessService->audit();
        $health = $this->operationsHealthService->run();
        $performance = $this->performanceDiagnosticsService->run();
        $security = $this->securityHardeningService->run();

        $ready = (bool) ($readiness['summary']['ready'] ?? false)
            && (bool) ($health['summary']['healthy'] ?? false)
            && (bool) ($security['summary']['secure'] ?? false);

        return [
            'generated_at' => CarbonImmutable::now()->toIso8601String(),
            'environment' => app()->environment(),
            'ready' => $ready,
            'production_readiness' => $readiness,
            'operations_health' => $health,
            'performance' => $performance,
            'security' => $security,
            'cutover_checklist' => $this->checklistRegistry->cutover(),
            'post_deployment_checklist' => $this->checklistRegistry->postDeployment(),
        ];
    }
}
