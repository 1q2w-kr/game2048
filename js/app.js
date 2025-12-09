/**
 * 2048 Game - JavaScript Game Logic
 */

(function() {
    'use strict';

    // ======================
    // Game2048 Class
    // ======================
    class Game2048 {
        constructor() {
            this.board = this.createEmptyBoard();
            this.moveCount = 0;
            this.hasStarted = false;
            this.startTime = null;
            this.sessionToken = this.generateSessionToken();
            this.hasWon = false;
            this.winTime = null;
            this.scoreSubmitted = false;
            this.hasLost = false;
            this.lastAddedTile = null;
            this.lastMoveDirection = null;

            this.initBoard();
        }

        createEmptyBoard() {
            return Array(4).fill(null).map(() => Array(4).fill(0));
        }

        initBoard() {
            this.addRandomTile();
            this.lastAddedTile = this.addRandomTile();
        }

        generateSessionToken() {
            // Simple UUID v4 generator
            return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
                const r = Math.random() * 16 | 0;
                const v = c === 'x' ? r : (r & 0x3 | 0x8);
                return v.toString(16);
            });
        }

        addRandomTile() {
            const emptyCells = [];

            for (let r = 0; r < 4; r++) {
                for (let c = 0; c < 4; c++) {
                    if (this.board[r][c] === 0) {
                        emptyCells.push({ r, c });
                    }
                }
            }

            if (emptyCells.length === 0) {
                return null;
            }

            const { r, c } = emptyCells[Math.floor(Math.random() * emptyCells.length)];
            const value = Math.random() < 0.9 ? 2 : 4;
            this.board[r][c] = value;
            return { r, c, value };
        }

        move(direction) {
            if (this.hasLost) {
                return false; // Can't move after losing
            }

            if (this.hasWon && this.scoreSubmitted) {
                return false; // Can't move after score is submitted
            }

            if (!this.hasStarted) {
                this.hasStarted = true;
                this.startTime = performance.now();
            }

            const oldBoard = JSON.stringify(this.board);
            this.board = this.applyMove(this.board, direction);

            if (JSON.stringify(this.board) === oldBoard) {
                this.lastMoveDirection = null;
                this.lastAddedTile = null;
                return false; // No change, invalid move
            }

            this.moveCount++;
            this.lastMoveDirection = direction;
            this.lastAddedTile = this.addRandomTile();

            if (!this.hasWon && this.checkWin()) {
                this.hasWon = true;
                this.winTime = performance.now() - this.startTime;
            }

            if (!this.hasWon && this.checkLose()) {
                this.hasLost = true;
            }

            return true;
        }

        applyMove(board, direction) {
            // Rotate board so we always move "left"
            const rotated = this.rotateBoard(board, direction);

            // Apply left move logic
            const moved = rotated.map(row => this.slideAndMerge(row));

            // Rotate back
            return this.rotateBoard(moved, this.reverseDirection(direction));
        }

        slideAndMerge(row) {
            // Remove zeros
            let newRow = row.filter(val => val !== 0);

            // Merge adjacent identical tiles
            for (let i = 0; i < newRow.length - 1; i++) {
                if (newRow[i] === newRow[i + 1]) {
                    newRow[i] *= 2;
                    newRow[i + 1] = 0;
                    i++; // Skip merged tile
                }
            }

            // Remove zeros again
            newRow = newRow.filter(val => val !== 0);

            // Pad with zeros to length 4
            while (newRow.length < 4) {
                newRow.push(0);
            }

            return newRow;
        }

        rotateBoard(board, direction) {
            const size = 4;
            let rotated = board.map(row => [...row]);

            switch (direction) {
                case 'up':
                    // Transpose then reverse each row
                    rotated = this.transpose(rotated);
                    break;

                case 'down':
                    // Reverse each row then transpose
                    rotated = rotated.map(row => row.reverse());
                    rotated = this.transpose(rotated);
                    rotated = rotated.map(row => row.reverse());
                    break;

                case 'right':
                    // Reverse each row
                    rotated = rotated.map(row => row.reverse());
                    break;

                case 'left':
                default:
                    // No rotation needed
                    break;
            }

            return rotated;
        }

        transpose(board) {
            return board[0].map((_, colIndex) => board.map(row => row[colIndex]));
        }

        reverseDirection(direction) {
            const reverseMap = {
                'left': 'left',
                'right': 'right',
                'up': 'up',
                'down': 'down',
            };
            return reverseMap[direction];
        }

        checkWin() {
            for (let r = 0; r < 4; r++) {
                for (let c = 0; c < 4; c++) {
                    if (this.board[r][c] === 2048) {
                        return true;
                    }
                }
            }
            return false;
        }

        checkLose() {
            // Check if board is full
            for (let r = 0; r < 4; r++) {
                for (let c = 0; c < 4; c++) {
                    if (this.board[r][c] === 0) {
                        return false; // Has empty cells
                    }
                }
            }

            // Check for possible merges
            for (let r = 0; r < 4; r++) {
                for (let c = 0; c < 4; c++) {
                    const current = this.board[r][c];

                    // Check right
                    if (c < 3 && this.board[r][c + 1] === current) {
                        return false;
                    }

                    // Check down
                    if (r < 3 && this.board[r + 1][c] === current) {
                        return false;
                    }
                }
            }

            return true; // No moves available
        }
    }

    // ======================
    // Timer Class
    // ======================
    class Timer {
        constructor(element) {
            this.element = element;
            this.startTime = null;
            this.interval = null;
            this.elapsed = 0;
        }

        start() {
            this.startTime = performance.now();
            this.interval = setInterval(() => this.update(), 10);
        }

        update() {
            this.elapsed = performance.now() - this.startTime;
            this.render();
        }

        render() {
            const totalMs = Math.floor(this.elapsed);
            const min = Math.floor(totalMs / 60000);
            const sec = Math.floor((totalMs % 60000) / 1000);
            const ms = Math.floor((totalMs % 1000) / 10);

            this.element.textContent = `${min}:${sec.toString().padStart(2, '0')}.${ms.toString().padStart(2, '0')}`;
        }

        stop() {
            if (this.interval) {
                clearInterval(this.interval);
                this.interval = null;
            }
            return Math.floor(this.elapsed);
        }

        reset() {
            this.stop();
            this.startTime = null;
            this.elapsed = 0;
            this.element.textContent = '0:00.00';
        }
    }

    // ======================
    // Game UI Controller
    // ======================
    class GameUI {
        constructor() {
            this.game = new Game2048();
            this.timer = new Timer(document.querySelector('[data-timer]'));
            this.movesEl = document.querySelector('[data-moves]');
            this.boardEl = document.querySelector('[data-board]');
            this.modalEl = document.querySelector('[data-modal]');
            this.srAnnounce = document.querySelector('[data-sr-announce]');
            this.gameOverEl = document.querySelector('[data-game-over]');
            this.boardSlideClasses = [
                'game2048__board--slide-left',
                'game2048__board--slide-right',
                'game2048__board--slide-up',
                'game2048__board--slide-down',
            ];

            this.isLoggedIn = window.__FUN_AUTH_STATE__?.loggedIn || false;

            this.init();
        }

        init() {
            this.setupEventListeners();
            this.render();
            this.loadLeaderboard();
            if (this.isLoggedIn) {
                this.loadHistory();
            }
        }

        setupEventListeners() {
            // New game button
            document.querySelector('[data-new-game]').addEventListener('click', () => {
                this.newGame();
            });

            // Keyboard controls
            document.addEventListener('keydown', (e) => {
                const keyMap = {
                    'ArrowUp': 'up',
                    'ArrowDown': 'down',
                    'ArrowLeft': 'left',
                    'ArrowRight': 'right',
                };

                if (keyMap[e.key]) {
                    e.preventDefault();
                    this.handleMove(keyMap[e.key]);
                }
            });

            // Button controls
            document.querySelectorAll('[data-direction]').forEach(btn => {
                btn.addEventListener('click', () => {
                    const direction = btn.dataset.direction;
                    this.handleMove(direction);
                });
            });

            // Touch gestures
            let touchStartX = 0;
            let touchStartY = 0;
            const SWIPE_THRESHOLD = 30;

            this.boardEl.addEventListener('touchstart', (e) => {
                touchStartX = e.touches[0].clientX;
                touchStartY = e.touches[0].clientY;
            }, { passive: true });

            this.boardEl.addEventListener('touchend', (e) => {
                const deltaX = e.changedTouches[0].clientX - touchStartX;
                const deltaY = e.changedTouches[0].clientY - touchStartY;

                if (Math.abs(deltaX) > Math.abs(deltaY)) {
                    if (Math.abs(deltaX) > SWIPE_THRESHOLD) {
                        this.handleMove(deltaX > 0 ? 'right' : 'left');
                    }
                } else {
                    if (Math.abs(deltaY) > SWIPE_THRESHOLD) {
                        this.handleMove(deltaY > 0 ? 'down' : 'up');
                    }
                }
            });

            // Modal actions
            document.querySelector('[data-modal-close]')?.addEventListener('click', () => {
                this.closeModal();
            });

            document.querySelector('[data-continue]')?.addEventListener('click', () => {
                this.closeModal();
            });

            document.querySelector('[data-submit-score]')?.addEventListener('click', () => {
                this.submitScore();
            });
        }

        handleMove(direction) {
            const moved = this.game.move(direction);

            if (moved) {
                // Start timer on first move
                if (this.game.moveCount === 1) {
                    this.timer.start();
                }

                this.animateBoard(direction);
                this.render();

                // Check win condition
                if (this.game.hasWon && !this.modalEl.classList.contains('game2048__modal--visible')) {
                    this.timer.stop();
                    this.showWinModal();
                }

                // Check lose condition
                if (this.game.hasLost) {
                    this.timer.stop();
                    this.showGameOver();
                }
            }
        }

        render() {
            // Update moves
            this.movesEl.textContent = this.game.moveCount;

            // Render board
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
                        tile.style.setProperty('--tile-r', r);
                        tile.style.setProperty('--tile-c', c);
                        if (this.game.lastAddedTile && this.game.lastAddedTile.r === r && this.game.lastAddedTile.c === c) {
                            tile.classList.add('game2048__tile--new');
                        }
                    }

                    this.boardEl.appendChild(tile);
                }
            }
        }

        showWinModal() {
            const timeMs = Math.floor(this.game.winTime);
            const formattedTime = this.formatTime(timeMs);

            document.querySelector('[data-modal-time]').textContent = formattedTime;
            document.querySelector('[data-modal-moves]').textContent = this.game.moveCount;

            this.modalEl.classList.add('game2048__modal--visible');
            this.modalEl.setAttribute('aria-hidden', 'false');

            this.announce(`축하합니다! ${formattedTime}, ${this.game.moveCount}번의 이동으로 2048을 달성했습니다.`);
        }

        closeModal() {
            this.modalEl.classList.remove('game2048__modal--visible');
            this.modalEl.setAttribute('aria-hidden', 'true');
        }

        showGameOver() {
            if (this.gameOverEl) {
                this.gameOverEl.hidden = false;
                this.gameOverEl.classList.add('game2048__overlay--visible');
            }
            this.announce('게임 오버! 더 이상 움직일 수 없습니다.');
            alert('게임 오버! 더 이상 움직일 수 없습니다.');
        }

        async submitScore() {
            if (this.game.scoreSubmitted) {
                return;
            }

            const timeMs = Math.floor(this.game.winTime);

            try {
                const response = await fetch('/fun/game2048/api/game.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        action: 'submit',
                        sessionToken: this.game.sessionToken,
                        completionTimeMs: timeMs,
                        moveCount: this.game.moveCount,
                        finalBoard: this.game.board,
                    }),
                });

                const data = await response.json();

                if (data.ok) {
                    this.game.scoreSubmitted = true;
                    this.closeModal();

                    let message = '기록이 제출되었습니다!';
                    if (data.rank) {
                        message += ` 순위: ${data.rank}위`;
                    }
                    if (data.isPersonalBest) {
                        message += ' (개인 최고 기록!)';
                    }

                    this.announce(message);
                    alert(message);

                    // Reload leaderboard and history
                    this.loadLeaderboard();
                    if (this.isLoggedIn) {
                        this.loadHistory();
                    }
                } else {
                    alert('기록 제출 실패: ' + (data.message || data.error));
                }
            } catch (err) {
                console.error('Score submission error:', err);
                alert('기록 제출 중 오류가 발생했습니다.');
            }
        }

        async loadLeaderboard() {
            const container = document.querySelector('[data-leaderboard]');

            try {
                const response = await fetch('/fun/game2048/api/game.php?action=leaderboard&limit=10');
                const data = await response.json();

                if (data.ok && data.scores.length > 0) {
                    container.innerHTML = this.renderScoreTable(data.scores, true);
                } else {
                    container.innerHTML = '<div class="game2048__empty">아직 기록이 없습니다.</div>';
                }
            } catch (err) {
                console.error('Leaderboard load error:', err);
                container.innerHTML = '<div class="game2048__error">순위를 불러올 수 없습니다.</div>';
            }
        }

        async loadHistory() {
            const container = document.querySelector('[data-history]');
            if (!container) return;

            try {
                const response = await fetch('/fun/game2048/api/game.php?action=history&limit=5');
                const data = await response.json();

                if (data.ok && data.scores.length > 0) {
                    container.innerHTML = this.renderScoreTable(data.scores, false);
                } else {
                    container.innerHTML = '<div class="game2048__empty">플레이 기록이 없습니다.</div>';
                }
            } catch (err) {
                console.error('History load error:', err);
                container.innerHTML = '<div class="game2048__error">기록을 불러올 수 없습니다.</div>';
            }
        }

        renderScoreTable(scores, showRank) {
            let html = '<table class="game2048__table"><thead><tr>';

            if (showRank) {
                html += '<th>순위</th><th>닉네임</th>';
            }

            html += '<th>시간</th><th>이동</th><th>날짜</th></tr></thead><tbody>';

            scores.forEach(score => {
                html += '<tr>';

                if (showRank) {
                    html += `<td>${score.rank}</td><td>${this.escapeHtml(score.nickname)}</td>`;
                }

                const date = new Date(score.createdAt);
                const dateStr = `${date.getMonth() + 1}/${date.getDate()}`;

                html += `<td>${score.time}</td><td>${score.moves}</td><td>${dateStr}</td>`;
                html += '</tr>';
            });

            html += '</tbody></table>';
            return html;
        }

        formatTime(totalMs) {
            const min = Math.floor(totalMs / 60000);
            const sec = Math.floor((totalMs % 60000) / 1000);
            const ms = Math.floor((totalMs % 1000) / 10);

            return `${min}:${sec.toString().padStart(2, '0')}.${ms.toString().padStart(2, '0')}`;
        }

        newGame() {
            if (confirm('새 게임을 시작하시겠습니까? 현재 진행 중인 게임은 저장되지 않습니다.')) {
                this.game = new Game2048();
                this.timer.reset();
                if (this.gameOverEl) {
                    this.gameOverEl.classList.remove('game2048__overlay--visible');
                    this.gameOverEl.hidden = true;
                }
                this.render();
                this.announce('새 게임이 시작되었습니다.');
            }
        }

        announce(message) {
            if (this.srAnnounce) {
                this.srAnnounce.textContent = message;
            }
        }

        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        animateBoard(direction) {
            if (!direction) return;

            const className = `game2048__board--slide-${direction}`;
            this.boardEl.classList.remove(...this.boardSlideClasses);

            // Restart animation
            this.boardEl.getBoundingClientRect();

            this.boardEl.classList.add(className);
            this.boardEl.addEventListener('animationend', () => {
                this.boardEl.classList.remove(className);
            }, { once: true });
        }
    }

    // ======================
    // Initialize on DOM ready
    // ======================
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            new GameUI();
        });
    } else {
        new GameUI();
    }
})();
