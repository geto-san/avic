#!/usr/bin/env bash
# AVIC Phase 0 smoke test — exercises the claim state machine, the decision /
# payout guards and the upload location against a RUNNING dev server.
#
# Interim only: Phase 1 replaces this with PHPUnit + CI.
#
# Setup (fresh, seeded DB each run — the tests mutate claims):
#   php config/db/schema.php && php config/db/seed-demo.php
#   PHP_CLI_SERVER_WORKERS=4 AVIC_ALLOW_INSECURE_COOKIES=1 \
#     AVIC_UPLOAD_DIR=/tmp/avic-test-uploads php -S 127.0.0.1:8080
# Run (same AVIC_UPLOAD_DIR in this shell):
#   AVIC_UPLOAD_DIR=/tmp/avic-test-uploads bash tests/phase0-smoke.sh
#
# Needs: curl, php, and the mysql or mariadb client.

BASE="${BASE:-http://127.0.0.1:8080}"
DB_NAME="${DB_NAME:-avic_portal}"; DB_USER="${DB_USER:-avic}"; DB_PASS="${DB_PASS:-12345678}"
UPLOAD_DIR="${AVIC_UPLOAD_DIR:-}"
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SQLCLI="$(command -v mariadb || command -v mysql)"
TMP="$(mktemp -d)"; trap 'rm -rf "$TMP"' EXIT
PASS=0; FAIL=0

sql() { "$SQLCLI" -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" -N -B -e "$1"; }
login() { curl -s -c "$TMP/jar_$1" -o /dev/null -w '%{http_code}' -H 'Content-Type: application/json' \
          -d "{\"email\":\"$2\",\"password\":\"Demo2026!\"}" "$BASE/config/auth/login.php"; }
post() { curl -s -b "$TMP/jar_$1" -o "$TMP/last.json" -w '%{http_code}' -H 'Content-Type: application/json' -d "$3" "$BASE/$2"; }
ok() { # name expected actual
  if [ "$2" = "$3" ]; then PASS=$((PASS+1)); printf '  ok    %s\n' "$1"
  else FAIL=$((FAIL+1)); printf '  FAIL  %s (expected %s, got %s)\n' "$1" "$2" "$3"; fi; }
status_of()  { sql "SELECT status FROM claims WHERE id=$1"; }
payouts_of() { sql "SELECT COUNT(*) FROM payouts WHERE claim_id=$1"; }
claim_body() { # id policy [mode]
  printf '{"mode":"%s","id":%s,"policy_id":%s,"claim_type":"collision","incident_date":"%s","incident_location":"Kampala Rd","incident_description":"Smoke test claim","estimated_damage":"1500000"}' \
    "${3:-submit}" "$1" "$2" "${4:-2026-08-28}"; }
adj_jar() { case "$(sql "SELECT adjuster_id FROM claims WHERE id=$1")" in 2) echo adj2;; 8) echo adj8;; *) echo none;; esac; }

echo "== sign in"
ok "claimant 1 login" 200 "$(login c1 doreen@example.ug)"
ok "claimant 5 login" 200 "$(login c5 alex.m@example.ug)"
ok "adjuster 2 login" 200 "$(login adj2 brian.okot@avic.ug)"
ok "adjuster 8 login" 200 "$(login adj8 grace.a@avic.ug)"

echo "== claimant edits are gated by claim status"
ok "re-submit a PAID claim is refused"            409 "$(post c1 config/api/claims.php "$(claim_body 102 1 submit 2026-07-19)")"
ok "  ...and it stays paid"                        paid "$(status_of 102)"
ok "draft-save over an APPROVED claim is refused" 409 "$(post c1 config/api/claims.php "$(claim_body 108 1 draft 2026-08-22)")"
ok "  ...and it stays approved"                    approved "$(status_of 108)"
ok "draft-save on a real draft still works"       200 "$(post c1 config/api/claims.php "$(claim_body 103 1 draft 2026-09-14)")"
ok "  ...and it stays a draft"                     draft "$(status_of 103)"
ok "submitting a saved draft works"               200 "$(post c1 config/api/claims.php "$(claim_body 103 1 submit 2026-09-14)")"
ok "  ...status is submitted"                      submitted "$(status_of 103)"
ok "  ...and an adjuster was assigned"             1 "$(sql "SELECT adjuster_id IS NOT NULL FROM claims WHERE id=103")"
ok "re-submitting a SUBMITTED claim is refused"   409 "$(post c1 config/api/claims.php "$(claim_body 103 1 submit 2026-09-14)")"
ok "draft-save on a pending_docs claim is refused" 409 "$(post c5 config/api/claims.php "$(claim_body 104 3 draft)")"
ok "  ...and it stays pending_docs"                pending_docs "$(status_of 104)"
ok "answering a docs request (pending_docs -> submitted)" 200 "$(post c5 config/api/claims.php "$(claim_body 104 3 submit)")"
ok "  ...status is submitted"                      submitted "$(status_of 104)"
ok "brand-new claim submits"                       200 "$(post c1 config/api/claims.php "$(claim_body 0 1 submit 2026-09-20)")"
NEW="$(sql "SELECT MAX(id) FROM claims WHERE user_id=1")"
ok "  ...new claim has an adjuster"                1 "$(sql "SELECT adjuster_id IS NOT NULL FROM claims WHERE id=$NEW")"

echo "== adjuster decisions are gated by claim status"
ok "approve an already-APPROVED claim is refused" 409 "$(post adj2 config/api/claims-decision.php '{"claim_id":108,"decision":"approve","amount":1000000}')"
ok "  ...and creates no payout"                    0 "$(payouts_of 108)"
ok "approve a PAID claim is refused"               409 "$(post adj2 config/api/claims-decision.php '{"claim_id":102,"decision":"approve","amount":1000000}')"
ok "reject an already-REJECTED claim is refused"   409 "$(post adj2 config/api/claims-decision.php '{"claim_id":106,"decision":"reject","review_notes":"again"}')"
ok "approve an under_review claim"                 200 "$(post adj2 config/api/claims-decision.php '{"claim_id":101,"decision":"approve","amount":5000000}')"
ok "  ...second approve is refused"                409 "$(post adj2 config/api/claims-decision.php '{"claim_id":101,"decision":"approve","amount":5000000}')"
ok "  ...exactly one payout exists"                1 "$(payouts_of 101)"
ok "request docs on a submitted claim"             200 "$(post "$(adj_jar 103)" config/api/claims-decision.php '{"claim_id":103,"decision":"request_docs","review_notes":"need photos"}')"
ok "  ...status is pending_docs"                   pending_docs "$(status_of 103)"
ok "  ...asking again is refused"                  409 "$(post "$(adj_jar 103)" config/api/claims-decision.php '{"claim_id":103,"decision":"request_docs"}')"
ok "reject a pending_docs claim (docs never came)" 200 "$(post "$(adj_jar 103)" config/api/claims-decision.php '{"claim_id":103,"decision":"reject","review_notes":"no docs"}')"

echo "== two simultaneous approvals: exactly one wins"
( post adj8 config/api/claims-decision.php '{"claim_id":105,"decision":"approve","amount":2000000}' > "$TMP/r1" ) &
( curl -s -b "$TMP/jar_adj8" -o /dev/null -w '%{http_code}' -H 'Content-Type: application/json' \
    -d '{"claim_id":105,"decision":"approve","amount":2000000}' "$BASE/config/api/claims-decision.php" > "$TMP/r2" ) &
wait
ok "one 200 and one 409" "200 409" "$(printf '%s\n%s\n' "$(cat "$TMP/r1")" "$(cat "$TMP/r2")" | sort | tr '\n' ' ' | sed 's/ $//')"
ok "  ...exactly one payout exists"                1 "$(payouts_of 105)"

echo "== payout lifecycle"
P101="$(sql "SELECT id FROM payouts WHERE claim_id=101")"
ok "cannot skip pending -> completed"              409 "$(post adj2 config/api/payouts-update.php "{\"payout_id\":$P101,\"action\":\"mark_paid\"}")"
ok "pending -> processing"                         200 "$(post adj2 config/api/payouts-update.php "{\"payout_id\":$P101,\"action\":\"mark_processing\"}")"
ok "processing twice is refused"                   409 "$(post adj2 config/api/payouts-update.php "{\"payout_id\":$P101,\"action\":\"mark_processing\"}")"
ok "processing -> completed"                       200 "$(post adj2 config/api/payouts-update.php "{\"payout_id\":$P101,\"action\":\"mark_paid\"}")"
ok "  ...claim becomes paid"                       paid "$(status_of 101)"
ok "completing twice is refused"                   409 "$(post adj2 config/api/payouts-update.php "{\"payout_id\":$P101,\"action\":\"mark_paid\"}")"
ok "seeded payout (claim 107) completes"           200 "$(post adj2 config/api/payouts-update.php '{"payout_id":2,"action":"mark_paid"}')"
ok "  ...claim 107 becomes paid"                   paid "$(status_of 107)"

echo "== uploads live outside the web root"
if [ -z "$UPLOAD_DIR" ]; then echo "  skip  AVIC_UPLOAD_DIR not set"; else
  printf 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==' | base64 -d > "$TMP/pixel.png"
  CODE="$(curl -s -b "$TMP/jar_c1" -o "$TMP/up.json" -w '%{http_code}' -F mode=submit -F policy_id=1 -F claim_type=collision \
    -F incident_date=2026-09-21 -F incident_location=Jinja -F incident_description=upload-test -F 'files[]=@'"$TMP"'/pixel.png;type=image/png' \
    "$BASE/config/api/claims.php")"
  ok "claim with a PNG submits" 200 "$CODE"
  DOC="$(sql "SELECT id FROM claim_documents ORDER BY id DESC LIMIT 1")"
  STORED="$(sql "SELECT stored_name FROM claim_documents WHERE id=$DOC")"
  ok "file is in AVIC_UPLOAD_DIR"                   yes "$([ -f "$UPLOAD_DIR/$STORED" ] && echo yes || echo no)"
  ok "file is NOT under the project's uploads/"     no  "$([ -f "$ROOT/uploads/$STORED" ] && echo yes || echo no)"
  ok "direct URL /uploads/<name> is not served"     404 "$(curl -s -o /dev/null -w '%{http_code}' "$BASE/uploads/$STORED")"
  ok "proxy serves it to the owner"                 200 "$(curl -s -b "$TMP/jar_c1" -o /dev/null -w '%{http_code}' "$BASE/config/uploads.php?doc=$DOC")"
  ok "proxy refuses another claimant"               403 "$(curl -s -b "$TMP/jar_c5" -o /dev/null -w '%{http_code}' "$BASE/config/uploads.php?doc=$DOC")"
  sql "UPDATE claim_documents SET original_name='ev\"il; x.png' WHERE id=$DOC"
  HDRS="$(curl -s -D - -b "$TMP/jar_c1" -o /dev/null "$BASE/config/uploads.php?doc=$DOC")"
  ok "nosniff header is set"                        1 "$(printf '%s' "$HDRS" | grep -ci '^x-content-type-options: *nosniff')"
  ok "filename quote cannot break out of the header" 0 "$(printf '%s' "$HDRS" | grep -i '^content-disposition' | grep -c 'ev"il')"
  # legacy files (uploaded before the move) must keep working
  mkdir -p "$ROOT/uploads"; cp "$TMP/pixel.png" "$ROOT/uploads/legacy-smoke.png"
  sql "INSERT INTO claim_documents (claim_id,uploaded_by,doc_type,original_name,stored_name,file_path,file_size,mime_type) VALUES (102,1,'other','legacy.png','legacy-smoke.png','uploads/legacy-smoke.png',$(stat -c%s "$TMP/pixel.png"),'image/png')"
  LDOC="$(sql "SELECT id FROM claim_documents WHERE stored_name='legacy-smoke.png'")"
  ok "legacy uploads/ file still served by the proxy" 200 "$(curl -s -b "$TMP/jar_c1" -o /dev/null -w '%{http_code}' "$BASE/config/uploads.php?doc=$LDOC")"
  sql "DELETE FROM claim_documents WHERE id=$LDOC"; rm -f "$ROOT/uploads/legacy-smoke.png"; rmdir "$ROOT/uploads" 2>/dev/null
fi

echo; echo "passed: $PASS   failed: $FAIL"; [ "$FAIL" -eq 0 ]
