<?php
declare(strict_types=1);
namespace Avic\Tests\Feature;

final class PolicyRegistrationTest extends FeatureTestCase
{
    private function details(): array
    {
        return ['policy_number'=>'EXISTING-UG-001','insurer_name'=>'Example Insurance',
            'vehicle_make'=>'Toyota','vehicle_model'=>'Corolla','vehicle_year'=>2020,'vehicle_plate'=>'TEST 001',
            'coverage_type'=>'comprehensive','coverage_limit'=>20000000,'start_date'=>'2026-01-01','end_date'=>'2027-01-01'];
    }

    private function register(): int
    {
        $response=$this->signIn(self::DOREEN)->postJson('config/api/policy-registrations.php',$this->details());
        $this->assertStatus(201,$response);return (int)$response->json()['id'];
    }

    public function testClaimantRegistrationDoesNotCreateUsableCoverUntilVerified(): void
    {
        $id=$this->register();
        $this->assertSame(0,$this->rows('policies', 'policy_number = "EXISTING-UG-001"'));
        $this->assertSame('pending',$this->value('SELECT status FROM policy_registrations WHERE id=?',[$id]));
        $this->assertStatus(200,$this->signIn(self::BRIAN)->postJson('config/api/policy-registrations.php',['id'=>$id,'action'=>'verify','review_notes'=>'Checked the existing policy schedule and insurer reference.']));
        $policy=(int)$this->value('SELECT policy_id FROM policy_registrations WHERE id=?',[$id]);
        $this->assertSame(1,(int)$this->value('SELECT user_id FROM policies WHERE id=?',[$policy]));
        $this->assertNull($this->value('SELECT premium FROM policies WHERE id=?',[$policy]));
        $this->assertStatus(200,$this->signIn(self::DOREEN)->postMultipart('config/api/claims.php',$this->claimForm(['policy_id'=>$policy])));
    }

    public function testOwnersCannotSelfVerifyOrRegisterForAnotherUser(): void
    {
        $owner=$this->signIn(self::DOREEN);
        $response=$owner->postJson('config/api/policy-registrations.php',$this->details()+['user_id'=>5]);
        $this->assertStatus(201,$response);$id=(int)$response->json()['id'];
        $this->assertSame(1,(int)$this->value('SELECT user_id FROM policy_registrations WHERE id=?',[$id]));
        $this->assertStatus(422,$owner->postJson('config/api/policy-registrations.php',['id'=>$id,'action'=>'verify']));
        $this->assertSame('pending',$this->value('SELECT status FROM policy_registrations WHERE id=?',[$id]));
        $this->assertSame([],$this->signIn(self::ALEX)->get('config/api/policy-registrations.php')->json()['registrations']);
        $this->assertStatus(403,$this->signIn(self::KIGONGO)->get('config/api/policy-registrations.php'));
    }

    public function testReturnedPolicyCanBeCorrectedOnlyByItsOwner(): void
    {
        $id=$this->register();$desk=$this->signIn(self::BRIAN);
        $this->assertStatus(422,$desk->postJson('config/api/policy-registrations.php',['id'=>$id,'action'=>'reject']));
        $this->assertStatus(200,$desk->postJson('config/api/policy-registrations.php',['id'=>$id,'action'=>'reject','review_notes'=>'Correct the vehicle model to match the schedule.']));
        $this->assertStatus(404,$this->signIn(self::ALEX)->postJson('config/api/policy-registrations.php',$this->details()+['id'=>$id]));
        $this->assertStatus(201,$this->signIn(self::DOREEN)->postJson('config/api/policy-registrations.php',array_replace($this->details(),['id'=>$id,'vehicle_model'=>'Corrected model'])));
        $this->assertSame('pending',$this->value('SELECT status FROM policy_registrations WHERE id=?',[$id]));
        $this->assertSame(1,$this->rows('policy_registrations'));
    }

    public function testInvalidAndDuplicatePolicyDetailsAreRefused(): void
    {
        $owner=$this->signIn(self::DOREEN);
        $this->assertStatus(422,$owner->postJson('config/api/policy-registrations.php',array_replace($this->details(),['insurer_name'=>'','coverage_limit'=>-1])));
        $id=$this->register();
        $this->assertStatus(409,$owner->postJson('config/api/policy-registrations.php',$this->details()));
        $this->assertStatus(200,$this->signIn(self::BRIAN)->postJson('config/api/policy-registrations.php',['id'=>$id,'action'=>'verify','review_notes'=>'Verified against insurer records.']));
        $this->assertStatus(409,$this->signIn(self::GRACE)->postJson('config/api/policy-registrations.php',['id'=>$id,'action'=>'verify','review_notes'=>'Repeat attempt.']));
        $this->assertSame(1,$this->rows('policies','policy_number = "EXISTING-UG-001"'));
    }

    public function testVerificationCannotCreateOverlappingActiveCover(): void
    {
        $plate=$this->value('SELECT vehicle_plate FROM policies WHERE id=1');
        $response=$this->signIn(self::DOREEN)->postJson('config/api/policy-registrations.php',array_replace($this->details(),['vehicle_plate'=>$plate]));
        $this->assertStatus(201,$response);
        $this->assertStatus(409,$this->signIn(self::BRIAN)->postJson('config/api/policy-registrations.php',['id'=>$response->json()['id'],'action'=>'verify','review_notes'=>'Overlapping cover check.']));
        $this->assertSame(0,$this->rows('policies','policy_number = "EXISTING-UG-001"'));
    }

    public function testConcurrentVerificationCreatesExactlyOnePolicy(): void
    {
        if (!\Avic\Tests\Support\TestServer::concurrent()) $this->markTestSkipped('Needs concurrent workers.');
        $id=$this->register();$clients=[$this->signIn(self::BRIAN),$this->signIn(self::GRACE)];
        $codes=array_map(static fn($response)=>$response->status,\Avic\Tests\Support\ApiClient::postJsonTogether($clients,'config/api/policy-registrations.php',['id'=>$id,'action'=>'verify','review_notes'=>'Insurer records checked.']));sort($codes);
        $this->assertSame([200,409],$codes);$this->assertSame(1,$this->rows('policies','policy_number = "EXISTING-UG-001"'));
    }
}
