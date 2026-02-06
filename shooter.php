<?php
// shooter.php
declare(ticks=1);

// -----------------------------
// Basisklassen (Teil 1)
// -----------------------------
class Entity {
    public int $positionX;
    public int $positionY;

    public function __construct(int $x, int $y) {
        $this->positionX = $x;
        $this->positionY = $y;
    }
}

class Spaceship extends Entity {
    public int $movementX = 0;
    public int $movementY = 0;
    public bool $fire = false;

    public function __construct(int $x, int $y) {
        parent::__construct($x, $y);
    }
}

class Enemy extends Entity {
    public function __construct(int $x, int $y) {
        parent::__construct($x, $y);
    }
}

class Bullet extends Entity {
    public function __construct(int $x, int $y) {
        parent::__construct($x, $y);
    }
}

// -----------------------------
// Scene & Spiellogik (Teil 2+3+4)
// -----------------------------
class Scene {
    public int $width;
    public int $height;
    public Spaceship $ship;
    /** @var Enemy[] */
    public array $enemies = [];
    /** @var Bullet[] */
    public array $bullets = [];
    public int $score = 0;
    public bool $running = true;

    public function __construct(int $width, int $height) {
        $this->width = $width;
        $this->height = $height;
        $shipX = (int)floor($height / 2);
        $shipY = 0; // left edge
        $this->ship = new Spaceship($shipX, $shipY);
        $this->spawnEnemies();
    }

    public function renderGame(): void {
        // Build grid
        $grid = array_fill(0, $this->height, array_fill(0, $this->width, ' '));

        foreach ($this->enemies as $e) {
            if ($e->positionX >= 0 && $e->positionX < $this->height &&
                $e->positionY >= 0 && $e->positionY < $this->width) {
                $grid[$e->positionX][$e->positionY] = 'X';
            }
        }

        foreach ($this->bullets as $b) {
            if ($b->positionX >= 0 && $b->positionX < $this->height &&
                $b->positionY >= 0 && $b->positionY < $this->width) {
                if ($grid[$b->positionX][$b->positionY] === ' ') {
                    $grid[$b->positionX][$b->positionY] = '-';
                }
            }
        }

        if ($this->ship->positionX >= 0 && $this->ship->positionX < $this->height &&
            $this->ship->positionY >= 0 && $this->ship->positionY < $this->width) {
            $grid[$this->ship->positionX][$this->ship->positionY] = '>';
        }

        echo "\033[H\033[2J";
        for ($r = 0; $r < $this->height; $r++) {
            echo '|';
            for ($c = 0; $c < $this->width; $c++) {
                echo $grid[$r][$c];
            }
            echo "|\n";
        }
        echo str_repeat('-', $this->width+2) . PHP_EOL;
        echo "Score: {$this->score}    Enemies: " . count($this->enemies) . "    Bullets: " . count($this->bullets) . PHP_EOL;
        echo "Controls: WASD = move, Space/Enter = shoot, Esc = quit\n";
    }

    public function moveEnemies(): void {
        foreach ($this->enemies as $idx => $enemy) {
            $enemy->positionY -= 1;
        }
        $newEnemies = [];
        $bulletsToKeep = $this->bullets; // copy

        foreach ($this->enemies as $eIdx => $enemy) {
            $hit = false;
            foreach ($bulletsToKeep as $bIdx => $bullet) {
                if ($enemy->positionX === $bullet->positionX && $enemy->positionY === $bullet->positionY) {
                    // collision
                    $directHit = (
                        $enemy->positionX === $bullet->positionX &&
                        $enemy->positionY === $bullet->positionY
                    );
                    $swapHit = (
                        $enemy->positionX === $bullet->positionX &&
                        $enemy->positionY - 1 === $bullet->positionY &&
                        $bullet->positionY + 1 === $enemy->positionY
                    );

                    if ($directHit || $swapHit) {
                        unset($bulletsToKeep[$bIdx]);
                        $this->score += 1;
                        $hit = true;
                        break;
                    }
                }
            }
            if (!$hit) {
                if ($enemy->positionY >= 0) {
                    $newEnemies[] = $enemy;
                }
            }
        }

        $this->bullets = array_values($bulletsToKeep);
        $this->enemies = array_values($newEnemies);
    }

    public function moveBullets(): void {
        foreach ($this->bullets as $bIdx => $bullet) {
            $bullet->positionY += 1;
            // remove if off screen
            if ($bullet->positionY >= $this->width) {
                unset($this->bullets[$bIdx]);
            }
        }
        $this->bullets = array_values($this->bullets);
    }

    public function shoot(): void {
        if ($this->ship->fire) {
            $bx = $this->ship->positionX;
            $by = $this->ship->positionY + 1;
            $this->bullets[] = new Bullet($bx, $by);
            $this->ship->fire = false;
        }
    }

    public function spawnEnemies(): void {
        $minEnemies = 15;
        $maxSpawnY = $this->width + 20;
        while (count($this->enemies) < $minEnemies) {
            $x = random_int(0, max(0, $this->height - 1));
            $y = random_int($this->width, $maxSpawnY);
            $this->enemies[] = new Enemy($x, $y);
        }
    }

    public function gameOver(): void {
        foreach ($this->enemies as $enemy) {
            if ($enemy->positionX === $this->ship->positionX && $enemy->positionY === $this->ship->positionY) {
                $this->renderGame();
                echo PHP_EOL . "=== GAME OVER ===" . PHP_EOL;
                echo "Final Score: {$this->score}" . PHP_EOL;
                $this->running = false;
                return;
            }
        }
    }

    public function moveShip(): void {
        $this->ship->positionX += $this->ship->movementX;
        $this->ship->positionY += $this->ship->movementY;

        if ($this->ship->positionX < 0) $this->ship->positionX = 0;
        if ($this->ship->positionX > $this->height - 1) $this->ship->positionX = $this->height - 1;

        $maxQuarter = max(0, (int)floor($this->width / 4));
        if ($this->ship->positionY < 0) $this->ship->positionY = 0;
        if ($this->ship->positionY > $maxQuarter) $this->ship->positionY = $maxQuarter;

        // reset movement
        $this->ship->movementX = 0;
        $this->ship->movementY = 0;
    }
    public function processMovesAndCollisions(): void {
        $bulletCount = count($this->bullets);
        $enemyCount = count($this->enemies);

        // если ничего нет — быстро выйти
        if ($bulletCount === 0 && $enemyCount === 0) {
            return;
        }

        $bulletsNext = [];
        foreach ($this->bullets as $i => $b) {
            $bulletsNext[$i] = ['nx' => $b->positionX, 'ny' => $b->positionY + 1, 'cx' => $b->positionX, 'cy' => $b->positionY];
        }

        $enemiesNext = [];
        foreach ($this->enemies as $j => $e) {
            $enemiesNext[$j] = ['nx' => $e->positionX, 'ny' => $e->positionY - 1, 'cx' => $e->positionX, 'cy' => $e->positionY];
        }

        // флаги удаления
        $removeBullet = array_fill(0, $bulletCount, false);
        $removeEnemy = array_fill(0, $enemyCount, false);

        // проверим столкновения (вложенный цикл — ок при небольших числах)
        for ($i = 0; $i < $bulletCount; $i++) {
            if ($removeBullet[$i]) continue; // уже помечена
            for ($j = 0; $j < $enemyCount; $j++) {
                if ($removeEnemy[$j]) continue; // этот враг уже сбит

                $bC = $bulletsNext[$i]['cx']; $bCy = $bulletsNext[$i]['cy'];
                $bN = $bulletsNext[$i]['nx']; $bNy = $bulletsNext[$i]['ny'];

                $eC = $enemiesNext[$j]['cx']; $eCy = $enemiesNext[$j]['cy'];
                $eN = $enemiesNext[$j]['nx']; $eNy = $enemiesNext[$j]['ny'];

                // 1) прямое попадание: пуля после движения окажется на той же клетке, что и враг после движения
                $directAfterMove = ($bN === $eN && $bNy === $eNy && $bN !== null);

                // 2) пуля попадает в врага, который стоит на месте (или враг не двигается в видимую область)
                $bulletHitsEnemyCurrent = ($bN === $eC && $bNy === $eCy);

                // 3) встречное столкновение (swap): они поменялись местами
                $swap = ($bN === $eC && $eN === $bC && $bNy === $eCy && $eNy === $bCy);

                // 4) оба заходят в одну и ту же клетку (например, враг и пуля идут в одну клетку с разных сторон)
                $bothIntoSameCell = ($bN === $eN && $bNy === $eNy);

                if ($directAfterMove || $bulletHitsEnemyCurrent || $swap || $bothIntoSameCell) {
                    // помечаем на удаление — пуля и враг
                    $removeBullet[$i] = true;
                    $removeEnemy[$j] = true;
                    $this->score += 1;
                    // как только пуля попала, выходим к следующей пуле
                    break;
                }
            }
        }

        // Теперь применим движения и уберём помеченные элементы
        // Обновляем пули: только те, которые не помечены и не ушли за правый край
        $newBullets = [];
        foreach ($this->bullets as $i => $b) {
            if (!isset($removeBullet[$i]) || $removeBullet[$i]) {
                continue;
            }
            $nextY = $b->positionY + 1;
            // удаляем если вышла за экран
            if ($nextY >= $this->width) {
                continue;
            }
            // применяем перемещение
            $b->positionY = $nextY;
            $newBullets[] = $b;
        }
        $this->bullets = array_values($newBullets);

        // Обновляем врагов: те, кто не помечен и не ушёл за левый край
        $newEnemies = [];
        foreach ($this->enemies as $j => $e) {
            if (!isset($removeEnemy[$j]) || $removeEnemy[$j]) {
                continue;
            }
            $nextY = $e->positionY - 1;
            if ($nextY < 0) {
                // ушёл за левый край — просто удаляем (не считается попаданием)
                continue;
            }
            $e->positionY = $nextY;
            $newEnemies[] = $e;
        }
        $this->enemies = array_values($newEnemies);
    }

    // read stdin bytes and set actions accordingly
    public function action($stdin): void {
        $c = fread($stdin, 1);
        if ($c === false || $c === "") return;

        switch ($c) {
            case "w":
            case "W":
                $this->ship->movementX = -1;
                break;

            case "s":
            case "S":
                $this->ship->movementX = 1;
                break;

            case "a":
            case "A":
                $this->ship->movementY = -1;
                break;

            case "d":
            case "D":
                $this->ship->movementY = 1;
                break;

            case " ":
                $this->ship->fire = true;
                break;

            case "q":
            case "Q":
                $this->running = false;
                break;

            default:
                // ignore everything else
                break;
        }
    }
}


// -----------------------------
// Terminal helpers & main loop
// -----------------------------
function enableRawMode(): void {
    // on Windows, `stty` isn't available — enable VT100 (ANSI) if possible and skip stty calls
    if (PHP_OS_FAMILY === 'Windows') {
        if (function_exists('sapi_windows_vt100_support')) {
            @sapi_windows_vt100_support(STDOUT, true);
        }
        return;
    }

    // Save current stty settings
    $stty = @shell_exec('stty -g');
    if ($stty !== null) {
        @file_put_contents(sys_get_temp_dir() . '/php_shooter_stty_backup', $stty);
    }
    // Set raw: disable echo and canonical mode, min 0 time 0 for non-blocking reads
    @shell_exec('stty -echo -icanon min 0 time 0');
}

function disableRawMode(): void {
    // on Windows nothing to restore via stty
    if (PHP_OS_FAMILY === 'Windows') {
        return;
    }

    $backup = @file_get_contents(sys_get_temp_dir() . '/php_shooter_stty_backup');
    if ($backup !== false && $backup !== '') {
        @shell_exec('stty ' . escapeshellarg(trim($backup)));
    } else {
        // fallback
        @shell_exec('stty echo icanon');
    }
}


function restoreOnExit() {
    disableRawMode();
    // move cursor to bottom
    echo "\n";
}

// ensure terminal settings restored at exit
register_shutdown_function('restoreOnExit');

// trap signals (Ctrl+C)
if (function_exists('pcntl_signal')) {
    pcntl_signal(SIGINT, function() {
        exit(0);
    });
}

// Start script
$width = 50;
$height = 10;
$scene = new Scene($width, $height);

// configure terminal raw mode and non-blocking stdin
enableRawMode();
$stdin = fopen('php://stdin', 'r');
stream_set_blocking($stdin, false);

// initial render
$scene->renderGame();

$frame = 0;
$framesPerEnemyMove = 2; // enemy moves every n frames (to slow them)
$framesPerBulletMove = 1;
$framesPerSpawnCheck = 10;

while ($scene->running) {
    // read actions
    $scene->action($stdin);

    // move ship
    $scene->moveShip();

    // shoot (if requested)
    $scene->shoot();

    // move bullets occasionally
    if ($frame % $framesPerBulletMove === 0) {
        $scene->moveBullets();
    }

    // move enemies occasionally
    if ($frame % $framesPerEnemyMove === 0) {
        $scene->moveEnemies();
    }

    // spawn to maintain minimum
    if ($frame % $framesPerSpawnCheck === 0) {
        $scene->spawnEnemies();
    }

    // render
    $scene->renderGame();

    // check game over
    $scene->gameOver();
    if (!$scene->running) break;

    // pause
    usleep(80_000); // 80ms per frame ~12.5 FPS

    $frame++;
}

// cleanup
fclose($stdin);
disableRawMode();
echo "Thank you for playing!\n";
