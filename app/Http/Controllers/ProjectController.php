<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Exception;

class ProjectController extends Controller
{
    /**
     * Display a listing of projects.
     */
    public function index(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 15);
            $userId = $request->get('user_id');
            $status = $request->get('status');

            // Limit per_page to maximum 100
            if ($perPage > 100) {
                $perPage = 100;
            }

            $query = Project::with('user:id,name,email')
                ->select(['id', 'title', 'description', 'status', 'start_date', 'end_date', 'user_id', 'created_at', 'updated_at']);

            // Filter by user if provided
            if ($userId) {
                $query->byUser($userId);
            }

            // Filter by status if provided
            if ($status && in_array($status, array_keys(Project::getStatusOptions()))) {
                $query->byStatus($status);
            }

            $projects = $query->orderBy('created_at', 'desc')->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => [
                    'projects' => $projects->items(),
                    'pagination' => [
                        'current_page' => $projects->currentPage(),
                        'total_pages' => $projects->lastPage(),
                        'per_page' => $projects->perPage(),
                        'total_projects' => $projects->total(),
                        'from' => $projects->firstItem(),
                        'to' => $projects->lastItem(),
                    ]
                ]
            ]);

        } catch (Exception $e) {
            Log::error('Get projects error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'SERVER_ERROR',
                    'message' => 'Unable to fetch projects. Please try again.'
                ]
            ], 500);
        }
    }

    /**
     * Store a newly created project.
     */
    public function store(Request $request)
    {
        // Validation
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'status' => ['sometimes', 'string', 'in:pending,in_progress,completed,cancelled'],
            'start_date' => ['nullable', 'date', 'after_or_equal:today'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        try {
            // Create project using database transaction
            $project = DB::transaction(function () use ($data) {
                return Project::create([
                    'title' => $data['title'],
                    'description' => $data['description'] ?? null,
                    'status' => $data['status'] ?? 'pending',
                    'start_date' => $data['start_date'] ?? null,
                    'end_date' => $data['end_date'] ?? null,
                    'user_id' => $data['user_id'],
                ]);
            });

            // Load user relationship
            $project->load('user:id,name,email');

            Log::info('Project created successfully', [
                'project_id' => $project->id,
                'title' => $project->title,
                'user_id' => $project->user_id,
                'ip' => $request->ip()
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'project' => $project,
                    'message' => 'Project created successfully.'
                ]
            ], 201);

        } catch (Exception $e) {
            Log::error('Project creation error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->only(['title', 'user_id'])
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'CREATION_ERROR',
                    'message' => 'Unable to create project. Please try again.'
                ]
            ], 500);
        }
    }

    /**
     * Display the specified project.
     */
    public function show($id)
    {
        try {
            // Validate ID format
            if (!is_numeric($id) || $id <= 0) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'INVALID_PROJECT_ID',
                        'message' => 'Invalid project ID provided.'
                    ]
                ], 400);
            }

            $project = Project::with('user:id,name,email')->find($id);

            if (!$project) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'PROJECT_NOT_FOUND',
                        'message' => 'Project not found.'
                    ]
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'project' => $project
                ]
            ]);

        } catch (Exception $e) {
            Log::error('Get project error', [
                'project_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'SERVER_ERROR',
                    'message' => 'Unable to fetch project. Please try again.'
                ]
            ], 500);
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        try {
            // Validate ID format
            if (!is_numeric($id) || $id <= 0) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'INVALID_PROJECT_ID',
                        'message' => 'Invalid project ID provided.'
                    ]
                ], 400);
            }

            // Find project
            $project = Project::find($id);

            if (!$project) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'PROJECT_NOT_FOUND',
                        'message' => 'Project not found.'
                    ]
                ], 404);
            }

            // Validation - This will automatically return 422 on validation failure
            $data = $request->validate([
                'title' => ['sometimes', 'string', 'max:255'],
                'description' => ['nullable', 'string', 'max:5000'],
                'status' => ['sometimes', 'string', 'in:pending,in_progress,completed,cancelled'],
                'start_date' => ['nullable', 'date'],
                'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
                'user_id' => ['sometimes', 'integer', 'exists:users,id'],
            ]);

            // Update project using database transaction
            $updatedProject = DB::transaction(function () use ($project, $data) {
                $project->update(array_filter($data, function($value) {
                    return $value !== null;
                }));
                
                // Reload to get fresh data
                return $project->fresh(['user']);
            });

            Log::info('Project updated successfully', [
                'project_id' => $project->id,
                'title' => $project->title,
                'user_id' => $project->user_id,
                'ip' => $request->ip()
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'project' => $updatedProject,
                    'message' => 'Project updated successfully.'
                ]
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            // Re-throw validation exceptions to be handled by Laravel
            throw $e;
        } catch (Exception $e) {
            Log::error('Project update error', [
                'project_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'UPDATE_ERROR',
                    'message' => 'Unable to update project. Please try again.'
                ]
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            // Validate ID format
            if (!is_numeric($id) || $id <= 0) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'INVALID_PROJECT_ID',
                        'message' => 'Invalid project ID provided.'
                    ]
                ], 400);
            }

            // Find project
            $project = Project::find($id);

            if (!$project) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'PROJECT_NOT_FOUND',
                        'message' => 'Project not found.'
                    ]
                ], 404);
            }

            // Store project data for response before deletion
            $deletedProject = [
                'id' => $project->id,
                'title' => $project->title,
                'user_id' => $project->user_id
            ];

            // Delete project using database transaction
            DB::transaction(function () use ($project) {
                $project->delete();
            });

            Log::info('Project deleted successfully', $deletedProject);

            return response()->json([
                'success' => true,
                'data' => [
                    'message' => 'Project deleted successfully.',
                    'deleted_project' => $deletedProject
                ]
            ]);

        } catch (Exception $e) {
            Log::error('Project deletion error', [
                'project_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'DELETION_ERROR',
                    'message' => 'Unable to delete project. Please try again.'
                ]
            ], 500);
        }
    }
}
