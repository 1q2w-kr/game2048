# CLAUDE.md - 2048 Game Service

Service-specific guidance for Claude Code when working with the 2048 game service.

## Service Overview

2048 Game is a Fun service that implements the classic 2048 sliding puzzle game with time-based competitive ranking. Players combine numbered tiles to reach 2048, with scores ranked by completion time (primary) and move count (tiebreaker).

## Architecture

### Structure Pattern
This service follows the **simple structure** pattern (pickaju-style):
- Flat file organization (no namespace/autoloading)
- Direct PHP entry point (`index.php`)
- Vanilla JavaScript (no build tools or frameworks)
- Direct database access via `fun/common/db.php`

### Key Design Decisions

1. **Guest Play Allowed**: Anyone can play the game without logging in
2. **Leaderboard Login Required**: Only logged-in users' scores appear on the global leaderboard
3. **Time-First Ranking**: Primary ranking is by completion time (milliseconds), with move count as tiebreaker
4. **Session Token Anti-Cheat**: Client-generated UUID prevents duplicate score submissions
5. **Board State Validation**: Server verifies 2048 tile exists in submitted board

## File Organization

```
fun/game2048/
├── index.php              # Main UI entry point with Rhymix integration
├── api/
│   └── game.php          # REST API (submit, leaderboard, history)
├── config/
│   └── service.php       # Service metadata
├── js/
│   └── app.js            # Game logic, UI, input handling
├── css/
│   └── app.css           # Styling with mobile constraints
└── db/
    └── migrations/
        └── 0001_init.sql # Database schema
```

## Database Schema

### Table: `game2048_scores`

**Purpose**: Store completion times and move counts for ranking

**Key Fields**:
- `member_srl`: Links to Rhymix users (NULL for anonymous plays)
- `identity_hash`: SHA256 hash for anonymization
- `session_token`: UUID v4 to prevent duplicate submissions
- `completion_time_total_ms`: Primary ranking metric (lower = better)
- `move_count`: Secondary ranking metric (tiebreaker)
- `final_board_state`: JSON of 4x4 grid for anti-cheat verification

**Indexes**:
- `idx_ranking (completion_time_total_ms, move_count)` - Leaderboard queries
- `idx_member_history (member_srl, created_at DESC)` - Personal history
- `uq_session_token` - Prevent duplicates

### Auto-Migration

Database schema is **automatically initialized** on first API call. No manual intervention required.

**How it works:**
1. `api/game.php` calls `ensureDatabaseSchema($conn)` after DB connection
2. Function checks if `game2048_scores` table exists using `SHOW TABLES`
3. If missing, reads and executes `db/migrations/0001_init.sql`
4. Uses `multi_query()` to handle multi-statement SQL
5. Logs success/failure to PHP error log

**Static guard:** `$checked` flag prevents redundant checks within same request.

**Fallback paths:**
- Production: `/www/fun/game2048/db/migrations/0001_init.sql`
- Local: `__DIR__ . '/../db/migrations/0001_init.sql'`

**Manual migration (if needed):**
```bash
# SSH to production or Docker exec locally
mysql -u user -p database < /www/fun/game2048/db/migrations/0001_init.sql
```

## API Design

### POST /fun/game2048/api/game.php

**Action: submit**
- Validates session token uniqueness
- Checks time >= 5000ms (minimum realistic)
- Checks move count >= 50 (minimum realistic)
- Verifies 2048 tile exists in `finalBoard`
- Returns rank, total entries, and personal best flag

**Action: leaderboard** (GET)
- Returns top N scores (default 50, max 100)
- LEFT JOIN with `rhymix_member` for nicknames
- Only includes logged-in users (`member_srl IS NOT NULL`)

**Action: history** (GET)
- Returns user's recent games (default 10, max 50)
- Filtered by `member_srl` for logged-in users
- Empty array for guests

## Game Logic (js/app.js)

### Core Classes

1. **Game2048**
   - Manages board state (4x4 array)
   - Implements slide + merge logic
   - Win/lose detection
   - Session token generation

2. **Timer**
   - Starts on first move (`performance.now()`)
   - Tracks milliseconds with 10ms precision
   - Display format: `MM:SS.ms`

3. **GameUI**
   - Orchestrates game flow
   - Handles input (keyboard, touch, buttons)
   - Renders board and tiles
   - API communication
   - Modal management

### Input Handling

**Keyboard**: `keydown` listener for Arrow keys
**Touch**: `touchstart`/`touchend` with delta calculation (threshold 30px)
**Buttons**: Click handlers on `[data-direction]` elements

### Tile Movement Algorithm

1. Rotate board so movement is always "left"
2. For each row:
   - Compress: Move non-zero tiles left
   - Merge: Combine adjacent identical tiles
   - Compress again: Close gaps
3. Rotate board back to original orientation
4. If board changed: increment move count, add random tile

## Styling (css/app.css)

### Mobile Optimization

**Viewport Meta**:
```html
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
```

**CSS Constraints**:
- `touch-action: manipulation` - Disable zoom gestures
- `overflow: hidden` on html/body - Prevent scrolling
- `padding-top: 52px` on body - Account for fixed header

### Tile Colors

Classic 2048 color scheme:
- 2/4: Beige tones with dark text
- 8+: Orange to red gradient with light text
- 128+: Yellow gradient with light text
- 2048: Bright gold with light text

Font sizes scale down for larger numbers (1024: 1.75rem, 2048: 1.75rem)

### Dark Mode

Uses CSS custom properties with `@media (prefers-color-scheme: dark)`:
- Background: `--game2048-bg` switches to dark blue
- Surface: `--game2048-surface` becomes translucent
- Accent: `--game2048-accent` switches from blue to cyan

## Authentication Flow

**Server-side** (`index.php`):
```php
require_once __DIR__ . '/../common/rhymix_bridge.php';
$sessionUser = function_exists('rhxCurrentUser') ? rhxCurrentUser() : ['loggedIn' => false];
$isLoggedIn = !empty($sessionUser['loggedIn']);
```

**Client-side** (js/app.js):
```javascript
this.isLoggedIn = window.__FUN_AUTH_STATE__?.loggedIn || false;
```

**Feature Matrix**:
| Feature | Guest | Logged-in |
|---------|-------|-----------|
| Play game | ✓ | ✓ |
| Submit to leaderboard | ✗ | ✓ |
| View leaderboard | ✓ | ✓ |
| Personal history | ✗ | ✓ |

## Anti-Cheat Measures

1. **Session Token Uniqueness**: Client generates UUID v4, server enforces UNIQUE constraint
2. **Board Validation**: Verify 2048 tile exists in `finalBoard` JSON
3. **Time Sanity Check**: Reject submissions < 5 seconds
4. **Move Count Validation**: Reject submissions < 50 moves
5. **No Client-Side Score Calculation**: Server computes rank, not client

## Common Modifications

### Adjusting Leaderboard Size
Edit `api/game.php`:
```php
$limit = min(100, max(1, (int)($params['limit'] ?? 50)));
// Change 100 to new max, 50 to new default
```

### Changing Tile Colors
Edit `css/app.css`:
```css
--tile-2048: #edc22e; /* Change to desired color */
```

### Adding Sound Effects
1. Add audio files to `sounds/` directory
2. In `js/app.js`, add Audio objects:
   ```javascript
   this.sounds = {
       move: new Audio('/fun/game2048/sounds/move.mp3'),
       merge: new Audio('/fun/game2048/sounds/merge.mp3'),
       win: new Audio('/fun/game2048/sounds/win.mp3'),
   };
   ```
3. Play on events:
   ```javascript
   if (moved) {
       this.sounds.move.play();
   }
   ```

## Testing Checklist

- [ ] Play game as guest (no errors)
- [ ] Win game as guest (modal shows, no submit button)
- [ ] Play game as logged-in user
- [ ] Win game as logged-in user (modal shows submit button)
- [ ] Submit score (appears on leaderboard)
- [ ] Check personal history (shows submitted games)
- [ ] Test keyboard controls (all 4 directions)
- [ ] Test touch swipes on mobile (all 4 directions)
- [ ] Test on-screen buttons (all 4 directions)
- [ ] Verify mobile viewport (no zoom, no scroll)
- [ ] Test dark mode rendering
- [ ] Verify screen reader announcements

## Deployment

This service uses the root workspace's GitHub Actions workflow for FTP deployment. When modifying this service:

1. Make changes in this directory
2. Test locally with Docker
3. Commit and push to repository
4. GitHub Actions will deploy to `/www/fun/game2048/`

**Database Migration**:
Run `db/migrations/0001_init.sql` manually on production database after initial deployment.

## Dependencies

- **Rhymix CMS**: Authentication and user management
- **fun/common/rhymix_bridge.php**: Auth integration
- **fun/common/header.php**: Shared navigation header
- **fun/common/theme.css**: Shared color tokens
- **MariaDB/MySQL**: Database with JSON support

## Performance Considerations

- Board rendering is full replace (not differential) - acceptable for 4x4 grid
- No tile movement animations (simplified for performance)
- Leaderboard queries use indexed columns (`completion_time_total_ms`, `move_count`)
- Personal history limited to 50 recent games

## Known Limitations

1. **No Undo**: Game doesn't support undoing moves
2. **No Save/Resume**: Each game session is independent
3. **Timer Precision**: Limited to 10ms intervals (good enough for ranking)
4. **No Animations**: Tiles don't slide smoothly (would require position tracking)

## Future Enhancements

Potential improvements for future iterations:

- [ ] Daily/weekly leaderboards
- [ ] Achievements system (e.g., "Reach 2048 in under 3 minutes")
- [ ] Best tile tracker (max tile reached per game)
- [ ] Score sharing (social media integration)
- [ ] Tile movement animations
- [ ] Sound effects
- [ ] Game replay feature
