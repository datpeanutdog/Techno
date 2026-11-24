<?php

namespace App\Http\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

class StoreFetchRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'item_name' => ['required', 'string', 'max:90'],
            'details' => ['required', 'string', 'max:1000'],
            'budget' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'delivery_location' => ['required', 'string', 'max:100'],
            'duration_value' => [
                'required',
                'integer',
                'min:1',
                'max:1440',
                function (string $attribute, mixed $value, Closure $fail): void {
                    $durationInMinutes = $this->durationInMinutes($value, $this->input('duration_unit'));

                    if ($durationInMinutes !== null && ($durationInMinutes < 5 || $durationInMinutes > 1440)) {
                        $fail('Choose a live duration between 5 minutes and 1 day.');
                    }
                },
            ],
            'duration_unit' => ['required', 'string', 'in:minutes,hours,days'],
            'delivery_duration_value' => [
                'required',
                'integer',
                'min:1',
                'max:43200',
                function (string $attribute, mixed $value, Closure $fail): void {
                    $deliveryDurationInMinutes = $this->durationInMinutes(
                        $value,
                        $this->input('delivery_duration_unit'),
                    );
                    $liveDurationInMinutes = $this->durationInMinutes(
                        $this->input('duration_value'),
                        $this->input('duration_unit'),
                    );

                    if ($deliveryDurationInMinutes === null) {
                        return;
                    }

                    if ($deliveryDurationInMinutes < 5 || $deliveryDurationInMinutes > 43200) {
                        $fail('Choose a delivery deadline from 5 minutes to 30 days after posting.');

                        return;
                    }

                    if ($liveDurationInMinutes !== null && $deliveryDurationInMinutes <= $liveDurationInMinutes) {
                        $fail('The delivery deadline must be later than the live request expiration.');
                    }
                },
            ],
            'delivery_duration_unit' => ['required', 'string', 'in:minutes,hours,days'],
        ];
    }

    /**
     * @return array{expires_at: Carbon, delivery_deadline: Carbon}
     */
    public function deadlineAttributes(): array
    {
        $now = now();
        $validated = $this->validated();

        return [
            'expires_at' => $now->copy()->addMinutes($this->validatedDurationInMinutes(
                $validated,
                'duration_value',
                'duration_unit',
            )),
            'delivery_deadline' => $now->copy()->addMinutes($this->validatedDurationInMinutes(
                $validated,
                'delivery_duration_value',
                'delivery_duration_unit',
            )),
        ];
    }

    private function durationInMinutes(mixed $value, mixed $unit): ?int
    {
        if (! is_numeric($value) || ! is_string($unit)) {
            return null;
        }

        $multiplier = [
            'minutes' => 1,
            'hours' => 60,
            'days' => 1440,
        ][$unit] ?? null;

        return $multiplier === null ? null : (int) $value * $multiplier;
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function validatedDurationInMinutes(array $validated, string $valueField, string $unitField): int
    {
        $multiplier = match ($validated[$unitField]) {
            'minutes' => 1,
            'hours' => 60,
            'days' => 1440,
        };

        return (int) $validated[$valueField] * $multiplier;
    }
}
