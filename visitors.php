<?php
declare(strict_types=1);

/**
 * Visitor registration logic (Step 3).
 *
 * No HTML and no session code lives here: this file validates a name, creates a
 * visitor code, hashes it and stores the visitor. register.php (the page) uses it.
 *
 * The visitor code
 *   - is 20 random bytes (160 bits) from random_bytes(), the PHP cryptographically
 *     secure generator;
 *   - is written with the 32 letters and digits of Base32 (A-Z and 2-7: no 0, 1, 8 or 9)
 *     in 8 groups of 4 separated by hyphens, for example  K7QD-2MXA-...  (39 characters),
 *     so a visitor can read it from a screen and type it again later.
 *
 * What the database stores
 *   Only the SHA-256 hash of the code (64 hexadecimal characters) is stored in
 *   visitors.visitor_code_hash. The code itself is never stored in the database.
 *   The hash is computed over the code WITHOUT hyphens and spaces, in capital letters
 *   (visitor_code_normalize), so a visitor may later type it in lower case, with or
 *   without the hyphens, and still produce the same hash.
 */

// Direct web requests to this file do nothing: it must be loaded by a page.
if (!defined('APP_ROOT')) {
    http_response_code(403);
    exit;
}

/** visitors.name is VARCHAR(100): 100 characters (not bytes). */
const VISITOR_NAME_MAX_LENGTH = 100;

/** Fewest letters a name must contain. */
const VISITOR_NAME_MIN_LETTERS = 2;

/** Random bytes in a visitor code: 20 bytes = 160 bits = exactly 32 Base32 characters. */
const VISITOR_CODE_BYTES = 20;

/** How many different codes are tried if the database reports that a hash already exists. */
const VISITOR_CODE_MAX_ATTEMPTS = 5;

/**
 * Checks a visitor name typed by the visitor.
 *
 * Rules: text; valid UTF-8; extra spaces are removed; 1 to 100 characters; starts with a letter;
 * only letters (any alphabet), combining marks, spaces, hyphens, apostrophes and full stops;
 * at least 2 letters. Digits, symbols and control characters are refused, which also refuses
 * anything that looks like HTML.
 *
 * @return array{name: string, errors: list<string>} the cleaned name and the problems found
 *         (the name is only usable when "errors" is empty)
 */
function visitor_name_validate(mixed $input): array
{
    if (!is_string($input)) {
        return ['name' => '', 'errors' => ['Please enter your name.']];
    }
    // Refuse absurdly long input before running any pattern on it.
    if (strlen($input) > 1000) {
        return ['name' => '', 'errors' => ['Your name must be ' . VISITOR_NAME_MAX_LENGTH . ' characters or fewer.']];
    }
    if (!mb_check_encoding($input, 'UTF-8')) {
        return ['name' => '', 'errors' => ['Your name contains characters that cannot be read. Please type it again.']];
    }

    // Turn every run of whitespace into one space, then trim.
    $collapsed = preg_replace('/[\s\p{Z}]+/u', ' ', $input);
    $name = $collapsed === null ? '' : trim($collapsed);

    if ($name === '') {
        return ['name' => '', 'errors' => ['Please enter your name.']];
    }
    if (mb_strlen($name, 'UTF-8') > VISITOR_NAME_MAX_LENGTH) {
        return ['name' => $name, 'errors' => ['Your name must be ' . VISITOR_NAME_MAX_LENGTH . ' characters or fewer.']];
    }
    if (preg_match("/^[\\p{L}][\\p{L}\\p{M} .'\u{2019}-]*$/u", $name) !== 1) {
        return ['name' => $name, 'errors' => ['Your name must start with a letter and may contain only letters, spaces, hyphens, apostrophes and full stops.']];
    }
    if ((int) preg_match_all('/\p{L}/u', $name) < VISITOR_NAME_MIN_LETTERS) {
        return ['name' => $name, 'errors' => ['Your name must contain at least ' . VISITOR_NAME_MIN_LETTERS . ' letters.']];
    }

    return ['name' => $name, 'errors' => []];
}

/** Base32 (RFC 4648 alphabet, no padding) of a byte string. */
function visitor_base32_encode(string $bytes): string
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $output = '';
    $buffer = 0;
    $bitsInBuffer = 0;

    foreach (str_split($bytes) as $character) {
        $buffer = ($buffer << 8) | ord($character);
        $bitsInBuffer += 8;
        while ($bitsInBuffer >= 5) {
            $output .= $alphabet[($buffer >> ($bitsInBuffer - 5)) & 31];
            $bitsInBuffer -= 5;
        }
        $buffer &= (1 << $bitsInBuffer) - 1; // keep only the bits not used yet
    }
    if ($bitsInBuffer > 0) {
        $output .= $alphabet[($buffer << (5 - $bitsInBuffer)) & 31];
    }
    return $output;
}

/**
 * Creates a new random visitor code, for example "K7QD-2MXA-...", in groups of 4.
 * random_bytes() throws an Exception if the system has no secure random source;
 * the code is then NOT created (there is no weaker fallback).
 */
function visitor_code_generate(): string
{
    return implode('-', str_split(visitor_base32_encode(random_bytes(VISITOR_CODE_BYTES)), 4));
}

/** The form of a code that is hashed: capital letters, without hyphens and white space. */
function visitor_code_normalize(string $code): string
{
    return (string) preg_replace('/[\s-]+/', '', strtoupper($code));
}

/**
 * SHA-256 hash of a visitor code, as 64 lower-case hexadecimal characters
 * (the size of the column visitors.visitor_code_hash, CHAR(64)).
 */
function visitor_code_hash(string $code): string
{
    return hash('sha256', visitor_code_normalize($code));
}

/** Did this exception come from a duplicate value in a UNIQUE key (MySQL/MariaDB error 1062)? */
function visitor_is_duplicate_key_error(PDOException $e): bool
{
    return (int) ($e->errorInfo[1] ?? 0) === 1062;
}

/**
 * Stores a new visitor and returns the visitor code, which exists nowhere else afterwards.
 *
 * The collision check is done by the database itself: visitors.visitor_code_hash has a UNIQUE key,
 * so inserting a hash that already exists fails with error 1062 and nothing is stored. The function
 * then creates a NEW code and tries again, up to VISITOR_CODE_MAX_ATTEMPTS times. This is safe even
 * when two visitors register at the same moment, which "look it up first, then insert" would not be.
 *
 * $name must already have passed visitor_name_validate(). Any other database error is passed on.
 * $codeGenerator exists so that the retry can be tested; normal code passes nothing.
 *
 * @param null|callable(): string $codeGenerator
 * @return array{id: int, code: string}
 * @throws PDOException     on a database error other than a duplicate hash
 * @throws RuntimeException when no unique code could be stored
 */
function visitor_register(PDO $pdo, string $name, ?callable $codeGenerator = null): array
{
    $generate = $codeGenerator ?? 'visitor_code_generate';
    $insert = $pdo->prepare('INSERT INTO visitors (name, visitor_code_hash) VALUES (?, ?)');

    for ($attempt = 1; $attempt <= VISITOR_CODE_MAX_ATTEMPTS; $attempt++) {
        $code = $generate();
        try {
            $insert->execute([$name, visitor_code_hash($code)]);
        } catch (PDOException $e) {
            if (visitor_is_duplicate_key_error($e)) {
                continue; // this hash exists already: nothing was stored, try a new code
            }
            throw $e;
        }
        return ['id' => (int) $pdo->lastInsertId(), 'code' => $code];
    }

    throw new RuntimeException('No unique visitor code could be stored after ' . VISITOR_CODE_MAX_ATTEMPTS . ' attempts.');
}
