<?php
/**
 * 직원 관리자 공통 레이아웃 — 상단 7메뉴 + 좌측 서브메뉴 + 본문.
 * render_admin() 이 $pageTitle, $content, (로그인 후) $staff 를 넘겨준다.
 * 로그인 화면($staff 없음)은 메뉴 없이 본문만 가운데 띄운다.
 */
$isStaff = isset($staff) && $staff !== null;
$currentPath = rtrim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/', '/') ?: '/';
$activeSection = $isStaff ? admin_active_section($currentPath) : '';
$menus = admin_menus();
?>
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($pageTitle ?? '관리자') ?> | 제이엘 등기 관리자</title>
<meta name="robots" content="noindex, nofollow">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/orioncactus/pretendard@v1.3.9/dist/web/variable/pretendardvariable-dynamic-subset.min.css">
<link rel="stylesheet" href="/assets/portal.css?v=1">
<link rel="stylesheet" href="/assets/admin.css?v=1">
</head>
<body class="adm-body">

<header class="adm-hd">
  <div class="adm-hd__in">
    <a class="adm-hd__logo" href="/admin">
      <strong>법무법인 제이엘</strong><span>등기 관리자</span>
    </a>
    <?php if ($isStaff): ?>
    <nav class="adm-nav" aria-label="관리자 메뉴">
      <?php foreach ($menus as $key => $menu): ?>
      <a href="<?= h($menu['home']) ?>"<?= $activeSection === $key ? ' class="is-on"' : '' ?>><?= h($menu['label']) ?></a>
      <?php endforeach; ?>
    </nav>
    <div class="adm-hd__user">
      <span><?= h($staff['name']) ?> (<?= h($staff['role']) ?>)</span>
      <a class="adm-hd__out" href="/admin/logout">로그아웃</a>
    </div>
    <?php endif; ?>
  </div>
</header>

<?php if ($isStaff && $activeSection !== ''): ?>
<div class="adm-frame">
  <aside class="adm-side" aria-label="하위 메뉴">
    <b class="adm-side__ttl"><?= h($menus[$activeSection]['label']) ?></b>
    <?php foreach ($menus[$activeSection]['sub'] as $href => $label): ?>
    <a href="<?= h($href) ?>"<?= $currentPath === $href ? ' class="is-on" aria-current="page"' : '' ?>><?= h($label) ?></a>
    <?php endforeach; ?>
  </aside>
  <main class="adm-main">
<?= $content ?>
  </main>
</div>
<?php else: ?>
<main class="adm-main adm-main--wide">
<?= $content ?>
</main>
<?php endif; ?>

</body>
</html>
