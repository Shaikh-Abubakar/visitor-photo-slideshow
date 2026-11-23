<?php
declare(strict_types=1);

/**
 * Database connection (Step 2).
 *
 * db_connection() returns one shared PDO connection. The connection is opened the
 * first time it is needed (a page that never touches the database never connects)
 * and every later call in the same request returns the same object.
 *
 * Nothing in this file contains credentials: they come from app_config().
 */

// Direct web requests to this file do nothing: it must be loaded by includes/bootstrap.php.
if (!defined('APP_ROOT')) {
    http_response_code(403);
    exit;
}

/** Seconds to wait for the database server before giving up. */
const DATABASE_CONNECT_TIMEOUT_SECONDS = 5;

/**
 * Run once right after connecting, so every machine behaves the same:
 *  - time_zone: DATETIME values are stored in UTC (see schema.sql);
 *  - collation_connection: matches the tables' utf8mb4_unicode_ci collation.
 */
const DATABASE_SESSION_SQL = "SET time_zone = '+00:00', collation_connection = 'utf8mb4_unicode_ci'";

/**
 * The connection failed. The message is written for logs and for the developer:
 * it never contains the password, the user name or the server's own error text.
 * reason() is one of: driver_missing, unreachable, access_denied, unknown_database, other.
 */
final class DatabaseConnectionException extends RuntimeException
{
    public function __construct(string $message, private readonly string $reason)
    {
        parent::__construct($message);
    }

    public function reason(): string
    {
        return $this->reason;
    }
}

/** Translates a MySQL error number into one of the reasons above. */
function database_failure_reason(int $mysqlErrorNumber): string
{
    return match (true) {
        in_array($mysqlErrorNumber, [1044, 1045, 1698], true) => 'access_denied',
        $mysqlErrorNumber === 1049 => 'unknown_database',
        in_array($mysqlErrorNumber, [2002, 2003, 2006, 2013], true) => 'unreachable',
        default => 'other',
    };
}

/**
 * The shared PDO connection.
 *
 * Settings used:
 *  - exceptions for every error (no silent failures);
 *  - real (native) prepared statements, not emulated ones;
 *  - rows returned as associative arrays;
 *  - no persistent connection;
 *  - utf8mb4 character set.
 *
 * @throws DatabaseConnectionException when the connection cannot be made
 * @throws AppConfigException          when a database setting is missing or invalid
 */
function db_connection(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    if (!extension_loaded('pdo_mysql')) {
        throw new DatabaseConnectionException('The PHP extension pdo_mysql is not installed or not enabled.', 'driver_missing');
    }

    // The values were checked by the configuration loader, so they are safe inside the connection string.
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
        app_config('DB_HOST'),
        app_config('DB_PORT'),
        app_config('DB_NAME')
    );

    try {
        $connection = new PDO($dsn, (string) app_config('DB_USER'), (string) app_config('DB_PASSWORD'), [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_PERSISTENT         => false,
            PDO::ATTR_TIMEOUT            => DATABASE_CONNECT_TIMEOUT_SECONDS,
        ]);
        $connection->exec(DATABASE_SESSION_SQL);
    } catch (PDOException $e) {
        // The original error goes to the server log only. It is NOT passed on (no "previous"
        // exception), because its stack trace could contain the connection arguments.
        error_log('Database connection failed (MySQL error ' . (int) $e->getCode() . '): ' . $e->getMessage());

        $reason = database_failure_reason((int) $e->getCode());
        $messages = [
            'access_denied'    => 'The database server rejected the user name or password, or the user may not use this database (MySQL gives this same answer when the database does not exist).',
            'unknown_database' => 'The database named in DB_NAME does not exist.',
            'unreachable'      => 'The database server could not be reached. Check that it is running and that DB_HOST and DB_PORT are correct.',
            'other'            => 'The database connection failed. See the server error log for details.',
        ];
        throw new DatabaseConnectionException($messages[$reason], $reason);
    }

    $pdo = $connection;
    return $pdo;
}
