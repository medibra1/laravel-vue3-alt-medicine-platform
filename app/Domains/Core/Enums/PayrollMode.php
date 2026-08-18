<?php

namespace App\Domains\Core\Enums;

/**
 * Chosen per center — the structural fork point between the two payroll
 * models. See schema-donnees-v1.md §6bis/§6ter for the full rationale.
 *
 * - PoolSharing: no fixed salary, a pool amount is split among
 *   practitioners per period, weighted by attendance × grade coefficient.
 *   Fully implemented (PayPeriodCalculator).
 * - Conventional: a real payroll — employment contracts, base salary,
 *   employer/employee charges paid to payroll organisms (social
 *   security, tax, pension...). Schema only for now, calculation engine
 *   deferred until this mode is actually needed by a center.
 */
enum PayrollMode: string
{
    case PoolSharing = 'pool_sharing';
    case Conventional = 'conventional';
}
