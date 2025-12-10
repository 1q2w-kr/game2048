<?php
/**
 * 2048 Game - Main Entry Point
 */

// Integrate with Rhymix authentication
$authBridgePaths = [
    '/www/fun/common/rhymix_bridge.php',
    __DIR__ . '/../common/rhymix_bridge.php',
];

foreach ($authBridgePaths as $path) {
    if (file_exists($path)) {
        require_once $path;
        break;
    }
}

// Get current user from Rhymix
$sessionUser = function_exists('rhxCurrentUser') ? rhxCurrentUser() : ['loggedIn' => false];
$isLoggedIn = !empty($sessionUser['loggedIn']);
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" />
    <meta name="description" content="고전 2048 퍼즐 게임. 최단 시간 기록에 도전하세요!" />
    <title>2048 Game - 1q2w.kr</title>

    <!-- Shared styles -->
    <link rel="stylesheet" href="/fun/common/header.css?v=1.0" />

    <!-- Service styles -->
    <link rel="stylesheet" href="/fun/game2048/css/app.css?v=1.1" />
</head>
<body class="game2048">
<?php
// Shared header integration
$brand = '1q2w.kr';
$home = '/';
$service = '2048';

$headerPaths = [
    '/www/fun/common/header.php',
    __DIR__ . '/../common/header.php',
];

foreach ($headerPaths as $path) {
    if (file_exists($path)) {
        include $path;
        break;
    }
}
?>

<main class="game2048__container" id="main" tabindex="-1">
    <!-- Game info section -->
    <div class="game2048__info">
        <div class="game2048__score-box">
            <div class="game2048__label">시간</div>
            <div class="game2048__timer" data-timer>0:00.00</div>
        </div>
        <div class="game2048__score-box">
            <div class="game2048__label">이동</div>
            <div class="game2048__moves" data-moves>0</div>
        </div>
        <button class="game2048__new-game" data-new-game>새 게임</button>
    </div>

    <!-- Game board -->
    <div class="game2048__board-container">
        <div class="game2048__board" data-board aria-label="게임 보드">
            <!-- Tiles will be dynamically rendered here -->
        </div>
        <div class="game2048__overlay" data-game-over hidden aria-live="assertive">
            게임 오버
        </div>
    </div>

    <!-- Mobile controls -->
    <div class="game2048__controls">
        <div class="game2048__controls-row">
            <button class="game2048__btn" data-direction="up" aria-label="위로 이동">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M12 19V5M5 12l7-7 7 7"/>
                </svg>
            </button>
        </div>
        <div class="game2048__controls-row">
            <button class="game2048__btn" data-direction="left" aria-label="왼쪽으로 이동">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M19 12H5M12 19l-7-7 7-7"/>
                </svg>
            </button>
            <button class="game2048__btn" data-direction="down" aria-label="아래로 이동">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M12 5v14M19 12l-7 7-7-7"/>
                </svg>
            </button>
            <button class="game2048__btn" data-direction="right" aria-label="오른쪽으로 이동">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M5 12h14M12 5l7 7-7 7"/>
                </svg>
            </button>
        </div>
    </div>

    <!-- Leaderboard section -->
    <div class="game2048__leaderboard">
        <h2 class="game2048__section-title">순위표</h2>
        <div class="game2048__leaderboard-content" data-leaderboard>
            <div class="game2048__loading">순위를 불러오는 중...</div>
        </div>
    </div>

    <?php if ($isLoggedIn): ?>
    <!-- Personal history (logged-in users only) -->
    <div class="game2048__history">
        <h2 class="game2048__section-title">내 기록</h2>
        <div class="game2048__history-content" data-history>
            <div class="game2048__loading">기록을 불러오는 중...</div>
        </div>
    </div>
    <?php endif; ?>
</main>

<!-- Win modal -->
<div class="game2048__modal" data-modal aria-hidden="true" role="dialog">
    <div class="game2048__modal-overlay" data-modal-close></div>
    <div class="game2048__modal-content">
        <h2 class="game2048__modal-title">축하합니다!</h2>
        <p class="game2048__modal-message">2048 타일을 만들었습니다!</p>

        <div class="game2048__modal-stats">
            <div class="game2048__modal-stat">
                <div class="game2048__modal-stat-label">시간</div>
                <div class="game2048__modal-stat-value" data-modal-time>0:00.00</div>
            </div>
            <div class="game2048__modal-stat">
                <div class="game2048__modal-stat-label">이동</div>
                <div class="game2048__modal-stat-value" data-modal-moves>0</div>
            </div>
        </div>

        <?php if ($isLoggedIn): ?>
        <div class="game2048__modal-actions">
            <button class="game2048__btn game2048__btn--primary" data-submit-score>
                기록 제출
            </button>
            <button class="game2048__btn game2048__btn--secondary" data-continue>
                계속 플레이
            </button>
        </div>
        <?php else: ?>
        <div class="game2048__modal-message game2048__modal-message--info">
            로그인하시면 순위표에 기록을 등록할 수 있습니다.
        </div>
        <div class="game2048__modal-actions">
            <a href="/?act=dispMemberLoginForm" class="game2048__btn game2048__btn--primary">
                로그인
            </a>
            <button class="game2048__btn game2048__btn--secondary" data-continue>
                계속 플레이
            </button>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Screen reader announcements -->
<div role="status" aria-live="polite" class="visually-hidden" data-sr-announce></div>

<!-- Shared scripts -->
<script src="/fun/common/header.js" defer></script>

<!-- Game scripts -->
<script src="/fun/game2048/js/app.js?v=1.1" defer></script>
</body>
</html>
