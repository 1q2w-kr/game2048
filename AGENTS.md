# AGENTS.md - 2048 Game Development Guide

Development workflow and patterns for working on the 2048 game service.

## Development Environment

### Local Setup
```bash
# Start Docker stack
docker compose up -d

# Access service
http://localhost:8000/fun/game2048/

# View logs
docker compose logs -f web
```

## Service Status Guard
- `index.php` and `api/game.php` must call `fun_service_require_enabled('game2048')` from `fun/common/service/guard.php` so `operating`/`construction`/`blocked` states apply.

### Database Migration
```bash
# Connect to DB container
docker compose exec db mariadb -u ciiwol -p ciiwol

# Run migration
source /var/www/html/fun/game2048/dbinit/0001_init.sql

# Verify table
SHOW TABLES LIKE 'game2048_scores';
DESCRIBE game2048_scores;
```

## Code Patterns

### PHP API Response Pattern
```php
// Success
function jsonSuccess($data) {
    echo json_encode(array_merge(['ok' => true], $data), JSON_UNESCAPED_UNICODE);
    exit;
}

// Error
function jsonError($error, $message, $code = 400) {
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $error, 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}
```

### JavaScript Fetch Pattern
```javascript
async function callAPI(action, data = null) {
    const url = '/fun/game2048/api/game.php' + (data ? '' : `?action=${action}`);
    const options = {
        method: data ? 'POST' : 'GET',
        credentials: 'same-origin',
    };

    if (data) {
        options.headers = { 'Content-Type': 'application/json' };
        options.body = JSON.stringify({ action, ...data });
    }

    const response = await fetch(url, options);
    return response.json();
}
```

### Board Rendering Pattern
```javascript
render() {
    this.boardEl.innerHTML = '';

    for (let r = 0; r < 4; r++) {
        for (let c = 0; c < 4; c++) {
            const value = this.game.board[r][c];
            const tile = document.createElement('div');
            tile.className = 'game2048__tile';

            if (value > 0) {
                tile.classList.add('game2048__tile--filled');
                tile.setAttribute('data-value', value);
                tile.textContent = value;
            }

            this.boardEl.appendChild(tile);
        }
    }
}
```

## Testing Workflow

### Manual Testing Steps

1. **Guest Play**
   - Open game without logging in
   - Play until win/lose
   - Verify no leaderboard submission option

2. **Logged-in Play**
   - Log in to Rhymix
   - Play until win
   - Verify submit button appears
   - Submit score
   - Check leaderboard for entry
   - Check personal history

3. **Input Testing**
   - Keyboard: Press all arrow keys
   - Touch: Swipe in all 4 directions on mobile
   - Buttons: Click all 4 direction buttons

4. **Edge Cases**
   - Win on exact 50 moves
   - Try to submit duplicate session token (should fail)
   - Submit invalid board state (no 2048 tile - should fail)

### Debugging Tips

**Check Browser Console**:
```javascript
// View auth state
console.log(window.__FUN_AUTH_STATE__);

// View game board
console.log(gameUI.game.board);

// Check timer
console.log(gameUI.timer.elapsed);
```

**Check PHP Errors**:
```bash
# In api/game.php, temporarily enable:
ini_set('display_errors', 1);
error_reporting(E_ALL);
```

**Check Database**:
```sql
-- View recent scores
SELECT * FROM game2048_scores ORDER BY created_at DESC LIMIT 10;

-- Check for duplicates
SELECT session_token, COUNT(*) FROM game2048_scores GROUP BY session_token HAVING COUNT(*) > 1;

-- View leaderboard
SELECT member_srl, completion_time_total_ms, move_count
FROM game2048_scores
WHERE member_srl IS NOT NULL
ORDER BY completion_time_total_ms ASC, move_count ASC
LIMIT 10;
```

## Common Issues

### Issue: "Database connection failed"
**Cause**: `fun/common/db.php` not found or database credentials wrong

**Solution**:
```bash
# Check db.php exists
ls -la fun/common/db.php

# Verify credentials in .env
cat .env | grep DB_
```

### Issue: "Session token not unique"
**Cause**: Browser cache or duplicate submission

**Solution**:
```javascript
// Force new session token
this.game.sessionToken = crypto.randomUUID();
```

### Issue: Board rendering blank
**Cause**: JavaScript error or DOM not ready

**Solution**:
```javascript
// Check DOM ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
} else {
    init();
}
```

### Issue: Touch gestures not working
**Cause**: Missing `passive: true` on touch events

**Solution**:
```javascript
element.addEventListener('touchstart', handler, { passive: true });
```

## Performance Optimization

### Database Indexes
Ensure these indexes exist for optimal query performance:
```sql
-- Check indexes
SHOW INDEX FROM game2048_scores;

-- Should have:
-- idx_ranking (completion_time_total_ms, move_count)
-- idx_member_history (member_srl, created_at DESC)
-- uq_session_token (session_token)
```

### Frontend Optimization
- Board rendering is O(n²) for 4x4 grid - acceptable
- Timer updates every 10ms - minimal CPU usage
- No tile animations - reduces layout thrashing
- Use `requestAnimationFrame` if adding animations

## Deployment Checklist

Before deploying changes:

- [ ] Test locally in Docker
- [ ] Verify guest play works
- [ ] Verify logged-in play works
- [ ] Check leaderboard loads
- [ ] Check personal history loads
- [ ] Test mobile viewport (no zoom, no scroll)
- [ ] Verify dark mode
- [ ] Run database migration if schema changed
- [ ] Update version in `config/service.php`
- [ ] Commit changes to git

## Git Workflow

```bash
# Make changes
git add fun/game2048/

# Commit with descriptive message
git commit -m "feat(game2048): Add sound effects on tile merge"

# Push to remote
git push origin main

# GitHub Actions will auto-deploy to /www/fun/game2048/
```

## Troubleshooting Production Issues

### Check Deployed Files
```bash
# SSH to production server
ls -la /www/fun/game2048/

# Verify file permissions
chmod 644 /www/fun/game2048/index.php
chmod 644 /www/fun/game2048/api/game.php
chmod 755 /www/fun/game2048/js/
chmod 755 /www/fun/game2048/css/
```

### Check PHP Logs
```bash
# View PHP error log
tail -f /var/log/php/error.log

# Or in Docker:
docker compose logs -f web | grep game2048
```

### Check Database Connection
```php
// Add to api/game.php for debugging
error_log('DB connection: ' . ($conn ? 'OK' : 'FAILED'));
error_log('Member SRL: ' . $memberSrl);
```

## Code Style Guidelines

- **PHP**: Follow PSR-12 (but this is a simple service, so not strict)
- **JavaScript**: Use ES6+ features (classes, arrow functions, destructuring)
- **CSS**: Use BEM-like naming (`game2048__element--modifier`)
- **Comments**: Add comments for complex logic (e.g., tile merge algorithm)

## Security Checklist

- [ ] All user input sanitized (prepared statements)
- [ ] CSRF protection on API (origin check)
- [ ] Session token uniqueness enforced (DB constraint)
- [ ] Board validation on server (not client)
- [ ] Time/move sanity checks
- [ ] No SQL injection vectors
- [ ] No XSS vectors (HTML escaped in leaderboard nicknames)

## Resources

- **Rhymix Docs**: https://www.rhymix.org
- **2048 Original**: https://github.com/gabrielecirulli/2048 (reference for game logic)
- **Fun Common Patterns**: See `/fun/common/AGENTS.md`
- **Database Schema**: See `dbinit/0001_init.sql`
