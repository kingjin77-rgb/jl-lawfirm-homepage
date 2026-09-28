<h1 class="adm-ttl"><?= h($section['title']) ?></h1>
<p class="adm-lead">3단계에서 만드는 화면입니다. 기존 시스템의 구성은 아래와 같습니다.</p>

<div class="alert alert--info">
  <b>준비 중</b>
  이 메뉴의 기능은 2단계(등기진행 관리)까지 안정화한 뒤 3단계에서 옮겨 옵니다.
</div>

<div class="adm-sec">
  <h2>기존 화면 구성 (이대로 재현 예정)</h2>
  <ul style="margin:0; padding-left:20px; font-size:15px; line-height:2">
    <?php foreach ($section['items'] as $item): ?>
    <li><?= h($item) ?></li>
    <?php endforeach; ?>
  </ul>
</div>
