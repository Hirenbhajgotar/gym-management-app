<?php

namespace App\Helpers;

class BmiHelper
{
    /**
     * Calculate BMI based on weight and height.
     *
     * @param  float  $weight  Weight in kilograms.
     * @param  float  $height  Height in centimeters.
     * @return float|null BMI value or null if height is invalid.
     */
    public static function calculateBmi($weight, $height): ?float
    {
        $heightInMeters = $height / 100;
        if ($heightInMeters > 0) {
            $bmi = round($weight / ($heightInMeters ** 2), 1);

            return $bmi;
        }

        return null;
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
}
