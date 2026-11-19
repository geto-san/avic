# Motor repair pilot working agreement

Draft for insurer review. The initial case is repairable own-vehicle damage. The claims lead owns the product wording and referral criteria. Finance owns the authority limits, posting precision and payment exception policy. Operations owns deadlines, business calendars and escalation contacts. Record their names and approvals before enabling live cases.

| Action | Current foundation permission | Proposed later authority |
|---|---|---|
| Report a loss or answer a document request | Claimant on own claim | Same |
| Quote repairs | Garage assigned the work order | Same, with supplement approvals |
| Record coverage evidence | Assigned active adjuster | Same, subject to approved wording |
| Inspect another adjuster's outstanding coverage tasks | Active adjuster with separately granted coverage.view_all | Supervisor in charge of the relevant team |
| Accept or dispute an offer | Claimant on own settlement | Same, with versioned acceptance |
| Approve claim and record external payout | Existing adjuster workflow | Adjuster proposes, authorized supervisor approves, independent finance user releases |
| Issue a policy | Existing adjuster workflow | Policy administration or delegated underwriter |

## Walkthrough for acceptance

1. On a synthetic repair claim, verify the policy reference, record the evidence in the review queue, request a garage quote, issue an offer, obtain claimant acceptance and verify an external payment record before closure.
2. For missing evidence, request named documents. The claimant answers each request. Keep the submission deadline and restrict garage access to assigned work.
3. For a disputed offer, preserve the decision, assign an independent reviewer and hold payout and closure while the complaint is open.
4. For a failed or uncertain payment, keep the claim unpaid and refer to finance. Record the provider reference for investigation. Live transfer, independent release and automated reconciliation are pending roadmap work.

Use synthetic cases for development. Claims and finance should confirm period-of-loss rules, excesses, limits, required evidence, complaint holds and payment authority. The existing deadline in settings supplies a provisional queue target. An unanswered policy question should be referred to the named owner.

## Provisional demo rules

Use the existing comprehensive motor policy fixture, synthetic identities, UGX and the database's two-decimal precision. One test loss has a proposed settlement of UGX 10.07. An amount of 10.001 fails validation. The submitter owns the loss, the assigned adjuster records policy evidence, and the insured can dispute the proposed offer. Give the second adjuster cross-queue visibility explicitly when demonstrating supervision. Use the seeded 14 calendar days for verification. Preserve each task's original deadline if the setting changes. These choices are approved for demonstration only.

## Foundation rehearsal

A disposable database was populated using the six migrations and synthetic seed from commit `bef222f`, then upgraded with `php bin/migrate.php`. The upgrade retained eight claims and generated three outstanding coverage checks. A second migration run applied zero changes. A SQL backup restored to a separate database retained eight claims, three checks, one supervisor grant and a historical payout total of UGX 8,050,000.00. This exercise covered database records. A full recovery drill must also restore uploaded evidence and application configuration.

To repeat on a disposable copy, record the source claim count and payout total, apply the migration twice, compare open claims to outstanding tasks, and restore the resulting SQL dump to a second database. Verify the restored counts, total, task evidence and permission grants. Keep the test databases and uploads separate from the demonstration environment.

## Following milestone

The workbench still needs an immutable snapshot of the policy terms at loss, exposure-specific coverage decisions and a supervisor referral for ambiguous cover. Build those on the existing claims and coverage-check records. Financial approval limits, independent finance release, MFA, provider connectors, and live rollout remain later milestones.
