<?php

namespace App\Http\Controllers\Api\Community;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MentionSuggestionsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = ltrim((string) $request->query('q', ''), '@');

        $usersQuery = User::query()->where('is_deleted', false);
        if ($query !== '') {
            $usersQuery->where('username', 'like', $query.'%');
        }

        $page = max(1, (int) $request->query('page', 1));
        $limit = min(100, max(1, (int) $request->query('limit', 20)));
        $paginator = $usersQuery->orderBy('username')->paginate($limit, ['id', 'username'], 'page', $page);

        $items = $paginator->getCollection()->map(fn (User $user) => [
            'id' => $user->id,
            'username' => $user->username,
        ]);

        return ApiResponse::paginated(
            $paginator,
            $items,
            'User suggestions retrieved successfully.',
            'تم استرجاع اقتراحات المستخدمين بنجاح.'
        );
    }
}
