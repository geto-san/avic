<section class="card"><h2>Documents</h2>
<?php if (!$documents): ?><p class="empty">No documents yet.</p><?php else: ?>
<div class="table-wrap"><table class="data"><thead><tr><th>File</th><th>Type</th><th>Uploaded</th><th></th></tr></thead><tbody>
<?php foreach ($documents as $d): ?><tr>
  <td><?php if (str_starts_with($d['mime_type'], 'image/')): ?><a href="/documents/<?= (int)$d['id'] ?>"><img class="thumb" src="/documents/<?= (int)$d['id'] ?>" alt="<?= e($d['original_name']) ?>"></a><br><?php endif; ?><?= e($d['original_name']) ?> <span class="muted small">(<?= e(number_format($d['file_size'] / 1024, 0)) ?> KB)</span></td>
  <td><?= e(label($d['doc_type'])) ?></td><td class="nowrap"><?= e(fmt_date($d['created_at'])) ?><br><span class="muted small"><?= e($d['uploader_name']) ?></span></td>
  <td><a class="btn btn-sm" href="/documents/<?= (int)$d['id'] ?>?download=1">Download</a></td></tr><?php endforeach; ?>
</tbody></table></div><?php endif; ?></section>
<?php if ($claim['status'] !== 'closed'): ?>
<section class="card form-card"><h2>Upload a document</h2>
  <form method="post" action="/claims/<?= (int)$claim['id'] ?>/documents" enctype="multipart/form-data"><?= $csrf ?>
    <div class="cols">
      <div class="field"><label for="d_type">Type</label><select id="d_type" name="doc_type"><?php foreach (Avic\Domain\Catalog::DOC_TYPES as $k => $t): ?><option value="<?= e($k) ?>"><?= e($t) ?></option><?php endforeach; ?></select></div>
      <div class="field"><label for="d_file">File (JPEG, PNG or PDF, up to 5 MB)</label><input id="d_file" type="file" name="file" accept=".jpg,.jpeg,.png,.pdf" required></div>
    </div>
    <button class="btn btn-primary">Upload</button></form></section>
<?php endif; ?>
