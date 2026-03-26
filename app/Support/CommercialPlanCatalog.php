<?php

namespace App\Support;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

class CommercialPlanCatalog
{
    /**
     * @return array<string, mixed>
     */
    public function welcomeData(): array
    {
        return [
            'whatsappUrl' => (string) config('commercial_plans.whatsapp_url'),
            'heroHighlights' => (array) config('commercial_plans.hero_highlights', []),
            'coreFeatures' => (array) config('commercial_plans.core_features', []),
            'planSummaries' => (array) config('commercial_plans.plan_summaries', []),
            'pricingNotes' => (array) config('commercial_plans.pricing_notes', []),
            'businessTypes' => (array) config('commercial_plans.business_types', []),
            'pricingSections' => (array) config('commercial_plans.pricing_sections', []),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function implementationPlans(): array
    {
        return $this->plansForSection('implementation');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function maintenancePlans(): array
    {
        return $this->plansForSection('maintenance');
    }

    /**
     * @return list<string>
     */
    public function implementationCodes(): array
    {
        return array_values(array_filter(array_map(
            fn (array $plan): ?string => Arr::get($plan, 'code'),
            $this->implementationPlans()
        )));
    }

    /**
     * @return list<string>
     */
    public function maintenanceCodes(): array
    {
        return array_values(array_filter(array_map(
            fn (array $plan): ?string => Arr::get($plan, 'code'),
            $this->maintenancePlans()
        )));
    }

    /**
     * @return list<string>
     */
    public function allBillingCodes(): array
    {
        return array_values(array_merge($this->implementationCodes(), $this->maintenanceCodes()));
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findImplementationPlan(?string $code): ?array
    {
        return $this->findPlan('implementation', $code);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findMaintenancePlan(?string $code): ?array
    {
        return $this->findPlan('maintenance', $code);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function plansForSection(string $key): array
    {
        /** @var Collection<int, array<string, mixed>> $sections */
        $sections = collect((array) config('commercial_plans.pricing_sections', []));

        /** @var array<string, mixed>|null $section */
        $section = $sections->first(
            fn (array $item): bool => (string) ($item['key'] ?? '') === $key
        );

        return array_values((array) ($section['plans'] ?? []));
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findPlan(string $sectionKey, ?string $code): ?array
    {
        if ($code === null || trim($code) === '') {
            return null;
        }

        /** @var Collection<int, array<string, mixed>> $plans */
        $plans = collect($this->plansForSection($sectionKey));

        /** @var array<string, mixed>|null $plan */
        $plan = $plans->first(
            fn (array $item): bool => (string) ($item['code'] ?? '') === $code
        );

        return $plan;
    }
}
