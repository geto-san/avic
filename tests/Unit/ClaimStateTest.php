<?php
declare(strict_types=1);

namespace Avic\Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/config/api/claim-state.php';

final class ClaimStateTest extends TestCase
{
    public function testEveryTransitionTargetIsAKnownStatus(): void
    {
        foreach (CLAIM_TRANSITIONS as $from => $targets) {
            foreach ($targets as $to) {
                $this->assertArrayHasKey($to, CLAIM_TRANSITIONS, "$from -> $to points at an unknown status");
            }
        }
    }

    public function testEveryStatusHasAHumanLabel(): void
    {
        $this->assertEqualsCanonicalizing(array_keys(CLAIM_TRANSITIONS), array_keys(CLAIM_STATUS_WORDS));
    }

    public function testEveryStatusIsReachableFromDraft(): void
    {
        $seen = ['draft' => true];
        $queue = ['draft'];
        while ($queue) {
            foreach (CLAIM_TRANSITIONS[array_shift($queue)] as $to) {
                if (!isset($seen[$to])) {
                    $seen[$to] = true;
                    $queue[] = $to;
                }
            }
        }
        $this->assertEqualsCanonicalizing(array_keys(CLAIM_TRANSITIONS), array_keys($seen), 'a status nothing can reach is dead code');
    }

    public function testClosedIsTerminal(): void
    {
        $this->assertSame([], CLAIM_TRANSITIONS['closed']);
    }

    /** @dataProvider legalMoves */
    public function testLegalMoves(string $from, string $to): void
    {
        $this->assertTrue(claimCanMove($from, $to));
    }

    /** @return array<string,array{string,string}> */
    public static function legalMoves(): array
    {
        return [
            'save a draft'                  => ['draft', 'draft'],
            'submit a draft'                => ['draft', 'submitted'],
            'adjuster approves'             => ['submitted', 'approved'],
            'adjuster rejects'              => ['under_review', 'rejected'],
            'adjuster asks for documents'   => ['submitted', 'pending_docs'],
            'claimant answers'              => ['pending_docs', 'submitted'],
            'adjuster decides while parked' => ['pending_docs', 'approved'],
            'payout completes'              => ['approved', 'paid'],
        ];
    }

    /** @dataProvider illegalMoves */
    public function testIllegalMoves(string $from, string $to): void
    {
        $this->assertFalse(claimCanMove($from, $to));
    }

    /** @return array<string,array{string,string}> */
    public static function illegalMoves(): array
    {
        return [
            'edit a paid claim'            => ['paid', 'submitted'],
            'draft over an approved claim' => ['approved', 'draft'],
            'draft while docs requested'   => ['pending_docs', 'draft'],
            're-submit a submitted claim'  => ['submitted', 'submitted'],
            'approve twice'                => ['approved', 'approved'],
            'reverse a rejection'          => ['rejected', 'approved'],
            'skip the decision'            => ['submitted', 'paid'],
            'ask for docs twice'           => ['pending_docs', 'pending_docs'],
            'reopen a closed claim'        => ['closed', 'submitted'],
            'unknown source status'        => ['bogus', 'paid'],
        ];
    }

    public function testAssertMoveThrowsA409WithTheGivenMessage(): void
    {
        try {
            claimAssertMove('paid', 'submitted', 'custom text');
            $this->fail('expected ApiAbort');
        } catch (\ApiAbort $e) {
            $this->assertSame(409, $e->getCode());
            $this->assertSame('custom text', $e->getMessage());
        }
    }

    public function testAssertMoveHasASensibleDefaultMessage(): void
    {
        $this->expectException(\ApiAbort::class);
        $this->expectExceptionMessage('This claim is approved.');
        claimAssertMove('approved', 'draft');
    }

    public function testAssertMoveIsSilentWhenLegal(): void
    {
        claimAssertMove('draft', 'submitted');
        $this->addToAssertionCount(1);
    }

    public function testStatusWordsFallBackToTheRawStatus(): void
    {
        $this->assertSame('waiting for documents', claimStatusWords('pending_docs'));
        $this->assertSame('mystery', claimStatusWords('mystery'));
    }
}
