<?php

namespace Iquesters\Foundation\Http\Controllers;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Iquesters\Foundation\Constants\EntityListParams;
use Iquesters\Foundation\Constants\HttpStatusCode;
use Iquesters\Foundation\Http\Query\ParsesListQuery;
use Iquesters\Foundation\Services\Entity\EntityErrorMapper;
use Iquesters\Foundation\Services\Entity\EntityPresenter;
use Iquesters\Foundation\Services\Entity\EntityRepository;
use Iquesters\Foundation\Services\Entity\EntitySchemaResolver;
use Iquesters\Foundation\Services\Entity\EntityScope;
use Iquesters\Foundation\Services\Entity\EntityValidator;
use Iquesters\Foundation\Services\Entity\EntityValueNormalizer;
use Iquesters\Foundation\Support\EntityPermission;
use Iquesters\Foundation\System\Http\ApiResponse;
use Iquesters\Foundation\System\Traits\Loggable;
use Throwable;

/**
 * Generic CRUD API over registered entity tables.
 *
 * Each action: permission check (entity.{table}.{action}) -> allowlist
 * (registered entity) -> validate -> normalize -> repository -> presenter.
 */
class DynamicEntityController extends Controller
{
    use Loggable;
    use ParsesListQuery;

    /** Request keys that are never treated as column values. */
    private const RESERVED_PAYLOAD_KEYS = ['_token', '_method', 'meta'];

    public function __construct(
        private readonly EntitySchemaResolver $schema,
        private readonly EntityRepository $repository,
        private readonly EntityValidator $validator,
        private readonly EntityValueNormalizer $normalizer,
        private readonly EntityScope $scope,
        private readonly EntityPresenter $presenter,
        private readonly EntityErrorMapper $errors,
    ) {}

    public function list(Request $request, string $entity)
    {
        try {
            $this->authorizeEntity($entity, 'view');
            $this->schema->ensureEntityTableExists($entity);

            $offset = max((int) $request->query('offset', 0), 0);
            $length = min(max((int) $request->query('length', 50), 1), EntityListParams::MAX_LENGTH);

            [$total, $records] = $this->repository->paginate(
                $entity,
                $this->parseListFilters($request->query()),
                $this->parseListSorts((string) $request->query('sort', '')),
                $offset,
                $length,
                is_string($request->query(EntityListParams::PARAM_SEARCH)) ? $request->query(EntityListParams::PARAM_SEARCH) : null
            );

            return ApiResponse::paginated(
                $this->presenter->many($records),
                $total,
                $length,
                (int) floor($offset / $length) + 1,
                'Request successful'
            );
        } catch (Throwable $e) {
            return $this->errorResponse($e, $entity);
        }
    }

    public function show(string $entity, string $data_uid)
    {
        try {
            $this->authorizeEntity($entity, 'view');
            $this->schema->ensureEntityTableExists($entity);

            $record = $this->repository->find($entity, $data_uid);

            if (! $record) {
                return ApiResponse::error('Record not found', HttpStatusCode::HTTP_NOT_FOUND, [
                    'entity' => $entity,
                    'data_uid' => $data_uid,
                ]);
            }

            return ApiResponse::success($this->presenter->one($record), 'Request successful');
        } catch (Throwable $e) {
            return $this->errorResponse($e, $entity, $data_uid);
        }
    }

    public function store(Request $request, string $entity)
    {
        try {
            $this->authorizeEntity($entity, 'create');
            [$mainData, $metaData] = $this->validatedData($request, $entity, false);

            $record = $this->repository->create($entity, $mainData, $metaData);
            $this->logDebug("Dynamic entity record created | entity={$entity} | record_id=".($record->id ?? 'null'));

            return ApiResponse::created($this->presenter->one($record), 'Record created successfully');
        } catch (Throwable $e) {
            return $this->errorResponse($e, $entity);
        }
    }

    public function update(Request $request, string $entity, string $data_uid)
    {
        try {
            $this->authorizeEntity($entity, 'update');
            [$mainData, $metaData] = $this->validatedData($request, $entity, true, $data_uid);

            $record = $this->repository->update($entity, $data_uid, $mainData, $metaData);
            $this->logDebug("Dynamic entity record updated | entity={$entity} | record_id=".($record->id ?? 'null'));

            return ApiResponse::success($this->presenter->one($record), 'Record updated successfully');
        } catch (Throwable $e) {
            return $this->errorResponse($e, $entity, $data_uid);
        }
    }

    public function delete(string $entity, string $data_uid)
    {
        try {
            $this->authorizeEntity($entity, 'delete');
            $this->schema->ensureEntityTableExists($entity);

            $record = $this->repository->softDelete($entity, $data_uid);
            $this->logDebug("Dynamic entity record soft deleted | entity={$entity} | record_id=".($record->id ?? 'null'));

            return ApiResponse::success($this->presenter->one($record), 'Record deleted successfully');
        } catch (Throwable $e) {
            return $this->errorResponse($e, $entity, $data_uid);
        }
    }

    /**
     * Per-entity permission check; admins always pass. Runs before the
     * allowlist so callers without permission get 403 whether or not the table
     * exists, which avoids revealing registered entity names.
     */
    private function authorizeEntity(string $entity, string $action): void
    {
        if (! EntityPermission::allows(auth()->user(), $entity, $action)) {
            throw new AuthorizationException("You do not have permission to {$action} {$entity}.");
        }
    }

    /**
     * Allowlist, validate against field definitions, normalize, and check scope.
     *
     * @return array{0: array, 1: array} [main data, meta data]
     */
    private function validatedData(Request $request, string $entity, bool $isUpdate, ?string $reference = null): array
    {
        $schema = $this->schema->writeSchema($entity);
        $payload = $request->except(self::RESERVED_PAYLOAD_KEYS);
        $explicitMeta = $request->input('meta', []);

        $this->validator->validate(
            $entity,
            $schema['main_field_definitions'],
            $schema['meta_field_definitions'],
            array_intersect_key($payload, array_flip($schema['writable_main_columns'])),
            $this->normalizer->flattenMeta($explicitMeta),
            $schema['writable_main_columns'],
            $isUpdate,
            $isUpdate && $reference !== null ? $this->repository->idFor($entity, $reference) : null
        );

        $mainData = $this->normalizer->mainData($payload, $schema['main_field_definitions'], $schema['writable_main_columns']);
        $metaData = $this->normalizer->metaData($schema['meta_field_definitions'], $explicitMeta);

        if (empty($mainData) && empty($metaData)) {
            throw new \InvalidArgumentException('Request payload is empty.');
        }

        $this->scope->assertWritePayloadInScope($mainData);

        return [$mainData, $metaData];
    }

    private function errorResponse(Throwable $e, ?string $entity = null, ?string $reference = null)
    {
        [$status, $message, $errors] = $this->errors->map($e, $entity, $reference);

        return ApiResponse::error($message, $status, $errors);
    }
}
