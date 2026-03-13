<?php

namespace App\Support;

class ProductMeasurement
{
    public static function normalizeWeightUnit(?string $unitType, mixed $weightUnit): ?string
    {
        if ($unitType !== 'weight') {
            return null;
        }

        return in_array($weightUnit, ['kg', 'g'], true) ? $weightUnit : 'kg';
    }

    public static function quantityLabel(?string $unitType, ?string $weightUnit): string
    {
        if ($unitType !== 'weight') {
            return 'un';
        }

        return $weightUnit === 'g' ? 'g' : 'kg';
    }

    public static function priceLabel(?string $unitType, ?string $weightUnit): string
    {
        if ($unitType !== 'weight') {
            return 'por unidad';
        }

        return $weightUnit === 'g' ? 'por 100 g' : 'por kg';
    }

    public static function typeLabel(?string $unitType, ?string $weightUnit): string
    {
        if ($unitType !== 'weight') {
            return 'Unidad';
        }

        return $weightUnit === 'g' ? 'Peso (g)' : 'Peso (kg)';
    }

    public static function quantityStep(?string $unitType, ?string $weightUnit): string
    {
        if ($unitType !== 'weight') {
            return '1';
        }

        return $weightUnit === 'g' ? '1' : '0.001';
    }

    public static function quantityMin(?string $unitType, ?string $weightUnit): string
    {
        if ($unitType !== 'weight') {
            return '1';
        }

        return $weightUnit === 'g' ? '1' : '0.001';
    }

    public static function calculateSubtotal(float $quantity, float $unitPrice, ?string $unitType, ?string $weightUnit): float
    {
        $normalizedWeightUnit = self::normalizeWeightUnit($unitType, $weightUnit);

        if ($unitType === 'weight' && $normalizedWeightUnit === 'g') {
            return round(($quantity * $unitPrice) / 100, 2);
        }

        return round($quantity * $unitPrice, 2);
    }
}
