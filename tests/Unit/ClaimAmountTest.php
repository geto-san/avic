<?php
declare(strict_types=1);

namespace Avic\Tests\Unit;

use Avic\Finance\ClaimAmount;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/src/Finance/ClaimAmount.php';

final class ClaimAmountTest extends TestCase
{
    public function testExistingDecimalColumnRangeRoundTripsExactly(): void
    {
        foreach (['0.00', '0.01', '10.07', '9999999999.99'] as $amount) {
            $this->assertSame($amount, ClaimAmount::decimal(ClaimAmount::units($amount)));
        }
    }

    /** @dataProvider unsupportedAmounts */
    public function testValuesOutsideTheStorageContractAreRejected(mixed $amount): void
    {
        $this->expectException(InvalidArgumentException::class);
        ClaimAmount::units($amount);
    }

    public static function unsupportedAmounts(): array
    {
        return [['10.001'], ['-1'], ['10000000000.00'], [true], [null], [[]], ['1e6']];
    }
}
