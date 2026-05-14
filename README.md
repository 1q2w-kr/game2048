# 2048 Game

고전 2048 퍼즐 게임. 최단 시간 기록에 도전하세요!

## Features

- 🎮 Classic 2048 gameplay with smooth animations
- ⏱️ Precise timer (millisecond accuracy) - starts on first move
- 🏆 Global leaderboard ranked by time, then move count
- 📊 Personal game history for logged-in users
- 📱 Mobile-optimized with touch gestures
- ⌨️ Full keyboard support (arrow keys)
- 🎨 Dark mode support
- ♿ Screen reader accessible

## Controls

### Desktop
- Arrow keys (↑ ↓ ← →)
- On-screen directional buttons

### Mobile
- Swipe gestures
- On-screen directional buttons

## Gameplay

1. Combine tiles with the same number to create larger numbers
2. Reach the **2048** tile to win
3. Your time and move count are recorded
4. Log in to submit your score to the leaderboard

## Ranking System

- **Primary**: Completion time (lower is better)
- **Tiebreaker**: Move count (fewer is better)
- Only logged-in users appear on the leaderboard

## Technical Stack

- **Frontend**: Vanilla JavaScript (no dependencies)
- **Backend**: PHP 8.4 with MySQLi
- **Database**: MariaDB 10.11
- **Auth**: Gnuboard7 CMS integration

## File Structure

```
fun/game2048/
├── index.php           # Main entry point
├── api/
│   └── game.php       # REST API
├── config/
│   └── service.php    # Service metadata
├── js/
│   └── app.js         # Game logic
├── css/
│   └── app.css        # Styling
└── db/
    └── migrations/
        └── 0001_init.sql  # Database schema
```

## API Endpoints

### Submit Score
```http
POST /fun/game2048/api/game.php
Content-Type: application/json

{
  "action": "submit",
  "sessionToken": "uuid-v4",
  "completionTimeMs": 123456,
  "moveCount": 87,
  "finalBoard": [[...], [...], [...], [...]]
}
```

### Get Leaderboard
```http
GET /fun/game2048/api/game.php?action=leaderboard&limit=50
```

### Get Personal History
```http
GET /fun/game2048/api/game.php?action=history&limit=10
```

## Development

### Local Setup
1. Ensure Docker stack is running (`docker compose up`)
2. Navigate to `http://localhost:8000/fun/game2048/`
3. Database schema is **auto-initialized** on first API call

### Database Migration
The database schema is automatically created when the API is first accessed. No manual migration is required.

**How it works:**
- On first API call, `ensureDatabaseSchema()` checks if `game2048_scores` table exists
- If missing, automatically runs `dbinit/0001_init.sql`
- Logs success/failure to PHP error log

**Manual migration (optional):**
```bash
# Only needed if auto-migration fails
docker compose exec db mariadb -u ciiwol -p ciiwol
source /var/www/html/fun/game2048/dbinit/0001_init.sql
```

### DB Init Quickstart
1) Apply once:
```bash
mysql -u <user> -p <db> < fun/game2048/dbinit/0001_init.sql
```
2) The API checks `information_schema` and runs init only if the table is missing.

## Security

- Session token uniqueness prevents duplicate submissions
- Board state validation ensures 2048 tile exists
- Time/move sanity checks prevent unrealistic scores
- SQL injection prevention via prepared statements
- CSRF protection on API endpoints

## License

Part of the 1q2w.kr Fun services collection.
