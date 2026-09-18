<?php

/*
 * Oids.php
 *
 * Numeric SNMP OIDs used by the CiscoPppoe plugin.
 *
 * LibreNMS does not ship CISCO-PPPOE-MIB or CISCO-SUBSCRIBER-SESSION-MIB, so the
 * plugin polls numerically and never depends on a MIB file at runtime. The files
 * under mibs/ are reference material: they come from the official Cisco MIB
 * repository and were used with snmptranslate to derive every constant below.
 */

namespace App\Plugins\CiscoPppoe\Support;

final class Oids
{
    /** ciscoPppoeMIB */
    public const PPPOE_MIB = '.1.3.6.1.4.1.9.9.194';

    /** ciscoSubscriberSessionMIB */
    public const SUBSCRIBER_SESSION_MIB = '.1.3.6.1.4.1.9.9.786';

    /*
     * CISCO-PPPOE-MIB :: cPppoeSystemInfo
     *
     * Device wide scalars, so every OID carries the .0 instance suffix.
     * Verified against a live Cisco ASR1000 (IOS-XE) BRAS.
     */

    /** cPppoeSystemCurrSessions.0 - Gauge32, PPPoE sessions currently active on the device */
    public const SYSTEM_CURRENT_SESSIONS = '.1.3.6.1.4.1.9.9.194.1.1.1.0';

    /** cPppoeSystemHighWaterSessions.0 - Gauge32, highest concurrent session count seen since boot */
    public const SYSTEM_HIGH_WATER_SESSIONS = '.1.3.6.1.4.1.9.9.194.1.1.2.0';

    /** cPppoeSystemMaxAllowedSessions.0 - Unsigned32, configured session limit (0 means no limit) */
    public const SYSTEM_MAX_ALLOWED_SESSIONS = '.1.3.6.1.4.1.9.9.194.1.1.3.0';

    /** cPppoeSystemThresholdSessions.0 - Unsigned32, session count that triggers the threshold trap */
    public const SYSTEM_THRESHOLD_SESSIONS = '.1.3.6.1.4.1.9.9.194.1.1.4.0';

    /** cPppoeSystemExceededSessionErrors.0 - Counter32, sessions refused because the limit was reached */
    public const SYSTEM_EXCEEDED_SESSION_ERRORS = '.1.3.6.1.4.1.9.9.194.1.1.5.0';

    /**
     * Scalar OID => key used in the statistics array.
     *
     * @var array<string, string>
     */
    public const SYSTEM_SCALARS = [
        self::SYSTEM_CURRENT_SESSIONS => 'system_current',
        self::SYSTEM_HIGH_WATER_SESSIONS => 'high_water',
        self::SYSTEM_MAX_ALLOWED_SESSIONS => 'max_allowed',
        self::SYSTEM_THRESHOLD_SESSIONS => 'threshold',
        self::SYSTEM_EXCEEDED_SESSION_ERRORS => 'exceeded_errors',
    ];

    /*
     * CISCO-PPPOE-MIB :: cPppoeSessionsPerInterfaceTable
     *
     * INDEX { ifIndex }. The table entry itself is not-accessible, walk the entry
     * OID to collect every columnar object in a single SNMP walk.
     */

    /** cPppoeSessionsPerInterfaceEntry - walk this to get all columns below at once */
    public const PER_INTERFACE_ENTRY = '.1.3.6.1.4.1.9.9.194.1.4.1.1';

    /**
     * Column number inside cPppoeSessionsPerInterfaceEntry => key in the parsed row.
     *
     * 1 cPppoeTotalSessions                    Gauge32, PTA + FWDED + TRANS on the interface
     * 2 cPppoePtaSessions                      Gauge32, PPP Termination Aggregation sessions
     * 3 cPppoeFwdedSessions                    Gauge32, forwarded (L2TP/PPPoE relay) sessions
     * 4 cPppoeTransSessions                    Gauge32, sessions still negotiating
     * 5 cPppoePerInterfaceSessionLossThreshold Unsigned32, low watermark that raises a trap
     * 6 cPppoePerInterfaceSessionLossPercent   Unsigned32, loss percentage that raises a trap
     *
     * @var array<int, string>
     */
    public const PER_INTERFACE_COLUMNS = [
        1 => 'total',
        2 => 'pta',
        3 => 'fwded',
        4 => 'trans',
        5 => 'loss_threshold',
        6 => 'loss_percent',
    ];

    /*
     * CISCO-SUBSCRIBER-SESSION-MIB :: csubSessionTable
     *
     * INDEX { ifIndex } - one row per subscriber session, the ifIndex being the
     * virtual-access interface of that session. On a busy BRAS this table holds
     * tens of thousands of rows, which is why the plugin only walks it when the
     * per-session listing is explicitly enabled in the settings.
     */

    /** csubSessionTable - not-accessible, listed for reference only */
    public const SESSION_TABLE = '.1.3.6.1.4.1.9.9.786.1.1.1';

    /** Columnar OIDs of csubSessionEntry, keyed by the name used in the parsed row. */
    public const SESSION_COLUMNS = [
        'type' => '.1.3.6.1.4.1.9.9.786.1.1.1.1.1',      // csubSessionType, see SESSION_TYPES
        'state' => '.1.3.6.1.4.1.9.9.786.1.1.1.1.3',     // csubSessionState, see SESSION_STATES
        'creation_time' => '.1.3.6.1.4.1.9.9.786.1.1.1.1.6', // csubSessionCreationTime, TimeStamp
        'mac' => '.1.3.6.1.4.1.9.9.786.1.1.1.1.10',      // csubSessionMacAddress, MacAddress
        'ip' => '.1.3.6.1.4.1.9.9.786.1.1.1.1.13',       // csubSessionNativeIpAddr, InetAddress
        'domain' => '.1.3.6.1.4.1.9.9.786.1.1.1.1.23',   // csubSessionDomain, SnmpAdminString
        'username' => '.1.3.6.1.4.1.9.9.786.1.1.1.1.24', // csubSessionUsername, SnmpAdminString
    ];

    /** csubSessionType value that identifies a PPPoE subscriber */
    public const SESSION_TYPE_PPPOE = 4;

    /**
     * csubSessionType (SubSessionType textual convention).
     *
     * @var array<int, string>
     */
    public const SESSION_TYPES = [
        1 => 'all',
        2 => 'other',
        3 => 'ppp',
        4 => 'pppoe',
        5 => 'l2tp',
        6 => 'l2f',
        7 => 'ip-interface',
        8 => 'ip-packet',
        9 => 'ip-dhcpv4',
        10 => 'ip-radius',
        11 => 'l2-mac',
        12 => 'l2-dhcpv4',
        13 => 'l2-radius',
    ];

    /**
     * csubSessionState (SubSessionState textual convention).
     *
     * @var array<int, string>
     */
    public const SESSION_STATES = [
        1 => 'other',
        2 => 'pending',
        3 => 'up',
    ];

    /*
     * Not polled, kept here because it is the cheap alternative to csubSessionTable
     * if per-session detail is ever replaced by aggregated counters:
     *
     * csubAggStatsTable        .1.3.6.1.4.1.9.9.786.1.2.1
     *   INDEX { csubAggStatsPointType, csubAggStatsPoint, csubAggStatsSessionType }
     *   csubAggStatsPendingSessions .1.3.6.1.4.1.9.9.786.1.2.1.1.4
     *   csubAggStatsUpSessions      .1.3.6.1.4.1.9.9.786.1.2.1.1.5
     *   csubAggStatsAuthSessions    .1.3.6.1.4.1.9.9.786.1.2.1.1.6
     *   csubAggStatsUnAuthSessions  .1.3.6.1.4.1.9.9.786.1.2.1.1.7
     *   csubAggStatsHighUpSessions  .1.3.6.1.4.1.9.9.786.1.2.1.1.10
     */
}
