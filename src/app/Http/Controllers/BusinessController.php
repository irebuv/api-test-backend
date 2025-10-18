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
        $type = $request->input('type', 'all');
        if ($type == 'all') {
            $type = false;
        }
        $businesses = Business::with('user')
            ->when($myProjects, fn($q) => $q->where('user_id', $request->user()->id))
            ->when($type, fn($q) => $q->where('type', $type))
            ->orderByDesc('id')
            ->paginate(8)
            ->withQueryString();
        $types = Cache::remember('business_types', 10080, function () {
            return Business::distinct()->pluck('type');
        });
        $myRequests = Order::query()
            ->select('orders.*', 'businesses.name as business_name', 'businesses.image as business_image')
            ->join('businesses', 'orders.business_id', '=', 'businesses.id')
            ->whereHas('business', fn($q) => $q
                ->where('user_id', auth('api')->id()))
            ->orderBy('created_at', 'desc')
            ->get();
        $unreadCount = $myRequests->where('is_read', false)->count();
        return response()->json([
            'businesses' => $businesses,
            'myProjects' => (bool)$myProjects,
            'types' => $types,
            'myRequests' => $myRequests,
            'unreadCount' => $unreadCount,
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
            // 'image' => 'nullable|mimes:png,jpg,gif,jpeg,webp|max:2048',
        ]);

        try {
            $imagePath = null;

            if ($request->hasFile('image')) {
                $folders = date('Y') . '/' . date('m') . '/' . date('d');
                $imagePath = $request->file('image')->store("images/businesses/{$folders}", "public");
            }

            $business = Business::create([
                "name" => $request->name,
                "description" => $request->description,
                "image" => $imagePath,
                "type" => $request->type,
                "user_id" => auth('api')->id(),
            ]);

            return response()->json([
                'message' => 'Business created successfully',
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
            //'image' => 'nullable|mimes:png,jpg,gif,jpeg,webp|max:2048',
        ]);

        if (!$business || $business->user_id !== auth('api')->id()) {
            return response()->json([
                'message' => 'Forbidden',
            ], 403);
        }
        try {
            if ($image = $request->file('image')) {
                if ($business->image) {
                    Storage::disk('public')->delete($business->image);
                }

                $folders = date('Y') . '/' . date('m') . '/' . date('d');
                $image = $image->store("images/businesses/{$folders}", 'public');
                $business->image = $image;
            }

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
        if ($business->user_id === auth('api')->id()) {
            $business->delete();
            return response()->json([
                'message' => 'Project was successfully deleted!'
            ], 200);
        } else {
            return response()->json([
                'message' => 'Unable to delete project!'
            ], 403);
        }
    }
}
