<?php
/**
 * includes/db_session_handler.php
 *
 * Why this exists: Vercel serverless functions are stateless — each request
 * may run in a fresh, throwaway container with its own /tmp. PHP's default
 * file-based session storage relies on the SAME server disk being available
 * on the next request, which Vercel does not guarantee. Without this, users
 * would appear logged out (or get logged into someone else's session) on
 * almost every click.
 *
 * This stores session data in the MySQL database instead, so it survives
 * across serverless invocations. Works identically on XAMPP too — the
 * sessions table is created automatically the first time it's needed.
 */

class DbSessionHandler implements SessionHandlerInterface
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS sessions (
                id VARCHAR(128) PRIMARY KEY,
                data MEDIUMTEXT NOT NULL,
                last_activity INT UNSIGNED NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
    }

    public function open($savePath, $sessionName): bool
    {
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function read($id): string
    {
        $stmt = $this->pdo->prepare("SELECT data FROM sessions WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ? $row['data'] : '';
    }

    public function write($id, $data): bool
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO sessions (id, data, last_activity) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE data = VALUES(data), last_activity = VALUES(last_activity)"
        );
        return $stmt->execute([$id, $data, time()]);
    }

    public function destroy($id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM sessions WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function gc($max_lifetime): int|false
    {
        $stmt = $this->pdo->prepare("DELETE FROM sessions WHERE last_activity < ?");
        $stmt->execute([time() - $max_lifetime]);
        return $stmt->rowCount();
    }
}
