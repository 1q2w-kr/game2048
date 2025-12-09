<?php
/**
 * 2048 Game API
 *
 * Actions:
 * - submit: Submit a completed game score
 * - leaderboard: Get top scores (logged-in users only)
 * - history: Get personal game history
 */

header('Content-Type: application/json; charset=utf-8');

// Enable error reporting for development
ini_set('display_errors', 0);
error_reporting(E_ALL);

// CORS and CSRF protection
$origin = $_SERVER['HTTP_ORIGIN'] ?? $_SERVER['HTTP_REFERER'] ?? '';
$host = parse_url($origin, PHP_URL_HOST);
$allowedHosts = ['1q2w.kr', 'localhost', '127.0.0.1'];

if ($origin && !in_array($host, $allowedHosts, true)) {
    jsonError('invalid_origin', 'Request origin not allowed', 403);
}

// Integrate with Rhymix
$authBridgePaths = [
    '/www/fun/common/rhymix_bridge.php',
    __DIR__ . '/../../common/rhymix_bridge.php',
];

foreach ($authBridgePaths as $path) {
    if (file_exists($path)) {
        require_once $path;
        break;
    }
}

// Get current user
$sessionUser = function_exists('rhxCurrentUser') ? rhxCurrentUser() : ['loggedIn' => false];
$isLoggedIn = !empty($sessionUser['loggedIn']);
$memberSrl = $sessionUser['memberSrl'] ?? null;

// Database connection
$FUN_DB_ENV_PREFIX = 'GAME2048';

$dbConfigPaths = [
    '/www/fun/common/db.php',
    __DIR__ . '/../../common/db.php',
];

foreach ($dbConfigPaths as $path) {
    if (file_exists($path)) {
        require_once $path;
        break;
    }
}

if (!isset($conn)) {
    jsonError('database_error', 'Database connection failed', 500);
}

// Auto-run migrations if needed
ensureDatabaseSchema($conn);

// Get request method and action
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
    if ($method === 'POST') {
        $payload = json_decode(file_get_contents('php://input') ?: 'null', true);

        if (!is_array($payload) || empty($payload['action'])) {
            throw new RuntimeException('INVALID_PAYLOAD');
        }

        $action = $payload['action'];

        switch ($action) {
            case 'submit':
                handleSubmit($conn, $payload, $memberSrl);
                break;

            default:
                throw new RuntimeException('UNKNOWN_ACTION');
        }
    } elseif ($method === 'GET') {
        $action = $_GET['action'] ?? '';

        switch ($action) {
            case 'leaderboard':
                handleLeaderboard($conn, $_GET);
                break;

            case 'history':
                handleHistory($conn, $memberSrl, $_GET);
                break;

            default:
                throw new RuntimeException('UNKNOWN_ACTION');
        }
    } else {
        throw new RuntimeException('METHOD_NOT_ALLOWED');
    }
} catch (RuntimeException $e) {
    jsonError($e->getMessage(), 'Request failed', 400);
} catch (Throwable $e) {
    error_log('2048 API Error: ' . $e->getMessage());
    jsonError('INTERNAL_ERROR', 'An error occurred', 500);
}

/**
 * Handle score submission
 */
function handleSubmit($conn, $payload, $memberSrl) {
    // Validate required fields
    $sessionToken = $payload['sessionToken'] ?? '';
    $completionTimeMs = (int)($payload['completionTimeMs'] ?? 0);
    $moveCount = (int)($payload['moveCount'] ?? 0);
    $finalBoard = $payload['finalBoard'] ?? null;

    if (strlen($sessionToken) !== 36) {
        throw new RuntimeException('INVALID_SESSION_TOKEN');
    }

    // Anti-cheat validation
    if ($completionTimeMs < 5000) {
        throw new RuntimeException('TIME_TOO_SHORT');
    }

    if ($moveCount < 50) {
        throw new RuntimeException('MOVE_COUNT_TOO_LOW');
    }

    if (!is_array($finalBoard) || count($finalBoard) !== 4) {
        throw new RuntimeException('INVALID_BOARD_STATE');
    }

    // Verify 2048 tile exists
    $has2048 = false;
    foreach ($finalBoard as $row) {
        if (!is_array($row) || count($row) !== 4) {
            throw new RuntimeException('INVALID_BOARD_STATE');
        }
        if (in_array(2048, $row, true)) {
            $has2048 = true;
            break;
        }
    }

    if (!$has2048) {
        throw new RuntimeException('NO_2048_TILE');
    }

    // Generate identity hash
    $identityHash = hash('sha256', $memberSrl ? ('member_' . $memberSrl) : ('guest_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . ($_SERVER['HTTP_USER_AGENT'] ?? '')));

    // Check for duplicate session token
    $stmt = $conn->prepare("SELECT score_id FROM game2048_scores WHERE session_token = ?");
    $stmt->bind_param('s', $sessionToken);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->fetch_assoc()) {
        throw new RuntimeException('DUPLICATE_SUBMISSION');
    }

    // Insert score
    $finalBoardJson = json_encode($finalBoard, JSON_UNESCAPED_UNICODE);

    $stmt = $conn->prepare(
        "INSERT INTO game2048_scores
        (member_srl, identity_hash, session_token, completion_time_total_ms, move_count, final_board_state, created_at)
        VALUES (?, ?, ?, ?, ?, ?, NOW())"
    );

    $stmt->bind_param('isssis', $memberSrl, $identityHash, $sessionToken, $completionTimeMs, $moveCount, $finalBoardJson);

    if (!$stmt->execute()) {
        throw new RuntimeException('INSERT_FAILED');
    }

    // Calculate rank (only for logged-in users)
    $rank = null;
    $totalEntries = 0;
    $isPersonalBest = false;

    if ($memberSrl) {
        // Get rank
        $stmt = $conn->prepare(
            "SELECT COUNT(*) as rank FROM game2048_scores
            WHERE member_srl IS NOT NULL
            AND (completion_time_total_ms < ? OR (completion_time_total_ms = ? AND move_count < ?))"
        );
        $stmt->bind_param('iii', $completionTimeMs, $completionTimeMs, $moveCount);
        $stmt->execute();
        $rankData = $stmt->get_result()->fetch_assoc();
        $rank = (int)$rankData['rank'] + 1;

        // Get total entries
        $stmt = $conn->prepare("SELECT COUNT(*) as total FROM game2048_scores WHERE member_srl IS NOT NULL");
        $stmt->execute();
        $totalData = $stmt->get_result()->fetch_assoc();
        $totalEntries = (int)$totalData['total'];

        // Check if personal best
        $stmt = $conn->prepare(
            "SELECT COUNT(*) as count FROM game2048_scores
            WHERE member_srl = ?
            AND (completion_time_total_ms < ? OR (completion_time_total_ms = ? AND move_count < ?))"
        );
        $stmt->bind_param('iiii', $memberSrl, $completionTimeMs, $completionTimeMs, $moveCount);
        $stmt->execute();
        $pbData = $stmt->get_result()->fetch_assoc();
        $isPersonalBest = (int)$pbData['count'] === 0;
    }

    jsonSuccess([
        'rank' => $rank,
        'totalEntries' => $totalEntries,
        'isPersonalBest' => $isPersonalBest,
    ]);
}

/**
 * Handle leaderboard request
 */
function handleLeaderboard($conn, $params) {
    $limit = min(100, max(1, (int)($params['limit'] ?? 50)));

    $sql = "SELECT
                s.completion_time_total_ms,
                s.move_count,
                s.created_at,
                COALESCE(m.nick_name, '익명') AS nickname
            FROM game2048_scores s
            LEFT JOIN rhymix_member m ON s.member_srl = m.member_srl
            WHERE s.member_srl IS NOT NULL
            ORDER BY s.completion_time_total_ms ASC, s.move_count ASC
            LIMIT ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $limit);
    $stmt->execute();
    $result = $stmt->get_result();

    $scores = [];
    $rank = 1;
    while ($row = $result->fetch_assoc()) {
        $scores[] = [
            'rank' => $rank++,
            'nickname' => $row['nickname'],
            'time' => formatTime((int)$row['completion_time_total_ms']),
            'timeMs' => (int)$row['completion_time_total_ms'],
            'moves' => (int)$row['move_count'],
            'createdAt' => $row['created_at'],
        ];
    }

    jsonSuccess(['scores' => $scores]);
}

/**
 * Handle personal history request
 */
function handleHistory($conn, $memberSrl, $params) {
    if (!$memberSrl) {
        jsonSuccess(['scores' => []]);
        return;
    }

    $limit = min(50, max(1, (int)($params['limit'] ?? 10)));

    $stmt = $conn->prepare(
        "SELECT completion_time_total_ms, move_count, created_at
        FROM game2048_scores
        WHERE member_srl = ?
        ORDER BY created_at DESC
        LIMIT ?"
    );

    $stmt->bind_param('ii', $memberSrl, $limit);
    $stmt->execute();
    $result = $stmt->get_result();

    $scores = [];
    while ($row = $result->fetch_assoc()) {
        $scores[] = [
            'time' => formatTime((int)$row['completion_time_total_ms']),
            'timeMs' => (int)$row['completion_time_total_ms'],
            'moves' => (int)$row['move_count'],
            'createdAt' => $row['created_at'],
        ];
    }

    jsonSuccess(['scores' => $scores]);
}

/**
 * Format milliseconds as MM:SS.ms
 */
function formatTime($totalMs) {
    $min = floor($totalMs / 60000);
    $sec = floor(($totalMs % 60000) / 1000);
    $ms = floor(($totalMs % 1000) / 10);

    return sprintf('%d:%02d.%02d', $min, $sec, $ms);
}

/**
 * Send JSON success response
 */
function jsonSuccess($data) {
    echo json_encode(array_merge(['ok' => true], $data), JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Send JSON error response
 */
function jsonError($error, $message, $code = 400) {
    http_response_code($code);
    echo json_encode([
        'ok' => false,
        'error' => $error,
        'message' => $message,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Ensure database schema is initialized
 * Auto-runs migrations if game2048_scores table doesn't exist
 */
function ensureDatabaseSchema($conn) {
    static $checked = false;

    if ($checked) {
        return;
    }

    $checked = true;

    try {
        // Check if game2048_scores table exists
        $result = $conn->query("SHOW TABLES LIKE 'game2048_scores'");

        if ($result && $result->num_rows > 0) {
            // Table exists, no migration needed
            return;
        }

        // Table doesn't exist, run migration
        $migrationPaths = [
            '/www/fun/game2048/db/migrations/0001_init.sql',
            __DIR__ . '/../db/migrations/0001_init.sql',
        ];

        $migrationFile = null;
        foreach ($migrationPaths as $path) {
            if (file_exists($path)) {
                $migrationFile = $path;
                break;
            }
        }

        if (!$migrationFile) {
            error_log('game2048: Migration file not found');
            return;
        }

        $sql = file_get_contents($migrationFile);
        if ($sql === false) {
            error_log('game2048: Failed to read migration file');
            return;
        }

        // Execute migration
        if ($conn->multi_query($sql)) {
            // Clear all results
            do {
                if ($result = $conn->store_result()) {
                    $result->free();
                }
            } while ($conn->more_results() && $conn->next_result());

            error_log('game2048: Database schema initialized successfully');
        } else {
            error_log('game2048: Migration failed - ' . $conn->error);
        }

    } catch (Throwable $e) {
        error_log('game2048: Database schema check failed - ' . $e->getMessage());
    }
}
