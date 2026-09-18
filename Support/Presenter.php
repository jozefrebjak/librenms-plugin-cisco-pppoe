<?php

/*
 * Presenter.php
 *
 * Small formatting helpers shared by the hooks, so the blade views stay free of logic.
 */

namespace App\Plugins\CiscoPppoe\Support;

final class Presenter
{
    /**
     * Bootstrap contextual class for a session utilisation percentage.
     */
    public static function utilizationClass(?float $utilization): string
    {
        return match (true) {
            $utilization === null => 'info',
            $utilization >= 90 => 'danger',
            $utilization >= 75 => 'warning',
            default => 'success',
        };
    }

    /**
     * Progress bar width, clamped so an over-subscribed BRAS cannot break the layout.
     */
    public static function utilizationWidth(?float $utilization): float
    {
        if ($utilization === null) {
            return 0.0;
        }

        return max(0.0, min(100.0, $utilization));
    }

    /**
     * Decorate a statistics array with the values the views need for display.
     *
     * @param  array<string, mixed>  $statistics
     * @return array<string, mixed>
     */
    public static function decorate(array $statistics): array
    {
        $utilization = $statistics['utilization'] ?? null;

        $statistics['utilization_class'] = self::utilizationClass($utilization);
        $statistics['utilization_width'] = self::utilizationWidth($utilization);

        return $statistics;
    }
}
