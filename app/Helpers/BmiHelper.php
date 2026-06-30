<?php

declare(strict_types=1);

namespace App\Helpers;

class BmiHelper
{
    /**
     * Calculate BMI based on weight and height.
     *
     * @param  float  $weight  Weight in kilograms.
     * @param  float  $height  Height in centimeters.
     * @return float|null BMI value or null if weight/height is invalid.
     */
    public static function calculateBmi(float $weight, float $height): ?float
    {
        if ($weight <= 0 || $height <= 0) {
            return null;
        }

        $heightInMeters = $height / 100;

        return round($weight / ($heightInMeters ** 2), 1);
    }

    /**
     * Get BMI status based on BMI value.
     *
     * @param  float  $bmi  BMI value.
     * @return string BMI status.
     */
    public static function getStatus(float $bmi): string
    {
        return match (true) {
            $bmi < 18.5 => 'Underweight',
            $bmi < 25 => 'Normal',
            $bmi < 30 => 'Overweight',
            default => 'Obese',
        };
    }

    /**
     * Get BMI color based on BMI value.
     *
     * @param  float  $bmi  BMI value.
     * @return string BMI color.
     */
    public static function getColor(float $bmi): string
    {
        return match (true) {
            $bmi < 18.5 => 'warning',
            $bmi < 25 => 'success',
            $bmi < 30 => 'warning',
            default => 'danger',
        };
    }

    /**
     * Calculate a health score (0-100) based on BMI.
     * Score is 100 for Normal range (18.5-25).
     * Deducts points for deviations.
     *
     * @param  float  $bmi  BMI value.
     * @return int Health score (0-100).
     */
    public static function calculateHealthScore(?float $bmi): int
    {
        if (! $bmi) {
            return 0;
        }

        if ($bmi >= 18.5 && $bmi <= 25) {
            return 100;
        }

        if ($bmi < 18.5) {
            // Underweight penalty: Higher penalty per unit because range is smaller
            // Deviation of 2.5 (BMI 16) results in ~25 points penalty (Score 75)
            // Deviation of 4.5 (BMI 14) results in ~45 points penalty (Score 55)
            $deviation = 18.5 - $bmi;
            $penalty = $deviation * 10;
        } else {
            // Overweight penalty
            // Deviation of 5 (BMI 30) results in 20 points penalty (Score 80)
            // Deviation of 15 (BMI 40) results in 60 points penalty (Score 40)
            $deviation = $bmi - 25;
            $penalty = $deviation * 4;
        }

        return (int) max(0, round(100 - $penalty));
    }
}
