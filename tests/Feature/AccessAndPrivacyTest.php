<?php
declare(strict_types=1);

namespace Avic\Tests\Feature;

/**
 * Who can see what. These are the properties the portal is built on: a person
 * only ever gets their own data, and a garage never learns who the claimant is
 * or what they are being paid.
 */
final class AccessAndPrivacyTest extends FeatureTestCase
{
    private const BOOTSTRAP = 'config/api/bootstrap.php';

    /** @return list<int> */
    private function claimIds(string $email): array
    {
        $ids = array_map(static fn($c) => (int)$c['id'], $this->signIn($email)->get(self::BOOTSTRAP)->json()['claims']);
        sort($ids);
        return $ids;
    }

    public function testAClaimantSeesOnlyTheirOwnClaims(): void
    {
        $this->assertSame([101, 102, 103, 108], $this->claimIds(self::DOREEN));
        $this->assertSame([104, 105, 107], $this->claimIds(self::ALEX));
    }

    public function testAnAdjusterSeesOnlyTheClaimsOnTheirDesk(): void
    {
        $this->assertSame([101, 102, 104, 106, 108], $this->claimIds(self::BRIAN));
        $this->assertSame([105, 107], $this->claimIds(self::GRACE));
    }

    public function testAGarageSeesOnlyTheJobsItHoldsAWorkOrderFor(): void
    {
        $this->assertSame([101, 104, 107], $this->claimIds(self::KIGONGO));
        $this->assertSame([], $this->claimIds(self::SSEMWANGA));
    }

    public function testAGarageNeverSeesWhoTheClaimantIsOrWhatTheyArePaid(): void
    {
        $data = $this->signIn(self::KIGONGO)->get(self::BOOTSTRAP)->json();

        $allowed = ['claim_number', 'claim_type', 'id', 'incident_date', 'incident_description', 'incident_location', 'policy_id', 'status'];
        foreach ($data['claims'] as $claim) {
            $keys = array_keys($claim);
            sort($keys);
            $this->assertSame($allowed, $keys, 'a garage gets the damage details and nothing else');
        }
        foreach ($data['policies'] as $policy) {
            $keys = array_keys($policy);
            sort($keys);
            $this->assertSame(['id', 'vehicle_make', 'vehicle_model', 'vehicle_plate', 'vehicle_year'], $keys, 'vehicle only — no policy holder, number or limit');
        }
        $this->assertSame([], $data['payouts']);
        $this->assertCount(1, $data['users'], 'the only person a garage can see is themselves');
        $this->assertStringNotContainsString('doreen', strtolower(json_encode($data)));
        $this->assertStringNotContainsString('example.ug', json_encode($data['claims']));
    }

    public function testAGarageOnlySeesItsOwnQuotes(): void
    {
        $data = $this->signIn(self::SSEMWANGA)->get(self::BOOTSTRAP)->json();

        $this->assertSame([], $data['estimates']);
        $this->assertSame([], $data['workOrders']);
    }

    public function testAClaimantSeesOnlyTheirOwnPolicies(): void
    {
        $policies = $this->signIn(self::DOREEN)->get(self::BOOTSTRAP)->json()['policies'];
        $ids = array_map(static fn($p) => (int)$p['id'], $policies);
        sort($ids);

        $this->assertSame([1, 2], $ids);
    }

    public function testNobodyElseCanDownloadAClaimPdf(): void
    {
        $url = 'config/api/claims-pdf.php?claim_id=101';

        $this->assertStatus(401, $this->anonymous()->get($url));
        $this->assertStatus(403, $this->signIn(self::ALEX)->get($url), 'another claimant');
        $this->assertStatus(403, $this->signIn(self::GRACE)->get($url), 'an adjuster who does not hold the claim');
    }

    public function testTheOwnerAndTheHoldingAdjusterGetARealPdf(): void
    {
        foreach ([self::DOREEN, self::BRIAN] as $email) {
            $response = $this->signIn($email)->get('config/api/claims-pdf.php?claim_id=101');

            $this->assertStatus(200, $response, $email);
            $this->assertStringStartsWith('%PDF-', $response->body);
        }
    }

    /** @dataProvider adjusterOnlyEndpoints */
    public function testAdjusterOnlyEndpointsRefuseEveryoneElse(string $path, array $body): void
    {
        $this->assertStatus(401, $this->anonymous()->postJson($path, $body), 'signed out');
        $this->assertStatus(403, $this->signIn(self::DOREEN)->postJson($path, $body), 'claimant');
        $this->assertStatus(403, $this->signIn(self::KIGONGO)->postJson($path, $body), 'garage');
    }

    /** @return array<string,array{string,array<string,mixed>}> */
    public static function adjusterOnlyEndpoints(): array
    {
        return [
            'decide a claim'    => ['config/api/claims-decision.php', ['claim_id' => 101, 'decision' => 'reject']],
            'assign a garage'   => ['config/api/claims-assign.php', ['claim_id' => 101, 'garage_id' => 7]],
            'act on a quote'    => ['config/api/estimates-decision.php', ['estimate_id' => 1, 'decision' => 'approve']],
            'move a payout'     => ['config/api/payouts-update.php', ['payout_id' => 2, 'action' => 'mark_paid']],
            'verify a document' => ['config/api/documents.php', ['action' => 'verify', 'id' => 3]],
        ];
    }

    public function testQuotingIsForGaragesOnly(): void
    {
        $body = ['claim_id' => 101, 'parts_cost' => 1000, 'labor_cost' => 1000, 'repair_days' => 3];

        $this->assertStatus(401, $this->anonymous()->postJson('config/api/estimates.php', $body));
        $this->assertStatus(403, $this->signIn(self::DOREEN)->postJson('config/api/estimates.php', $body));
        $this->assertStatus(403, $this->signIn(self::BRIAN)->postJson('config/api/estimates.php', $body));
    }

    public function testWrongMethodsAndBadBodiesAreRefusedCleanly(): void
    {
        $brian = $this->signIn(self::BRIAN);

        $this->assertStatus(405, $brian->get('config/api/claims-decision.php'));
        $this->assertStatus(405, $this->signIn(self::DOREEN)->postJson(self::BOOTSTRAP, []));
        $this->assertStatus(422, $brian->postJson('config/api/claims-decision.php', []), 'an empty body is not a decision');
    }
}
