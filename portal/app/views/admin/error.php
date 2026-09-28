<div class="card" style="max-width:560px">
  <h1 class="adm-ttl"><?= h($pageTitle ?? '오류') ?></h1>
  <div class="alert alert--err"><?= h($message ?? '요청을 처리할 수 없습니다.') ?></div>
  <a class="btn" href="/admin">대시보드로</a>
</div>
