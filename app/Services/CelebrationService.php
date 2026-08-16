<?php

namespace App\Services;

use App\Models\Customer;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class CelebrationService
{
    public function forDate(CarbonInterface $date): Collection
    {
        return $this->events($date)
            ->filter(fn (array $event): bool => $event['occurs_on']->isSameDay($date))
            ->values();
    }

    public function forMonth(CarbonInterface $date): Collection
    {
        return $this->events($date)
            ->filter(fn (array $event): bool => $event['occurs_on']->month === $date->month)
            ->sortBy(fn (array $event): string => $event['occurs_on']->format('md').$event['customer']->name)
            ->values();
    }

    private function events(CarbonInterface $reference): Collection
    {
        return Customer::query()
            ->where('status', 'active')
            ->where(fn ($query) => $query->whereNotNull('birth_date')->orWhereNotNull('anniversary_date'))
            ->orderBy('name')
            ->get()
            ->flatMap(function (Customer $customer) use ($reference): array {
                $events = [];

                if ($customer->birth_date) {
                    $events[] = $this->event($customer, 'birthday', 'Cumpleaños', $customer->birth_date, $reference);
                }

                if ($customer->anniversary_date) {
                    $events[] = $this->event($customer, 'anniversary', 'Aniversario', $customer->anniversary_date, $reference);
                }

                return $events;
            });
    }

    private function event(Customer $customer, string $type, string $label, CarbonInterface $original, CarbonInterface $reference): array
    {
        $daysInMonth = CarbonImmutable::create($reference->year, $original->month, 1)->daysInMonth;
        $occursOn = CarbonImmutable::create(
            $reference->year,
            $original->month,
            min($original->day, $daysInMonth),
            0,
            0,
            0,
            $reference->timezone,
        );

        return [
            'customer' => $customer,
            'type' => $type,
            'label' => $label,
            'occurs_on' => $occursOn,
            'years' => max(0, $reference->year - $original->year),
        ];
    }
}
