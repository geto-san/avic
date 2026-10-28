<div class="table-wrap"><table class="data"><thead><tr><th>Policy</th><th>Vehicle</th><th>Cover</th><th class="num">Limit</th><th>Period</th></tr></thead><tbody>
<?php foreach ($policies as $p): $expired = $p['end_date'] < date('Y-m-d'); ?>
  <tr><td><?= e($p['policy_number']) ?></td>
    <td><?= e($p['vehicle_make'] . ' ' . $p['vehicle_model']) ?><br><span class="muted small"><?= e($p['vehicle_plate']) ?></span></td>
    <td><?= e(label($p['coverage_type'])) ?></td><td class="num"><?= e(money($p['coverage_limit'])) ?></td>
    <td class="nowrap"><?= e(fmt_date($p['start_date'])) ?> – <?= e(fmt_date($p['end_date'])) ?>
      <?= $p['status'] === 'cancelled' ? badge('cancelled') : ($expired ? badge('expired', 'Expired') : badge('active', 'In force')) ?></td></tr>
<?php endforeach; ?></tbody></table></div>
