<?php

namespace Iquesters\Foundation\Http\Controllers;

use Illuminate\Routing\Controller;
use Iquesters\Foundation\Models\Entity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Exception;

class EntityController extends Controller
{
    public function index()
    {
        try {
            Log::info('Fetching all entity');

            $entities = Entity::with('metas')
                ->get();
            Log::info('Displaying entities', ['entities' => $entities->toArray()]);

            return view('foundation::entity.index', [
                'entities' => $entities
            ]);
        } catch (Exception $e) {
            Log::error('Error fetching entity', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return redirect()
                ->back()
                ->with('error', 'An error occurred while fetching entity.');
        }
    }
}