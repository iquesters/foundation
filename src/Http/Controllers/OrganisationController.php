<?php


namespace Iquesters\Foundation\Http\Controllers;

use Illuminate\Routing\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Iquesters\Foundation\Models\Organisation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class OrganisationController extends Controller
{

    public function index()
    {
        try {
            Log::info('Fetching all organisations');
            $user = User::find(Auth::id());

            Log::info('User is super-admin, fetching all organisations');
            $organisations = Organisation::where('status', '<>', 'deleted')->get();

            Log::debug('Organisations fetched successfully', ['count' => $organisations->count()]);

            return view('foundation::organisations.index', compact('organisations'));
        } catch (\Exception $e) {
            Log::error('Failed to fetch organisations', ['error' => $e->getMessage()]);
            Log::debug($e->getTraceAsString());
            return redirect()->route('dashboard')->with('error', 'Failed to load organisations');
        }
    }



    // Show create form
    public function create()
    {
        return view('foundation::organisations.form');
    }

    // Store a new organisation
    public function store(Request $request)
    {
        try {
            Log::info('Attempting to create new organisation');

            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'status' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                Log::warning('Organisation validation failed', ['errors' => $validator->errors()]);
                return redirect()->back()->withErrors($validator)->withInput();
            }

            $organisation = Organisation::create([
                'uid' => Str::ulid(),
                'name' => $request->name,
                'description' => $request->description ?? '',
                'status' => $request->status ?? 'unknown',
                'created_by' => Auth::user()->id ?? 0,
                'updated_by' => Auth::user()->id ?? 0,
            ]);

            Log::info('Organisation created successfully', ['organisation_uid' => $organisation->uid]);

            return redirect()->route('organisations.show', $organisation->uid)
                ->with('success', 'Organisation created successfully');
        } catch (\Exception $e) {
            Log::error('Failed to create organisation', ['error' => $e->getMessage()]);
            return redirect()->back()->with('error', 'Failed to create organisation')->withInput();
        }
    }

    // Show a specific organisation
    public function show($organisationUid)
    {
        try {
            Log::info('Fetching organisation', ['organisation_uid' => $organisationUid]);

            $organisation = Organisation::where('uid', $organisationUid)->firstOrFail();

            Log::debug('Organisation fetched successfully', ['organisation' => $organisation->toArray()]);

            return view('foundation::organisations.show', compact('organisation'));
        } catch (\Exception $e) {
            Log::error('Failed to fetch organisation', [
                'organisation_uid' => $organisationUid,
                'error' => $e->getMessage()
            ]);
            return redirect()->route('organisations.index')->with('error', 'Organisation not found');
        }
    }

    // Show edit form
    public function edit($organisationUid)
    {
        try {
            $organisation = Organisation::where('uid', $organisationUid)->firstOrFail();
            if (!isset($organisation)) {
                throw new \Exception('Organisation not found uid: ' . $organisationUid);
            }
            return view('foundation::organisations.form', compact('organisation'));
        } catch (\Exception $e) {
            Log::error('Failed to load edit form', ['organisation_uid' => $organisationUid, 'error' => $e->getMessage()]);
            return redirect()->route('organisations.index')->with('error', $e->getMessage());
        }
    }

    // Update an organisation
    public function update(Request $request, $organisationUid)
    {
        try {
            Log::info('Attempting to update organisation', ['organisation_uid' => $organisationUid]);

            $organisation = Organisation::where('uid', $organisationUid)->firstOrFail();
            if (!isset($organisation)) {
                throw new \Exception('Organisation not found uid: ' . $organisationUid);
            }

            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|string|max:255',
                'description' => 'nullable|string',
                'status' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                Log::warning('Organisation update validation failed', ['errors' => $validator->errors()]);
                return redirect()->back()->withErrors($validator)->withInput();
            }

            $organisation->update([
                'name' => $request->name ?? $organisation->name,
                'description' => $request->description ?? $organisation->description,
                'status' => $request->status ?? $organisation->status,
                'updated_by' => Auth::user()->id ?? $organisation->updated_by,
            ]);

            Log::info('Organisation updated successfully', ['organisation_uid' => $organisationUid]);

            return redirect()->route('organisations.show', $organisation->uid)
                ->with('success', 'Organisation updated successfully');
        } catch (\Exception $e) {
            Log::error('Failed to update organisation', ['organisation_uid' => $organisationUid, 'error' => $e->getMessage()]);
            return redirect()->back()->with('error', 'Failed to update organisation')->withInput();
        }
    }

    // Delete an organisation
    public function destroy($organisationUid)
    {
        try {
            Log::info('Attempting to delete organisation', ['organisation_uid' => $organisationUid]);

            $organisation = Organisation::where('uid', $organisationUid)->firstOrFail();
            if (!isset($organisation)) {
                throw new \Exception('Organisation not found uid: ' . $organisationUid);
            }

            $organisation->delete();

            Log::info('Organisation deleted successfully', ['organisation_uid' => $organisationUid]);

            return redirect()->route('organisations.index')
                ->with('success', 'Organisation deleted successfully');
        } catch (\Exception $e) {
            Log::error('Failed to delete organisation', ['organisation_uid' => $organisationUid, 'error' => $e->getMessage()]);
            return redirect()->back()->with('error', 'Failed to delete organisation');
        }
    }
}