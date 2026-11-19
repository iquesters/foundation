<?php

namespace Iquesters\Foundation\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Iquesters\Foundation\Constants\EntityListParams;
use Iquesters\Foundation\Constants\HttpStatusCode;
use Iquesters\Foundation\Http\Query\ParsesListQuery;
use Iquesters\Foundation\Services\BusinessEntity\BusinessEntityRecords;
use Iquesters\Foundation\Services\Entity\EntityErrorMapper;
use Iquesters\Foundation\System\Http\ApiResponse;
use Throwable;

/**
 * CRUD API over business entity records (one record across several entity
 * tables), at /api/foundation/v1/business-entity/{action}/{slug}[/{uid}].
 * The {uid} is the root record's uid. See BusinessEntityRecords.
 */
class BusinessEntityDataController extends Controller
{
    use ParsesListQuery;

    /** Request keys that are never field values. */
    private const RESERVED_PAYLOAD_KEYS = ['_token', '_method', 'meta'];

    public function __construct(
        private readonly BusinessEntityRecords $records,
        private readonly EntityErrorMapper $errors,
    ) {}

    public function list(Request $request, string $slug)
    {
        try {
            [, $definition] = $this->records->resolve($slug);
            $this->records->authorize($definition, 'view');

            $offset = max((int) $request->query('offset', 0), 0);
            $length = min(max((int) $request->query('length', 50), 1), EntityListParams::MAX_LENGTH);
            $search = $request->query(EntityListParams::PARAM_SEARCH);

            [$total, $records] = $this->records->paginate(
                $definition,
                $this->parseListFilters($request->query()),
                $this->parseListSorts((string) $request->query('sort', '')),
                $offset,
                $length,
                is_string($search) ? $search : null
            );

            return ApiResponse::paginated($records, $total, $length, (int) floor($offset / $length) + 1, 'Request successful');
        } catch (Throwable $e) {
            return $this->errorResponse($e, $slug);
        }
    }

    public function show(string $slug, string $uid)
    {
        try {
            [, $definition] = $this->records->resolve($slug);
            $this->records->authorize($definition, 'view');

            $record = $this->records->find($definition, $uid);

            if (! $record) {
                return ApiResponse::error('Record not found', HttpStatusCode::HTTP_NOT_FOUND, ['business_entity' => $slug, 'data_uid' => $uid]);
            }

            return ApiResponse::success($record, 'Request successful');
        } catch (Throwable $e) {
            return $this->errorResponse($e, $slug, $uid);
        }
    }

    public function store(Request $request, string $slug)
    {
        try {
            [, $definition] = $this->records->resolve($slug);
            $this->records->authorize($definition, 'create');

            $record = $this->records->create($definition, $this->payload($request), (array) $request->input('meta', []));

            return ApiResponse::created($record, 'Record created successfully');
        } catch (Throwable $e) {
            return $this->errorResponse($e, $slug);
        }
    }

    public function update(Request $request, string $slug, string $uid)
    {
        try {
            [, $definition] = $this->records->resolve($slug);
            $this->records->authorize($definition, 'update');

            $record = $this->records->update($definition, $uid, $this->payload($request), (array) $request->input('meta', []));

            return ApiResponse::success($record, 'Record updated successfully');
        } catch (Throwable $e) {
            return $this->errorResponse($e, $slug, $uid);
        }
    }

    public function delete(string $slug, string $uid)
    {
        try {
            [, $definition] = $this->records->resolve($slug);
            $this->records->authorize($definition, 'delete');

            return ApiResponse::success($this->records->delete($definition, $uid), 'Record deleted successfully');
        } catch (Throwable $e) {
            return $this->errorResponse($e, $slug, $uid);
        }
    }

    /**
     * Choices for a reference part ({value, label} list), used by the form's
     * select / checkbox fields ("optionsFrom": {"url": ...}). Needs
     * entity.{table}.view on the referenced table.
     */
    public function options(Request $request, string $slug, string $alias)
    {
        try {
            [, $definition] = $this->records->resolve($slug);
            $search = $request->query('search');

            return ApiResponse::success(
                $this->records->options($definition, $alias, is_string($search) ? $search : null, (int) $request->query('limit', 100)),
                'Request successful'
            );
        } catch (Throwable $e) {
            return $this->errorResponse($e, $slug);
        }
    }

    private function payload(Request $request): array
    {
        return $request->except(self::RESERVED_PAYLOAD_KEYS);
    }

    private function errorResponse(Throwable $e, string $slug, ?string $uid = null)
    {
        [$status, $message, $errors] = $this->errors->map($e, "business-entity/{$slug}", $uid);

        return ApiResponse::error($message, $status, $errors);
    }
}
