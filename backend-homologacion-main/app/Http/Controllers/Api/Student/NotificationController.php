<?php

namespace App\Http\Controllers\Api\Student;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate(['per_page' => ['nullable', 'integer', 'between:1,100'], 'sin_leer' => ['nullable', 'boolean']]);
        $notifications = $request->user()->notifications()
            ->when($request->boolean('sin_leer'), fn (Builder $query): Builder => $query->whereNull('read_at'))
            ->orderByDesc('id')->paginate($filters['per_page'] ?? 15)->withQueryString();

        return response()->json([
            'success' => true,
            'data' => $notifications->getCollection()->map(fn (DatabaseNotification $notification): array => [
                'id' => (string) $notification->id,
                'data' => [
                    'solicitud_id' => (int) $notification->data['solicitud_id'],
                    'evento' => (string) $notification->data['evento'],
                    'mensaje' => (string) $notification->data['mensaje'],
                    'url' => (string) $notification->data['url'],
                ],
                'read_at' => $notification->read_at === null ? null : (string) $notification->read_at->toISOString(),
                'created_at' => (string) $notification->created_at->toISOString(),
            ]),
            'meta' => ['current_page' => $notifications->currentPage(), 'last_page' => $notifications->lastPage(), 'per_page' => $notifications->perPage(), 'total' => $notifications->total()],
            'links' => ['first' => $notifications->url(1), 'last' => $notifications->url($notifications->lastPage()), 'prev' => $notifications->previousPageUrl(), 'next' => $notifications->nextPageUrl()],
            'sin_leer' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    public function update(Request $request, string $notificacion): JsonResponse
    {
        /** @var DatabaseNotification $notification */
        $notification = $request->user()->notifications()->findOrFail($notificacion);
        $notification->markAsRead();

        return response()->json(['success' => true, 'data' => [
            'id' => (string) $notification->id, 'read_at' => (string) $notification->read_at->toISOString(),
        ]]);
    }
}
