<?php

namespace Iquesters\Foundation\Services\Entity;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;
use Iquesters\Foundation\Constants\HttpStatusCode;
use Iquesters\Foundation\System\Traits\Loggable;
use Throwable;

/**
 * Maps exceptions from the generic entity API to [status, message, errors],
 * without leaking SQL details to clients.
 */
class EntityErrorMapper
{
    use Loggable;

    /** @return array{0: int, 1: string, 2: array|null} */
    public function map(Throwable $e, ?string $entity = null, ?string $reference = null): array
    {
        $mapped = match (true) {
            $e instanceof ValidationException => [HttpStatusCode::HTTP_UNPROCESSABLE_ENTITY, 'The given data was invalid.', $e->errors()],
            $e instanceof AuthorizationException => [HttpStatusCode::HTTP_FORBIDDEN, $e->getMessage(), null],
            $e instanceof \InvalidArgumentException => [HttpStatusCode::HTTP_BAD_REQUEST, $e->getMessage(), null],
            $e instanceof UniqueConstraintViolationException,
            $e instanceof QueryException => $this->mapQueryException($e),
            $e instanceof \RuntimeException => [HttpStatusCode::HTTP_NOT_FOUND, $e->getMessage(), null],
            default => null,
        };

        if ($mapped === null) {
            $this->logError('Entity Data API Error: '.$e->getMessage().' '.json_encode([
                'entity' => $entity ?? 'unknown',
                'data_uid' => $reference,
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]));

            $mapped = [HttpStatusCode::HTTP_INTERNAL_SERVER_ERROR, 'Something went wrong while processing entity data.', null];
        }

        $this->logDebug(sprintf(
            'Dynamic entity exception mapped | entity=%s | data_uid=%s | exception=%s | status=%s',
            $entity ?? 'null',
            $reference ?? 'null',
            get_class($e),
            (string) $mapped[0]
        ));

        return $mapped;
    }

    private function mapQueryException(QueryException $e): array
    {
        $errorInfo = $e->errorInfo ?? [];
        $sqlState = $errorInfo[0] ?? null;
        $driverCode = (int) ($errorInfo[1] ?? 0);
        $driverMessage = $errorInfo[2] ?? $e->getMessage();

        $this->logError(sprintf(
            'Entity Data DB Error | sql_state=%s | driver_code=%s | message=%s',
            $sqlState ?? 'null',
            (string) $driverCode,
            $driverMessage
        ));

        if ($sqlState === '23000' && $driverCode === 1062) {
            $field = $this->fieldFromDuplicateKey($driverMessage);

            return [
                HttpStatusCode::HTTP_CONFLICT,
                $field ? ucfirst($field).' already exists.' : 'Duplicate value already exists.',
                $field ? [$field => ["The {$field} has already been taken."]] : null,
            ];
        }

        // Relational integrity failures are client errors; don't leak SQL.
        if ($sqlState === '23000' && in_array($driverCode, [1451, 1452], true)) {
            return [HttpStatusCode::HTTP_CONFLICT, 'Related record does not exist or cannot be modified because it is in use.', null];
        }

        if ($sqlState === '23000' && $driverCode === 1048) {
            $field = preg_match("/Column '([^']+)' cannot be null/i", $driverMessage, $m) === 1 ? $m[1] : null;

            return [
                HttpStatusCode::HTTP_UNPROCESSABLE_ENTITY,
                $field ? ucfirst($field).' is required.' : 'A required field is missing.',
                $field ? [$field => ["The {$field} field is required."]] : null,
            ];
        }

        return [HttpStatusCode::HTTP_INTERNAL_SERVER_ERROR, 'Unable to save the record due to a database constraint.', null];
    }

    private function fieldFromDuplicateKey(string $message): ?string
    {
        if (preg_match("/for key '([^']+)'/i", $message, $matches) !== 1) {
            return null;
        }

        $index = $matches[1];
        if (str_ends_with($index, '_unique')) {
            $index = substr($index, 0, -7);
        }

        $parts = explode('_', $index);

        return count($parts) > 1 ? end($parts) : $index;
    }
}
