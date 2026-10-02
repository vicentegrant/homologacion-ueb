<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\StoreAdminUserRequest;
use App\Http\Requests\Api\Admin\UpdateAdminUserRequest;
use App\Http\Requests\Api\Admin\UpdateUserStatusRequest;
use App\Http\Requests\Api\Admin\UserIndexRequest;
use App\Http\Resources\Api\UserResource;
use App\Models\Role;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(UserIndexRequest $request): AnonymousResourceCollection
    {
        $filters = $request->validated();
        $users = User::query()
            ->with(['roles', 'creador', 'carrerasCoordinadas'])
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $searchQuery) use ($search): void {
                    $searchQuery->where('nombres_completos', 'like', "%{$search}%")
                        ->orWhere('cedula', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($filters['rol'] ?? null, fn (Builder $query, string $role) => $query->role($role))
            ->when(array_key_exists('cuenta_activa', $filters), fn (Builder $query) => $query->where('cuenta_activa', $filters['cuenta_activa']))
            ->orderBy($filters['order_by'] ?? 'id', $filters['direction'] ?? 'desc')
            ->paginate($filters['per_page'] ?? 15)
            ->withQueryString();

        return UserResource::collection($users)->additional(['success' => true]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreAdminUserRequest $request, UserService $userService): JsonResponse
    {
        $user = $userService->create($request->validated(), $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Usuario registrado correctamente.',
            'data' => UserResource::make($user),
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(User $user): JsonResponse
    {
        $user->load(['roles', 'creador', 'carrerasCoordinadas']);

        return response()->json(['success' => true, 'data' => UserResource::make($user)]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateAdminUserRequest $request, User $user, UserService $userService): JsonResponse
    {
        $data = $request->validated();

        if ($request->user()->is($user) && isset($data['rol_id'])) {
            $requestedRole = Role::query()->findOrFail($data['rol_id']);

            if (! $user->hasRole($requestedRole)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No puede cambiar su propio rol administrativo.',
                ], 409);
            }
        }

        $updatedUser = $userService->update($user, $data);

        return response()->json([
            'success' => true,
            'message' => 'Usuario actualizado correctamente.',
            'data' => UserResource::make($updatedUser),
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function updateStatus(UpdateUserStatusRequest $request, User $user): JsonResponse
    {
        $active = $request->boolean('cuenta_activa');

        if ($request->user()->is($user) && ! $active) {
            return response()->json([
                'success' => false,
                'message' => 'No puede desactivar su propia cuenta.',
            ], 409);
        }

        $user->update(['cuenta_activa' => $active]);

        if (! $active) {
            $user->tokens()->delete();
        }

        return response()->json([
            'success' => true,
            'message' => $active ? 'Usuario activado correctamente.' : 'Usuario desactivado correctamente.',
            'data' => UserResource::make($user->load(['roles', 'creador', 'carrerasCoordinadas'])),
        ]);
    }
}
