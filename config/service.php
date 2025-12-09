<?php
/**
 * 2048 Game Service Configuration
 */

return [
    'slug' => 'game2048',
    'name' => '2048 Game',
    'description' => '고전 2048 퍼즐 게임. 최단 시간 기록에 도전하세요!',
    'version' => '1.0.0',
    'features' => [
        'requires_login' => false,           // Anyone can play
        'has_leaderboard' => true,           // Has ranking system
        'leaderboard_requires_login' => true, // Only logged-in users on leaderboard
        'has_personal_history' => true,      // Track user's game history
    ],
];
