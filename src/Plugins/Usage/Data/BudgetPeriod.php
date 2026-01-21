<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Usage\Data;

/**
 * Budget period types.
 */
enum BudgetPeriod: string
{
    case Daily = 'daily';
    case Weekly = 'weekly';
    case Monthly = 'monthly';
    case Yearly = 'yearly';
}
