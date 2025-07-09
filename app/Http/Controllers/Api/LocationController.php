<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Country;
use App\Models\State;
use App\Models\Zella;
use App\Models\Upazilla;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * @group Location Management
 *
 * APIs for managing user location
 */


class LocationController extends Controller
{
    /**
     * Get all countries
     *
     * @queryParam status string Filter by status (active/inactive). Example: active
     * @queryParam trashed boolean Include soft deleted records. Example: 1
     *
     * @response {
     *   "data": [
     *     {
     *       "id": 1,
     *       "name": "Bangladesh",
     *       "slug": "bangladesh",
     *       "code": "BD",
     *       "currency": "BDT",
     *       "currency_symbol": "৳",
     *       "status": "active",
     *       "created_at": "2023-04-29T10:00:00.000000Z",
     *       "updated_at": "2023-04-29T10:00:00.000000Z"
     *     }
     *   ]
     * }
     */
    public function countries(Request $request)
    {
        $query = Country::query();

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('trashed') && $request->trashed) {
            $query->withTrashed();
        }

        return response()->json(['data' => $query->get()]);
    }

    /**
     * Get a specific country
     *
     * @urlParam id integer required The ID of the country. Example: 1
     *
     * @response {
     *   "data": {
     *     "id": 1,
     *     "name": "Bangladesh",
     *     "slug": "bangladesh",
     *     "code": "BD",
     *     "currency": "BDT",
     *     "currency_symbol": "৳",
     *     "status": "active",
     *     "created_at": "2023-04-29T10:00:00.000000Z",
     *     "updated_at": "2023-04-29T10:00:00.000000Z"
     *   }
     * }
     */
    public function country($id)
    {
        $country = Country::findOrFail($id);
        return response()->json(['data' => $country]);
    }

    /**
     * Create a new country
     *
     * @bodyParam name string required The name of the country. Example: Bangladesh
     * @bodyParam slug string The slug for the country (generated from name if not provided). Example: bangladesh
     * @bodyParam code string required The ISO code of the country. Example: BD
     * @bodyParam currency string The currency of the country. Example: BDT
     * @bodyParam currency_symbol string The currency symbol. Example: ৳
     * @bodyParam status string The status (active/inactive). Example: active
     *
     * @response {
     *   "message": "Country created successfully",
     *   "data": {
     *     "id": 1,
     *     "name": "Bangladesh",
     *     "slug": "bangladesh",
     *     "code": "BD",
     *     "currency": "BDT",
     *     "currency_symbol": "৳",
     *     "status": "active",
     *     "created_at": "2023-04-29T10:00:00.000000Z",
     *     "updated_at": "2023-04-29T10:00:00.000000Z"
     *   }
     * }
     */
    public function storeCountry(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:countries',
            'code' => 'required|string|max:3|unique:countries',
            'currency' => 'nullable|string|max:50',
            'currency_symbol' => 'nullable|string|max:10',
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $country = Country::create($request->all());

        return response()->json([
            'message' => 'Country created successfully',
            'data' => $country
        ], 201);
    }

    /**
     * Update a country
     *
     * @urlParam id integer required The ID of the country. Example: 1
     * @bodyParam name string The name of the country. Example: Bangladesh
     * @bodyParam slug string The slug for the country. Example: bangladesh
     * @bodyParam code string The ISO code of the country. Example: BD
     * @bodyParam currency string The currency of the country. Example: BDT
     * @bodyParam currency_symbol string The currency symbol. Example: ৳
     * @bodyParam status string The status (active/inactive). Example: active
     *
     * @response {
     *   "message": "Country updated successfully",
     *   "data": {
     *     "id": 1,
     *     "name": "Bangladesh",
     *     "slug": "bangladesh",
     *     "code": "BD",
     *     "currency": "BDT",
     *     "currency_symbol": "৳",
     *     "status": "active",
     *     "created_at": "2023-04-29T10:00:00.000000Z",
     *     "updated_at": "2023-04-29T10:00:00.000000Z"
     *   }
     * }
     */
    public function updateCountry(Request $request, $id)
    {
        $country = Country::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'string|max:255',
            'slug' => 'nullable|string|max:255|unique:countries,slug,' . $id,
            'code' => 'string|max:3|unique:countries,code,' . $id,
            'currency' => 'nullable|string|max:50',
            'currency_symbol' => 'nullable|string|max:10',
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $country->update($request->all());

        return response()->json([
            'message' => 'Country updated successfully',
            'data' => $country
        ]);
    }

    /**
     * Delete a country (soft delete)
     *
     * @urlParam id integer required The ID of the country. Example: 1
     *
     * @response {
     *   "message": "Country deleted successfully"
     * }
     */
    public function deleteCountry($id)
    {
        $country = Country::findOrFail($id);
        $country->delete();

        return response()->json(['message' => 'Country deleted successfully']);
    }

    /**
     * Restore a soft-deleted country
     *
     * @urlParam id integer required The ID of the country. Example: 1
     *
     * @response {
     *   "message": "Country restored successfully"
     * }
     */
    public function restoreCountry($id)
    {
        $country = Country::withTrashed()->findOrFail($id);
        $country->restore();

        return response()->json(['message' => 'Country restored successfully']);
    }

    /**
     * Get states by country
     *
     * @urlParam country_id integer required The ID of the country. Example: 1
     * @queryParam status string Filter by status (active/inactive). Example: active
     * @queryParam trashed boolean Include soft deleted records. Example: 1
     *
     * @response {
     *   "data": [
     *     {
     *       "id": 1,
     *       "country_id": 1,
     *       "name": "Dhaka",
     *       "slug": "dhaka",
     *       "code": "DHK",
     *       "status": "active",
     *       "created_at": "2023-04-29T10:00:00.000000Z",
     *       "updated_at": "2023-04-29T10:00:00.000000Z"
     *     }
     *   ]
     * }
     */
    public function statesByCountry($country_id, Request $request)
    {
        $query = State::where('country_id', $country_id);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('trashed') && $request->trashed) {
            $query->withTrashed();
        }

        return response()->json(['data' => $query->get()]);
    }

    /**
     * Get a specific state
     *
     * @urlParam id integer required The ID of the state. Example: 1
     *
     * @response {
     *   "data": {
     *     "id": 1,
     *     "country_id": 1,
     *     "name": "Dhaka",
     *     "slug": "dhaka",
     *     "code": "DHK",
     *     "status": "active",
     *     "created_at": "2023-04-29T10:00:00.000000Z",
     *     "updated_at": "2023-04-29T10:00:00.000000Z",
     *     "country": {
     *       "id": 1,
     *       "name": "Bangladesh",
     *       "code": "BD"
     *     }
     *   }
     * }
     */
    public function state($id)
    {
        $state = State::with('country:id,name,code')->findOrFail($id);
        return response()->json(['data' => $state]);
    }

    /**
     * Create a new state
     *
     * @bodyParam country_id integer required The ID of the country. Example: 1
     * @bodyParam name string required The name of the state. Example: Dhaka
     * @bodyParam slug string The slug for the state (generated from name if not provided). Example: dhaka
     * @bodyParam code string The state code. Example: DHK
     * @bodyParam status string The status (active/inactive). Example: active
     *
     * @response {
     *   "message": "State created successfully",
     *   "data": {
     *     "id": 1,
     *     "country_id": 1,
     *     "name": "Dhaka",
     *     "slug": "dhaka",
     *     "code": "DHK",
     *     "status": "active",
     *     "created_at": "2023-04-29T10:00:00.000000Z",
     *     "updated_at": "2023-04-29T10:00:00.000000Z"
     *   }
     * }
     */
    public function storeState(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'country_id' => 'required|exists:countries,id',
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:states',
            'code' => 'nullable|string|max:10',
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $state = State::create($request->all());

        return response()->json([
            'message' => 'State created successfully',
            'data' => $state
        ], 201);
    }

    /**
     * Update a state
     *
     * @urlParam id integer required The ID of the state. Example: 1
     * @bodyParam country_id integer The ID of the country. Example: 1
     * @bodyParam name string The name of the state. Example: Dhaka
     * @bodyParam slug string The slug for the state. Example: dhaka
     * @bodyParam code string The state code. Example: DHK
     * @bodyParam status string The status (active/inactive). Example: active
     *
     * @response {
     *   "message": "State updated successfully",
     *   "data": {
     *     "id": 1,
     *     "country_id": 1,
     *     "name": "Dhaka",
     *     "slug": "dhaka",
     *     "code": "DHK",
     *     "status": "active",
     *     "created_at": "2023-04-29T10:00:00.000000Z",
     *     "updated_at": "2023-04-29T10:00:00.000000Z"
     *   }
     * }
     */
    public function updateState(Request $request, $id)
    {
        $state = State::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'country_id' => 'exists:countries,id',
            'name' => 'string|max:255',
            'slug' => 'nullable|string|max:255|unique:states,slug,' . $id,
            'code' => 'nullable|string|max:10',
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $state->update($request->all());

        return response()->json([
            'message' => 'State updated successfully',
            'data' => $state
        ]);
    }

    /**
     * Delete a state (soft delete)
     *
     * @urlParam id integer required The ID of the state. Example: 1
     *
     * @response {
     *   "message": "State deleted successfully"
     * }
     */
    public function deleteState($id)
    {
        $state = State::findOrFail($id);
        $state->delete();

        return response()->json(['message' => 'State deleted successfully']);
    }

    /**
     * Restore a soft-deleted state
     *
     * @urlParam id integer required The ID of the state. Example: 1
     *
     * @response {
     *   "message": "State restored successfully"
     * }
     */
    public function restoreState($id)
    {
        $state = State::withTrashed()->findOrFail($id);
        $state->restore();

        return response()->json(['message' => 'State restored successfully']);
    }

    /**
     * Get zellas by state
     *
     * @urlParam state_id integer required The ID of the state. Example: 1
     * @queryParam status string Filter by status (active/inactive). Example: active
     * @queryParam trashed boolean Include soft deleted records. Example: 1
     *
     * @response {
     *   "data": [
     *     {
     *       "id": 1,
     *       "state_id": 1,
     *       "name": "Dhaka City",
     *       "slug": "dhaka-city",
     *       "code": "DHK-C",
     *       "status": "active",
     *       "created_at": "2023-04-29T10:00:00.000000Z",
     *       "updated_at": "2023-04-29T10:00:00.000000Z"
     *     }
     *   ]
     * }
     */
    public function zellasByState($state_id, Request $request)
    {
        $query = Zella::where('state_id', $state_id);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('trashed') && $request->trashed) {
            $query->withTrashed();
        }

        return response()->json(['data' => $query->get()]);
    }

    /**
     * Get a specific zella
     *
     * @urlParam id integer required The ID of the zella. Example: 1
     *
     * @response {
     *   "data": {
     *     "id": 1,
     *     "state_id": 1,
     *     "name": "Dhaka City",
     *     "slug": "dhaka-city",
     *     "code": "DHK-C",
     *     "status": "active",
     *     "created_at": "2023-04-29T10:00:00.000000Z",
     *     "updated_at": "2023-04-29T10:00:00.000000Z",
     *     "state": {
     *       "id": 1,
     *       "name": "Dhaka",
     *       "country_id": 1,
     *       "country": {
     *         "id": 1,
     *         "name": "Bangladesh"
     *       }
     *     }
     *   }
     * }
     */
    public function zella($id)
    {
        $zella = Zella::with(['state' => function ($query) {
            $query->with('country:id,name');
        }])->findOrFail($id);

        return response()->json(['data' => $zella]);
    }

    /**
     * Create a new zella
     *
     * @bodyParam state_id integer required The ID of the state. Example: 1
     * @bodyParam name string required The name of the zella. Example: Dhaka City
     * @bodyParam slug string The slug for the zella (generated from name if not provided). Example: dhaka-city
     * @bodyParam code string The zella code. Example: DHK-C
     * @bodyParam status string The status (active/inactive). Example: active
     *
     * @response {
     *   "message": "Zella created successfully",
     *   "data": {
     *     "id": 1,
     *     "state_id": 1,
     *     "name": "Dhaka City",
     *     "slug": "dhaka-city",
     *     "code": "DHK-C",
     *     "status": "active",
     *     "created_at": "2023-04-29T10:00:00.000000Z",
     *     "updated_at": "2023-04-29T10:00:00.000000Z"
     *   }
     * }
     */
    public function storeZella(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'state_id' => 'required|exists:states,id',
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:zellas',
            'code' => 'nullable|string|max:10',
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $zella = Zella::create($request->all());

        return response()->json([
            'message' => 'Zella created successfully',
            'data' => $zella
        ], 201);
    }

    /**
     * Update a zella
     *
     * @urlParam id integer required The ID of the zella. Example: 1
     * @bodyParam state_id integer The ID of the state. Example: 1
     * @bodyParam name string The name of the zella. Example: Dhaka City
     * @bodyParam slug string The slug for the zella. Example: dhaka-city
     * @bodyParam code string The zella code. Example: DHK-C
     * @bodyParam status string The status (active/inactive). Example: active
     *
     * @response {
     *   "message": "Zella updated successfully",
     *   "data": {
     *     "id": 1,
     *     "state_id": 1,
     *     "name": "Dhaka City",
     *     "slug": "dhaka-city",
     *     "code": "DHK-C",
     *     "status": "active",
     *     "created_at": "2023-04-29T10:00:00.000000Z",
     *     "updated_at": "2023-04-29T10:00:00.000000Z"
     *   }
     * }
     */
    public function updateZella(Request $request, $id)
    {
        $zella = Zella::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'state_id' => 'exists:states,id',
            'name' => 'string|max:255',
            'slug' => 'nullable|string|max:255|unique:zellas,slug,' . $id,
            'code' => 'nullable|string|max:10',
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $zella->update($request->all());

        return response()->json([
            'message' => 'Zella updated successfully',
            'data' => $zella
        ]);
    }

    /**
     * Delete a zella (soft delete)
     *
     * @urlParam id integer required The ID of the zella. Example: 1
     *
     * @response {
     *   "message": "Zella deleted successfully"
     * }
     */
    public function deleteZella($id)
    {
        $zella = Zella::findOrFail($id);
        $zella->delete();

        return response()->json(['message' => 'Zella deleted successfully']);
    }

    /**
     * Restore a soft-deleted zella
     *
     * @urlParam id integer required The ID of the zella. Example: 1
     *
     * @response {
     *   "message": "Zella restored successfully"
     * }
     */
    public function restoreZella($id)
    {
        $zella = Zella::withTrashed()->findOrFail($id);
        $zella->restore();

        return response()->json(['message' => 'Zella restored successfully']);
    }

    /**
     * Get upazillas by zella
     *
     * @urlParam zella_id integer required The ID of the zella. Example: 1
     * @queryParam status string Filter by status (active/inactive). Example: active
     * @queryParam trashed boolean Include soft deleted records. Example: 1
     *
     * @response {
     *   "data": [
     *     {
     *       "id": 1,
     *       "zella_id": 1,
     *       "name": "Gulshan",
     *       "slug": "gulshan",
     *       "code": "GLS",
     *       "postal_code": "1212",
     *       "status": "active",
     *       "created_at": "2023-04-29T10:00:00.000000Z",
     *       "updated_at": "2023-04-29T10:00:00.000000Z"
     *     }
     *   ]
     * }
     */
    public function upazillasByZella($zella_id, Request $request)
    {
        $query = Upazilla::where('zella_id', $zella_id);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('trashed') && $request->trashed) {
            $query->withTrashed();
        }

        return response()->json(['data' => $query->get()]);
    }

    /**
     * Get a specific upazilla
     *
     * @urlParam id integer required The ID of the upazilla. Example: 1
     *
     * @response {
     *   "data": {
     *     "id": 1,
     *     "zella_id": 1,
     *     "name": "Gulshan",
     *     "slug": "gulshan",
     *     "code": "GLS",
     *     "postal_code": "1212",
     *     "status": "active",
     *     "created_at": "2023-04-29T10:00:00.000000Z",
     *     "updated_at": "2023-04-29T10:00:00.000000Z",
     *     "zella": {
     *       "id": 1,
     *       "name": "Dhaka City",
     *       "state_id": 1,
     *       "state": {
     *         "id": 1,
     *         "name": "Dhaka",
     *         "country_id": 1
     *       }
     *     }
     *   }
     * }
     */
    public function upazilla($id)
    {
        $upazilla = Upazilla::with(['zella' => function ($query) {
            $query->with('state:id,name,country_id');
        }])->findOrFail($id);

        return response()->json(['data' => $upazilla]);
    }

    /**
     * Create a new upazilla
     *
     * @bodyParam zella_id integer required The ID of the zella. Example: 1
     * @bodyParam name string required The name of the upazilla. Example: Gulshan
     * @bodyParam slug string The slug for the upazilla (generated from name if not provided). Example: gulshan
     * @bodyParam code string The upazilla code. Example: GLS
     * @bodyParam postal_code string The postal code. Example: 1212
     * @bodyParam status string The status (active/inactive). Example: active
     *
     * @response {
     *   "message": "Upazilla created successfully",
     *   "data": {
     *     "id": 1,
     *     "zella_id": 1,
     *     "name": "Gulshan",
     *     "slug": "gulshan",
     *     "code": "GLS",
     *     "postal_code": "1212",
     *     "status": "active",
     *     "created_at": "2023-04-29T10:00:00.000000Z",
     *     "updated_at": "2023-04-29T10:00:00.000000Z"
     *   }
     * }
     */
    public function storeUpazilla(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'zella_id' => 'required|exists:zellas,id',
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:upazillas',
            'code' => 'nullable|string|max:10',
            'postal_code' => 'nullable|string|max:20',
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $upazilla = Upazilla::create($request->all());

        return response()->json([
            'message' => 'Upazilla created successfully',
            'data' => $upazilla
        ], 201);
    }

    /**
     * Update an upazilla
     *
     * @urlParam id integer required The ID of the upazilla. Example: 1
     * @bodyParam zella_id integer The ID of the zella. Example: 1
     * @bodyParam name string The name of the upazilla. Example: Gulshan
     * @bodyParam slug string The slug for the upazilla. Example: gulshan
     * @bodyParam code string The upazilla code. Example: GLS
     * @bodyParam postal_code string The postal code. Example: 1212
     * @bodyParam status string The status (active/inactive). Example: active
     *
     * @response {
     *   "message": "Upazilla updated successfully",
     *   "data": {
     *     "id": 1,
     *     "zella_id": 1,
     *     "name": "Gulshan",
     *     "slug": "gulshan",
     *     "code": "GLS",
     *     "postal_code": "1212",
     *     "status": "active",
     *     "created_at": "2023-04-29T10:00:00.000000Z",
     *     "updated_at": "2023-04-29T10:00:00.000000Z"
     *   }
     * }
     */
    public function updateUpazilla(Request $request, $id)
    {
        $upazilla = Upazilla::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'zella_id' => 'exists:zellas,id',
            'name' => 'string|max:255',
            'slug' => 'nullable|string|max:255|unique:upazillas,slug,' . $id,
            'code' => 'nullable|string|max:10',
            'postal_code' => 'nullable|string|max:20',
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $upazilla->update($request->all());

        return response()->json([
            'message' => 'Upazilla updated successfully',
            'data' => $upazilla
        ]);
    }

    /**
     * Delete an upazilla (soft delete)
     *
     * @urlParam id integer required The ID of the upazilla. Example: 1
     *
     * @response {
     *   "message": "Upazilla deleted successfully"
     * }
     */
    public function deleteUpazilla($id)
    {
        $upazilla = Upazilla::findOrFail($id);
        $upazilla->delete();

        return response()->json(['message' => 'Upazilla deleted successfully']);
    }

    /**
     * Restore a soft-deleted upazilla
     *
     * @urlParam id integer required The ID of the upazilla. Example: 1
     *
     * @response {
     *   "message": "Upazilla restored successfully"
     * }
     */
    public function restoreUpazilla($id)
    {
        $upazilla = Upazilla::withTrashed()->findOrFail($id);
        $upazilla->restore();

        return response()->json(['message' => 'Upazilla restored successfully']);
    }

    /**
     * Get hierarchical location data
     *
     * @queryParam status string Filter by status (active/inactive). Example: active
     *
     * @response {
     *   "data": [
     *     {
     *       "id": 1,
     *       "name": "Bangladesh",
     *       "slug": "bangladesh",
     *       "code": "BD",
     *       "status": "active",
     *       "states": [
     *         {
     *           "id": 1,
     *           "name": "Dhaka",
     *           "slug": "dhaka",
     *           "code": "DHK",
     *           "status": "active",
     *           "zellas": [
     *             {
     *               "id": 1,
     *               "name": "Dhaka",
     *               "slug": "dhaka-city",
     *               "code": "DHK-C",
     *               "status": "active",
     *               "upazillas": [
     *                 {
     *                   "id": 1,
     *                   "name": "Gulshan",
     *                   "slug": "gulshan",
     *                   "code": "GLS",
     *                   "postal_code": "1212",
     *                   "status": "active"
     *                 }
     *               ]
     *             }
     *           ]
     *         }
     *       ]
     *     }
     *   ]
     * }
     */
    public function hierarchy(Request $request)
    {
        $query = Country::query();

        if ($request->has('status')) {
            $status = $request->status;
            $query->where('status', $status)
                ->with(['states' => function ($q) use ($status) {
                    $q->where('status', $status)
                        ->with(['zellas' => function ($q) use ($status) {
                            $q->where('status', $status)
                                ->with(['upazillas' => function ($q) use ($status) {
                                    $q->where('status', $status);
                                }]);
                        }]);
                }]);
        } else {
            $query->with(['states.zellas.upazillas']);
        }

        return response()->json(['data' => $query->get()]);
    }

 /**
     * Get top 8 destinations
     *
     * @queryParam status string Filter by status (active/inactive). Example: active
     *
     * @response {
     *   "data": [
     *     {
     *       "id": 1,
     *       "name": "Cox's Bazar",
     *       "slug": "coxs-bazar",
     *       "description": "World's longest sea beach",
     *       "short_description": "Beautiful beach destination",
     *       "image": "https://example.com/coxs-bazar.jpg",
     *       "status": "active",
     *       "tours_count": 15,
     *       "average_price": 12500,
     *       "created_at": "2023-04-29T10:00:00.000000Z"
     *     }
     *   ]
     * }
     */
    public function topDestinations(Request $request)
    {
        $statusFilter = $request->get('status', 'active');
        $destinations = collect();
    
        // Step 1: Get states marked as top destinations by admin
        $topDestinations = State::where('is_top_destination', true)
            ->where('status', $statusFilter)
            ->withCount(['tourPackagesFrom as tour_packages_count'])
            ->get();
    
        $destinations = $destinations->merge($topDestinations);
    
        // Step 2: If we don't have 8 destinations, get states with most tour packages
        if ($destinations->count() < 8) {
            $needed = 8 - $destinations->count();
            $excludeIds = $destinations->pluck('id')->toArray();
    
            $popularDestinations = State::where('status', $statusFilter)
                ->whereNotIn('id', $excludeIds)
                ->withCount(['tourPackagesFrom as tour_packages_count'])
                ->having('tour_packages_count', '>', 0)
                ->orderBy('tour_packages_count', 'desc')
                ->limit($needed)
                ->get();
    
            $destinations = $destinations->merge($popularDestinations);
        }
    
        // Step 3: If still not 8, add random states
        if ($destinations->count() < 8) {
            $needed = 8 - $destinations->count();
            $excludeIds = $destinations->pluck('id')->toArray();
    
            $randomDestinations = State::where('status', $statusFilter)
                ->whereNotIn('id', $excludeIds)
                ->withCount(['tourPackagesFrom as tour_packages_count'])
                ->inRandomOrder()
                ->limit($needed)
                ->get();
    
            $destinations = $destinations->merge($randomDestinations);
        }
    
        // Add calculated fields for each destination
        $destinations->each(function ($destination) {
            // Calculate average price from tour packages
            $tourPackages = \App\Models\TourPackage::where('from_state_id', $destination->id)
                ->where('status', 'active');
            
            $destination->average_price = $tourPackages->avg('base_price_adult') ?? 0;
            $destination->tours_count = $tourPackages->count();
    
            // Add featured image URL using the direct image field
            $destination->featured_image = $destination->image ? asset('storage/' . $destination->image) : null;
            
            // Add short description from description field
            $destination->short_description = $destination->getDescriptionExcerpt(100);
        });
    
        return response()->json(['data' => $destinations->take(8)->values()]);
    }
}
