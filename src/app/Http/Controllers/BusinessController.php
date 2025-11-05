<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class BusinessController extends Controller
{
    public function index(Request $request)
    {
        $myProjects = $request->input('myProjects', 0);
        $search = trim($request->input('search', ''));
        $sort = trim((string) $request->input('sort', 'id'));
        $type = $request->input('type', 'all');

        if ($type == 'all') {
            $type = false;
        }

        $query = Business::query()
            ->with('user')
            ->when(
                $myProjects && $request->user(),
                fn($q) =>
                $q->where('user_id', $request->user()->id)
            )
            ->when(
                $type && $type !== 'all',
                fn($q) =>
                $q->where('type', $type)
            )
            ->when(
                $search !== '',
                fn($q) =>
                $q->where(function ($qq) use ($search) {
                    $qq->where('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                })
            );
        if ($sort === "popular") {
            $query->withCount([
                'orders as orders_count' => fn($q) => $q,
            ])
                ->orderByDesc('orders_count')
                ->orderByDesc('id');
        } else {
            $query->orderByDesc($sort);
        }

        $businesses = $query->paginate(8)->appends($request->query());
        $types = Cache::remember('business_types', 10080, function () {
            return Business::distinct()->pluck('type');
        });
        $myRequests = Order::query()
            ->select('orders.*', 'businesses.name as business_name', 'businesses.image_original as business_image')
            ->join('businesses', 'orders.business_id', '=', 'businesses.id')
            ->whereHas('business', fn($q) => $q
                ->where('user_id', auth('api')->id()))
            ->orderBy('created_at', 'desc')
            ->get();
        $unreadCount = $myRequests->where('is_read', false)->count();

        $serverInfo = $search;
        return response()->json([
            'businesses' => $businesses,
            'myProjects' => (bool)$myProjects,
            'types' => $types,
            'myRequests' => $myRequests,
            'unreadCount' => $unreadCount,
            'serverInfo' => $sort,
        ]);
    }

    public function request(Request $request, $editingId)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'date' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
        ]);
        $order = Order::create([
            "name" => $request->name,
            "description" => $request->description,
            "date" => $request->date,
            "phone" => $request->phone,
            "business_id" => $editingId,
        ]);

        if ($order) {

            return response()->json([
                'message' => 'Your request added successfully! We feedback you soon!',
            ], 201);
        }
        return response()->json([
            'error' => 'Failed to create project',
        ], 500);
    }

    public function markAsRead(Request $request)
    {
        Order::whereHas('business', function ($q) {
            $q->where('user_id', auth('api')->id());
        })
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return response()->json([
            'message' => 'Marked as read'
        ], 200);
    }


    public function store(Request $request)
    {
        if (!auth('api')->check()) {
            return response()->json([
                'message' => 'Unauthorized',
            ], 401);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'required|string|max:255',
            'type' => 'required|string|max:255',
        ]);
        try {

            $business = Business::create([
                "name" => $request->name,
                "description" => $request->description,
                "type" => $request->type,
                "user_id" => auth('api')->id(),
            ]);
            sendTelegram("🆕 New business added: <b>{$request->name}</b>\nuser: <b>" . (auth('api')->user()->name ?? 'unknown') . "</b>");
            return response()->json([
                'message'  => 'Business created successfully',
                'business' => $business->fresh()->only([
                    'id',
                    'name',
                    'description',
                    'type',
                    'image_original',
                    'image_thumb_webp',
                    'image_card_webp',
                    'image_original_url',
                    'image_thumb_url',
                    'image_card_url',
                ]),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create project',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    public function update(Request $request, Business $business)
    {
        if (!auth('api')->check()) {
            return response()->json([
                'message' => 'Unauthorized',
            ], 401);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'required|string|max:255',
            'type' => 'required|string|max:255',
        ]);

        if (!$business || $business->user_id !== auth('api')->id()) {
            return response()->json([
                'message' => 'Forbidden',
            ], 403);
        }
        try {

            $business->name = $request->name;
            $business->description = $request->description;
            $business->type = $request->type;

            $business->save();

            return response()->json([
                'message' => 'Project updated successfully!',
                'business' => $business,
            ], 200);
        } catch (\Exception $e) {
            Log::error($e->getMessage());

            return response()->json([
                'message' => 'Failed to edit project',
            ], 500);
        }
    }


    public function destroy(Business $business)
    {
        if ($business->user_id !== auth('api')->id()) {
            return response()->json(['message' => 'Unable to delete project!'], 403);
        }

        try {
            $disk = 'public';

            // 🧹 1️⃣ Delete images before removing DB record
            $paths = array_filter([
                $business->image_original,
                $business->image_thumb_webp,
                $business->image_card_webp,
            ]);

            if (!empty($paths)) {
                Storage::disk($disk)->delete($paths);
            }

            // 🧹 2️⃣ Delete entire folder (e.g. images/businesses/YYYY/MM/DD/{id})
            if (!empty($business->image_original)) {
                $baseDir = dirname($business->image_original);
                Storage::disk($disk)->deleteDirectory($baseDir);
            }

            // 🧾 3️⃣ Delete DB record
            $business->delete();

            return response()->json([
                'message' => 'Project and its images were successfully deleted!',
            ], 200);
        } catch (\Throwable $e) {
            // 🧠 Log error for debugging
            \Log::error('Business deletion failed', [
                'business_id' => $business->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'An error occurred while deleting the project.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
