<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TourPackage;
use App\Models\TourCategory;
use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * @group Tour Management
 *
 * APIs for managing tours and tour packages
 */
class TourController extends Controller
{
    /**
     * Get all tours with filtering, pagination and sorting
     *
     * @queryParam status string Filter by status (active/inactive/draft). Example: active
     * @queryParam category_id string Filter by category ID. Example: 1
     * @queryParam tour_type string Filter by tour type (domestic/international/local). Example: domestic
     * @queryParam from_state_id integer Filter by departure state ID. Example: 1
     * @queryParam to_state_id integer Filter by destination state ID. Example: 2
     * @queryParam min_price number Filter by minimum price. Example: 1000
     * @queryParam max_price number Filter by maximum price. Example: 5000
     * @queryParam duration_min integer Filter by minimum duration (days). Example: 3
     * @queryParam duration_max integer Filter by maximum duration (days). Example: 10
     * @queryParam search string Search in tour name and description. Example: beach
     * @queryParam sort_by string Sort by field (name/base_price_adult/duration/created_at). Example: base_price_adult
     * @queryParam sort_order string Sort order (asc/desc). Example: asc
     * @queryParam per_page integer Items per page (max 100). Example: 15
     * @queryParam page integer Page number. Example: 1
     *
     * @response {
     *   "data": [
     *     {
     *       "id": "uuid-here",
     *       "name": "Cox's Bazar Beach Tour",
     *       "slug": "coxs-bazar-beach-tour",
     *       "description": "Beautiful beach tour package",
     *       "base_price_adult": 15000,
     *       "base_price_child": 12000,
     *       "duration_days": 3,
     *       "duration_nights": 2,
     *       "max_booking_per_day": 20,
     *       "tour_type": "domestic",
     *       "status": "active",
     *       "is_featured": true,
     *       "category": {
     *         "id": 1,
     *         "name": "Beach Tours"
     *       },
     *       "from_state": {
     *         "id": 1,
     *         "name": "Dhaka"
     *       },
     *       "to_state": {
     *         "id": 2,
     *         "name": "Chittagong"
     *       },
     *       "tour_route": "Dhaka → Chittagong",
     *       "bookings_count": 15,
     *       "created_at": "2023-04-29T10:00:00.000000Z"
     *     }
     *   ],
     *   "meta": {
     *     "current_page": 1,
     *     "last_page": 5,
     *     "per_page": 15,
     *     "total": 75,
     *     "from": 1,
     *     "to": 15
     *   }
     * }
     */
    public function index(Request $request)
        {
            $query = TourPackage::with([
                'category:id,name',
                'fromState:id,name',
                'toState:id,name',
                'galleries' => function($q) {
                    // Get featured gallery first, if none then get position 1, else random
                    $q->select('id', 'tour_package_id', 'image_url', 'position', 'is_featured')
                    ->where('is_featured', true)
                    ->orWhere(function($subQ) {
                        $subQ->where('position', 1)->orWhereRaw('1=1');
                    })
                    ->orderByRaw('is_featured DESC, position ASC')
                    ->limit(1);
                }
            ])->withCount('bookings');

            // Status filter
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            // Category filter
            if ($request->has('category_id')) {
                $query->where('category_id', $request->category_id);
            }

            // Tour type filter
            if ($request->has('tour_type')) {
                $query->where('tour_type', $request->tour_type);
            }

            // Location filters
            if ($request->has('from_state_id')) {
                $query->where('from_state_id', $request->from_state_id);
            }
            if ($request->has('to_state_id')) {
                $query->where('to_state_id', $request->to_state_id);
            }

            // Price range filter
            if ($request->has('min_price')) {
                $query->where('base_price_adult', '>=', $request->min_price);
            }
            if ($request->has('max_price')) {
                $query->where('base_price_adult', '<=', $request->max_price);
            }

            // Duration filter
            if ($request->has('duration_min')) {
                $query->where('number_of_days', '>=', $request->duration_min);
            }
            if ($request->has('duration_max')) {
                $query->where('number_of_days', '<=', $request->duration_max);
            }

            // Search filter
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
                });
            }

            // Sorting
            $sortBy = $request->get('sort_by', 'created_at');
            $sortOrder = $request->get('sort_order', 'desc');
            
            $allowedSortFields = ['title', 'base_price_adult', 'number_of_days', 'created_at'];
            if (in_array($sortBy, $allowedSortFields)) {
                $query->orderBy($sortBy, $sortOrder);
            }

            // Pagination - Select only existing fields
            $perPage = min($request->get('per_page', 15), 100);
            $tours = $query->select([
                'id',
                'title',
                'slug', 
                'base_price_adult',
                'base_price_child',
                'number_of_days',
                'number_of_nights',
                'tour_type',
                'status',
                'is_featured',
                'is_popular',
                'category_id',
                'from_state_id',
                'to_state_id',
                'created_at'
            ])->paginate($perPage);

            // Transform response with only existing fields
            $tours->getCollection()->transform(function ($tour) {
                return [
                    'id' => $tour->id,
                    'name' => $tour->title,
                    'slug' => $tour->slug,
                    'base_price_adult' => $tour->base_price_adult,
                    'base_price_child' => $tour->base_price_child,
                    'duration_days' => $tour->number_of_days,
                    'duration_nights' => $tour->number_of_nights,
                    'tour_type' => $tour->tour_type,
                    'status' => $tour->status,
                    'is_featured' => $tour->is_featured,
                    'is_popular' => $tour->is_popular,
                    'category' => $tour->category,
                    'from_state' => $tour->fromState,
                    'to_state' => $tour->toState,
                    'tour_route' => $tour->getTourRoute(),
                    'featured_image' => $tour->galleries->first()?->image_url,
                    'bookings_count' => $tour->bookings_count,
                    'created_at' => $tour->created_at
                ];
            });

            return response()->json([
                'data' => $tours->items(),
                'meta' => [
                    'current_page' => $tours->currentPage(),
                    'last_page' => $tours->lastPage(),
                    'per_page' => $tours->perPage(),
                    'total' => $tours->total()
                ]
            ]);
        }
  

    /**
     * Get a specific tour
     *
     * @urlParam slug string required The slug of the tour. Example: coxs-bazar-beach-tour
     *
     * @response {
     *   "data": {
     *     "id": "uuid-here",
     *     "name": "Cox's Bazar Beach Tour",
     *     "slug": "coxs-bazar-beach-tour",
     *     "description": "Complete beach tour package with accommodation",
     *     "base_price_adult": 15000,
     *     "base_price_child": 12000,
     *     "duration_days": 3,
     *     "duration_nights": 2,
     *     "max_booking_per_day": 20,
     *     "tour_type": "domestic",
     *     "status": "active",
     *     "is_featured": true,
     *     "category": {
     *       "id": 1,
     *       "name": "Beach Tours",
     *       "description": "Beach and coastal tours"
     *     },
     *     "from_state": {
     *       "id": 1,
     *       "name": "Dhaka"
     *     },
     *     "to_state": {
     *       "id": 2,
     *       "name": "Chittagong"
     *     },
     *     "itineraries": [
     *       {
     *         "day": 1,
     *         "title": "Arrival",
     *         "description": "Check-in and beach walk"
     *       }
     *     ],
     *     "galleries": [
     *       {
     *         "id": 1,
     *         "image_url": "https://example.com/image1.jpg",
     *         "alt_text": "Beach view"
     *       }
     *     ],
     *     "faqs": [
     *       {
     *         "question": "What's included?",
     *         "answer": "All meals and accommodation"
     *       }
     *     ],
     *     "tour_route": "Dhaka → Chittagong",
     *     "bookings_count": 25,
     *     "created_at": "2023-04-29T10:00:00.000000Z"
     *   }
     * }
     */
public function show($slug)
{
    $tour = TourPackage::with([
        'category:id,name,meta_description',
        'fromState:id,name',
        'toState:id,name',
        'fromZella:id,name',
        'toZella:id,name',
        'fromUpazilla:id,name',
        'toUpazilla:id,name',
        'fromCountry:id,name',
        'toCountry:id,name',
        'itineraries:id,tour_package_id,name,title,description,position',
        'galleries:id,tour_package_id,image_url,position,is_featured',
        'faqs:id,tour_package_id,question,answer,position'
    ])
    ->withCount('bookings')
    ->where('slug', $slug)
    ->firstOrFail();

    // Transform the response with ALL available fields
    return response()->json([
        'data' => [
            // Basic Info
            'id' => $tour->id,
            'name' => $tour->title,
            'slug' => $tour->slug,
            'description' => $tour->description,
            
            // SEO Fields (MISSING)
            'meta_title' => $tour->meta_title,
            'meta_description' => $tour->meta_description,
            'meta_keywords' => $tour->meta_keywords,
            
            // Content Fields (MISSING)
            'highlights' => $tour->highlights,
            'tour_schedule' => $tour->tour_schedule,
            'whats_included' => $tour->whats_included,
            'whats_excluded' => $tour->whats_excluded,
            
            // Media Fields (MISSING)
            'area_map_url' => $tour->area_map_url,
            'guide_pdf_url' => $tour->guide_pdf_url,
            
            // Pricing
            'base_price_adult' => $tour->base_price_adult,
            'base_price_child' => $tour->base_price_child,
            'agent_commission_percent' => $tour->agent_commission_percent, // MISSING
            
            // Duration & Capacity
            'duration_days' => $tour->number_of_days,
            'duration_nights' => $tour->number_of_nights,
            'max_booking_per_day' => $tour->max_booking_per_day,
            
            // Location Details (MISSING)
            'from_location_details' => $tour->from_location_details,
            'to_location_details' => $tour->to_location_details,
            
            // Tour Properties
            'tour_type' => $tour->tour_type,
            'status' => $tour->status,
            'is_featured' => $tour->is_featured,
            'is_popular' => $tour->is_popular,
            
            // Relationships
            'category' => [
                'id' => $tour->category->id,
                'name' => $tour->category->name,
                'description' => $tour->category->meta_description
            ],
            
            // Complete Location Data
            'from_location' => [
                'country' => $tour->fromCountry,
                'state' => $tour->fromState,
                'zella' => $tour->fromZella,
                'upazilla' => $tour->fromUpazilla,
                'details' => $tour->from_location_details
            ],
            'to_location' => [
                'country' => $tour->toCountry,
                'state' => $tour->toState,
                'zella' => $tour->toZella,
                'upazilla' => $tour->toUpazilla,
                'details' => $tour->to_location_details
            ],
            
            // Helper Methods
            'tour_route' => $tour->getTourRoute(),
            'from_location_name' => $tour->getFromLocationName(),
            'to_location_name' => $tour->getToLocationName(),
            'tour_type_label' => $tour->getTourTypeLabel(),
            
            // Media Collections
            'galleries' => $tour->galleries->sortBy('position')->map(function($gallery) {
                return [
                    'id' => $gallery->id,
                    'image_url' => $gallery->image_url,
                    'position' => $gallery->position,
                    'is_featured' => $gallery->is_featured
                ];
            }),
            
            'itineraries' => $tour->itineraries->sortBy('position')->map(function($itinerary) {
                return [
                    'name' => $itinerary->name,
                    'title' => $itinerary->title,
                    'description' => $itinerary->description,
                    'position' => $itinerary->position
                ];
            }),
            
            'faqs' => $tour->faqs->sortBy('position')->map(function($faq) {
                return [
                    'question' => $faq->question,
                    'answer' => $faq->answer,
                    'position' => $faq->position
                ];
            }),
            
            // Stats
            'bookings_count' => $tour->bookings_count,
            'created_at' => $tour->created_at,
            'updated_at' => $tour->updated_at
        ]
    ]);
}
    /**
     * Get featured tours
     *
     * @queryParam limit integer Number of tours to return (max 20). Example: 6
     * @queryParam status string Filter by status (active/inactive). Example: active
     *
     * @response {
     *   "data": [
     *     {
     *       "id": "uuid-here",
     *       "name": "Cox's Bazar Beach Tour",
     *       "slug": "coxs-bazar-beach-tour",
     *       "base_price_adult": 15000,
     *       "base_price_child": 12000,
     *       "duration_days": 3,
     *       "tour_type": "domestic",
     *       "status": "active",
     *       "is_featured": true,
     *       "category": {
     *         "id": 1,
     *         "name": "Beach Tours"
     *       },
     *       "to_state": {
     *         "id": 2,
     *         "name": "Chittagong"
     *       },
     *       "tour_route": "Dhaka → Chittagong",
     *       "bookings_count": 25
     *     }
     *   ]
     * }
     */
    public function featured(Request $request)
    {
        $limit = min($request->get('limit', 6), 20);
        
        $query = TourPackage::where('is_featured', true)
            ->with([
                'category:id,name',
                'fromState:id,name',
                'toState:id,name'
            ])
            ->withCount('bookings');

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $tours = $query->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        // Add calculated fields
        $tours->each(function ($tour) {
            $tour->tour_route = $tour->getTourRoute();
        });

        return response()->json(['data' => $tours]);
    }

 /**
 * Get popular tours (admin marked + most booked)
 *
 * @queryParam limit integer Number of tours to return (max 20). Example: 8
 * @queryParam status string Filter by status (active/inactive). Example: active
 *
 * @response {
 *   "data": [
 *     {
 *       "id": "uuid-here",
 *       "name": "Cox's Bazar Beach Tour",
 *       "slug": "coxs-bazar-beach-tour",
 *       "base_price_adult": 15000,
 *       "base_price_child": 12000,
 *       "duration_days": 3,
 *       "tour_type": "domestic",
 *       "status": "active",
 *       "is_popular": true,
 *       "category": {
 *         "id": 1,
 *         "name": "Beach Tours"
 *       },
 *       "to_state": {
 *         "id": 2,
 *         "name": "Chittagong"
 *       },
 *       "tour_route": "Dhaka → Chittagong",
 *       "bookings_count": 150
 *     }
 *   ]
 * }
 */
public function popular(Request $request)
{
    $limit = min($request->get('limit', 8), 20);
    $tours = collect();

    // Step 1: Get tours marked as popular by admin
    $popularQuery = TourPackage::where('is_popular', true)
        ->with([
            'category:id,name',
            'fromState:id,name',
            'toState:id,name'
        ])
        ->withCount(['bookings' => function ($query) {
            $query->where('status', 'completed');
        }]);

    if ($request->has('status')) {
        $popularQuery->where('status', $request->status);
    }

    $adminPopularTours = $popularQuery->orderBy('created_at', 'desc')->get();
    $tours = $tours->merge($adminPopularTours);

    // Step 2: If we don't have enough tours, get most booked tours
    if ($tours->count() < $limit) {
        $needed = $limit - $tours->count();
        $excludeIds = $tours->pluck('id')->toArray();

        $mostBookedQuery = TourPackage::whereNotIn('id', $excludeIds)
            ->with([
                'category:id,name',
                'fromState:id,name',
                'toState:id,name'
            ])
            ->withCount(['bookings' => function ($query) {
                $query->where('status', 'completed');
            }]);

        if ($request->has('status')) {
            $mostBookedQuery->where('status', $request->status);
        }

        $mostBookedTours = $mostBookedQuery->having('bookings_count', '>', 0)
            ->orderBy('bookings_count', 'desc')
            ->limit($needed)
            ->get();

        $tours = $tours->merge($mostBookedTours);
    }

    // Add calculated fields
    $tours->each(function ($tour) {
        $tour->tour_route = $tour->getTourRoute();
        $tour->name = $tour->title; // Add name alias for consistency
        $tour->duration_days = $tour->number_of_days; // Add duration alias
    });

    return response()->json(['data' => $tours->take($limit)->values()]);
}

    /**
     * Get tour categories
     *
     * @queryParam status string Filter by status (active/inactive). Example: active
     *
     * @response {
     *   "data": [
     *     {
     *       "id": 1,
     *       "name": "Beach Tours",
     *       "description": "Beach and coastal tours",
     *       "status": "active",
     *       "tours_count": 15,
     *       "created_at": "2023-04-29T10:00:00.000000Z"
     *     }
     *   ]
     * }
     */
    public function categories(Request $request)
    {
        $query = TourCategory::withCount(['tourPackages' => function ($query) {
            $query->where('status', 'active');
        }]);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $categories = $query->orderBy('name')->get();

        return response()->json(['data' => $categories]);
    }



    /**
     * Get tours by category
     *
     * @urlParam category_id integer required The ID of the category. Example: 1
     * @queryParam status string Filter by status (active/inactive). Example: active
     * @queryParam per_page integer Items per page (max 50). Example: 12
     *
     * @response {
     *   "data": [
     *     {
     *       "id": "uuid-here",
     *       "name": "Cox's Bazar Beach Tour",
     *       "base_price_adult": 15000,
     *       "duration_days": 3,
     *       "tour_type": "domestic",
     *       "status": "active",
     *       "tour_route": "Dhaka → Chittagong",
     *       "bookings_count": 25
     *     }
     *   ],
     *   "meta": {
     *     "current_page": 1,
     *     "last_page": 2,
     *     "per_page": 12,
     *     "total": 20
     *   }
     * }
     */
    public function toursByCategory($category_id, Request $request)
    {
        $query = TourPackage::where('category_id', $category_id)
            ->with([
                'fromState:id,name',
                'toState:id,name'
            ])
            ->withCount('bookings');

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $perPage = min($request->get('per_page', 12), 50);
        $tours = $query->orderBy('created_at', 'desc')->paginate($perPage);

        // Add calculated fields
        $tours->getCollection()->transform(function ($tour) {
            $tour->tour_route = $tour->getTourRoute();
            return $tour;
        });

        return response()->json([
            'data' => $tours->items(),
            'meta' => [
                'current_page' => $tours->currentPage(),
                'last_page' => $tours->lastPage(),
                'per_page' => $tours->perPage(),
                'total' => $tours->total(),
                'from' => $tours->firstItem(),
                'to' => $tours->lastItem()
            ]
        ]);
    }

    /**
     * Search tours
     *
     * @queryParam q string required Search query. Example: beach tour
     * @queryParam status string Filter by status (active/inactive). Example: active
     * @queryParam category_id integer Filter by category. Example: 1
     * @queryParam tour_type string Filter by tour type. Example: domestic
     * @queryParam per_page integer Items per page (max 50). Example: 10
     *
     * @response {
     *   "data": [
     *     {
     *       "id": "uuid-here",
     *       "name": "Cox's Bazar Beach Tour",
     *       "description": "Beautiful beach experience...",
     *       "base_price_adult": 15000,
     *       "duration_days": 3,
     *       "tour_type": "domestic",
     *       "status": "active",
     *       "category": {
     *         "name": "Beach Tours"
     *       },
     *       "tour_route": "Dhaka → Chittagong",
     *       "bookings_count": 25
     *     }
     *   ],
     *   "meta": {
     *     "query": "beach tour",
     *     "total_found": 5
     *   }
     * }
     */
    public function search(Request $request)
    {
        $request->validate([
            'q' => 'required|string|min:2'
        ]);

        $searchQuery = $request->q;
        
        $query = TourPackage::with([
            'category:id,name',
            'fromState:id,name',
            'toState:id,name'
        ])
        ->withCount('bookings')
        ->where(function ($q) use ($searchQuery) {
            $q->where('title', 'like', "%{$searchQuery}%") // Fix field name
              ->orWhere('description', 'like', "%{$searchQuery}%")
              ->orWhereHas('category', function ($categoryQuery) use ($searchQuery) {
                  $categoryQuery->where('name', 'like', "%{$searchQuery}%");
              })
              ->orWhereHas('toState', function ($stateQuery) use ($searchQuery) {
                  $stateQuery->where('name', 'like', "%{$searchQuery}%");
              });
        });

        // Additional filters
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->has('tour_type')) {
            $query->where('tour_type', $request->tour_type);
        }

        $perPage = min($request->get('per_page', 10), 50);
        $tours = $query->orderBy('bookings_count', 'desc')->paginate($perPage);

        // Add calculated fields
        $tours->getCollection()->transform(function ($tour) {
            $tour->tour_route = $tour->getTourRoute();
            $tour->name = $tour->title; // Add name alias
            return $tour;
        });

        return response()->json([
            'data' => $tours->items(),
            'meta' => [
                'query' => $searchQuery,
                'current_page' => $tours->currentPage(),
                'last_page' => $tours->lastPage(),
                'per_page' => $tours->perPage(),
                'total' => $tours->total(),
                'from' => $tours->firstItem(),
                'to' => $tours->lastItem()
            ]
        ]);
    }
}