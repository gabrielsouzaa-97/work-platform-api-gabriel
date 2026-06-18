<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use App\Modules\Onboarding\Enums\OnboardingStep;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class OnboardingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->state->value,
            'current_step' => $this->current_step?->value,
            'steps' => $this->formattedSteps(),
            'tenant_slug' => $this->tenant_slug,
            'correlation_id' => $this->correlation_id,
        ];
    }

    /**
     * @return list<array{name: string, status: string}>
     */
    private function formattedSteps(): array
    {
        $stored = is_array($this->steps) ? $this->steps : [];
        $items = [];

        foreach ($stored as $name => $meta) {
            if (! is_string($name)) {
                continue;
            }

            $items[] = [
                'name' => $name,
                'status' => $this->resolveStepStatus($name, is_array($meta) ? $meta : []),
            ];
        }

        return $items;
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function resolveStepStatus(string $name, array $meta): string
    {
        if (isset($meta['status']) && is_string($meta['status'])) {
            return $meta['status'];
        }

        $stepEnum = OnboardingStep::tryFrom($name);
        $current = $this->current_step;

        if ($stepEnum === null || $current === null) {
            return isset($meta['job_id']) ? 'running' : 'pending';
        }

        $order = array_map(
            static fn (OnboardingStep $step): string => $step->value,
            OnboardingStep::cases(),
        );
        $nameIdx = array_search($name, $order, true);
        $currentIdx = array_search($current->value, $order, true);

        if ($nameIdx === false || $currentIdx === false) {
            return 'running';
        }

        if ($nameIdx < $currentIdx) {
            return 'completed';
        }

        if ($nameIdx === $currentIdx) {
            return 'running';
        }

        return 'pending';
    }
}
