-- 2048 Game Scores Table
-- Stores completion times and move counts for ranking

CREATE TABLE IF NOT EXISTS game2048_scores (
    score_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    -- Identity tracking
    member_srl BIGINT UNSIGNED NULL COMMENT 'Rhymix member ID for logged-in users',
    identity_hash CHAR(64) NOT NULL COMMENT 'SHA256 hash for anonymization',
    session_token CHAR(32) NOT NULL COMMENT 'Unique game session token (prevents duplicate submissions)',

    -- Game metrics
    completion_time_total_ms INT UNSIGNED NOT NULL COMMENT 'Total time in milliseconds to reach 2048',
    move_count INT UNSIGNED NOT NULL COMMENT 'Number of moves made to reach 2048',
    final_board_state JSON NULL COMMENT 'Final 4x4 grid state for anti-cheat verification',

    -- Timestamps
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'When the score was submitted',

    -- Indexes for efficient queries
    UNIQUE KEY uq_session_token (session_token),
    INDEX idx_ranking (completion_time_total_ms, move_count),
    INDEX idx_member_history (member_srl, created_at DESC),
    INDEX idx_identity (identity_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='2048 game completion scores and rankings';
