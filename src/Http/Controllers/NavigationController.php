<?php

namespace Iquesters\Foundation\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Iquesters\Foundation\Services\NavigationLayoutService;

class NavigationController extends Controller
{
    public function __construct(
        protected NavigationLayoutService $navigationLayoutService
    ) {
    }

    public function index()
    {
        try {
            $layouts = $this->navigationLayoutService->getNavigationRows();
            $editorPayload = $this->navigationLayoutService->getNavigationEditorPayload();
            $renderedPayload = $this->navigationLayoutService->getRenderedNavigationPayload();

            return view('foundation::navigation.details', [
                'moduleNavigation' => $layouts['module_navigation'],
                'foundationNavigation' => $layouts['foundation_navigation'],
                'editorPayload' => $editorPayload,
                'renderedPayload' => $renderedPayload,
            ]);
        } catch (\Throwable $e) {
            Log::error('Navigation index error', ['error' => $e->getMessage()]);
            abort(500, 'Unable to load navigation');
        }
    }

    public function details()
    {
        return $this->index();
    }

    public function loadModuleSubMenu(string $moduleUid)
    {
        $layout = $this->navigationLayoutService->getModuleNavigationByUid($moduleUid);

        if (!$layout) {
            return response()->json([
                'success' => false,
                'message' => 'Navigation not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'navigation' => $layout,
        ]);
    }

    public function saveOrder(Request $request)
    {
        try {
            $payload = $request->validate([
                'module_navigation' => ['nullable', 'string'],
                'foundation_navigation' => ['nullable', 'string'],
            ]);

            $moduleNavigation = $this->decodeNavigationPayload($payload['module_navigation'] ?? null);
            $foundationNavigation = $this->decodeNavigationPayload($payload['foundation_navigation'] ?? null);

            $this->navigationLayoutService->saveNavigationRows(
                $moduleNavigation,
                $foundationNavigation
            );

            return back()->with('success', 'Navigation updated successfully');
        } catch (\Throwable $e) {
            Log::error('Save navigation failed', [
                'error' => $e->getMessage(),
                'user' => auth()->id(),
            ]);

            return back()->with('error', 'Failed to save navigation');
        }
    }

    protected function decodeNavigationPayload(?string $payload): ?array
    {
        if (!$payload) {
            return null;
        }

        $decoded = json_decode($payload, true);

        return is_array($decoded) ? $decoded : null;
    }
}
