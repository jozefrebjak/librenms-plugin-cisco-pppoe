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
     * What the CISCO-PPPOE-MIB session state abbreviations mean.
     *
     * Wording follows the MIB descriptions of cPppoePtaSessions,
     * cPppoeFwdedSessions and cPppoeTransSessions. Kept in one place so the device
     * overview panel and the plugin page cannot drift apart.
     *
     * @var array<string, string>
     */
    public const GLOSSARY = [
        'Active' => 'All PPPoE sessions currently on the device: PTA plus FWDED plus TRANS.',
        'PTA' => 'PPP Termination Aggregation — the session terminates on this BRAS and its traffic is routed locally.',
        'FWDED' => 'Forwarded — the session is not terminated here but handed on, typically over an L2TP tunnel to an LNS.',
        'TRANS' => 'Transient — the session is still negotiating and is not up yet.',
        'Limit' => 'Configured session ceiling (cPppoeSystemMaxAllowedSessions), falling back to the trap threshold when no ceiling is set.',
        'Loss threshold' => 'Low watermark for the interface: when the session count drops below it, the BRAS sends a trap.',
    ];

    /**
     * Same terms in a few words, for places where a full sentence does not fit.
     *
     * The v1 layout does not initialise Bootstrap tooltips, so a title attribute
     * alone is easy to miss. These short forms get rendered as visible text.
     *
     * @var array<string, string>
     */
    public const GLOSSARY_SHORT = [
        'PTA' => 'terminated here',
        'FWDED' => 'forwarded to an LNS',
        'TRANS' => 'still negotiating',
    ];

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
