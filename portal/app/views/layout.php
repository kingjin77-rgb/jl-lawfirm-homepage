<?php
/**
 * 공통 레이아웃 — 헤더(로그아웃) + 탭 내비(6메뉴) + 본문 + 푸터.
 * $pageTitle, $content 는 render() 가 넘겨준다. $customer/$hh 는 있을 때만.
 */
$isLoggedIn = isset($customer, $hh) && $customer !== null;
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
?>
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($pageTitle ?? '등기 진행 조회') ?> | 법무법인 제이엘</title>
<meta name="robots" content="noindex, nofollow">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/orioncactus/pretendard@v1.3.9/dist/web/variable/pretendardvariable-dynamic-subset.min.css">
<link rel="stylesheet" href="/assets/portal.css?v=1">
</head>
<body>

<header class="hd">
  <div class="wrap hd__in">
    <a class="hd__logo" href="<?= $isLoggedIn ? '/home' : '/login' ?>">
      <strong>법무법인 제이엘</strong><span>등기 진행 조회</span>
    </a>
    <?php if ($isLoggedIn): ?>
    <div class="hd__user">
      <span class="hd__who"><?= h($customer['owner_name']) ?> 님 · <?= h($hh['dong']) ?>동 <?= h($hh['ho']) ?>호</span>
      <a class="hd__out" href="/logout">로그아웃</a>
    </div>
    <?php endif; ?>
  </div>
</header>

<?php if ($isLoggedIn): ?>
<nav class="tabs" aria-label="등기 메뉴">
  <div class="wrap tabs__in">
    <?php foreach (customer_menus() as $href => $label): ?>
    <a href="<?= h($href) ?>"<?= $currentPath === $href ? ' class="is-on" aria-current="page"' : '' ?>><?= h($label) ?></a>
    <?php endforeach; ?>
  </div>
</nav>
<?php endif; ?>

<main class="wrap main">
<?= $content ?>
</main>

<footer class="ft">
  <div class="wrap">
    <p>&copy; 법무법인 제이엘</p>
    <p>광고책임변호사 박종일</p>
    <p>사업자등록번호 801-81-01890</p>
    <p>문의 <a href="tel:18994252">1899-4252</a></p>
  </div>
</footer>

</body>
</html>
