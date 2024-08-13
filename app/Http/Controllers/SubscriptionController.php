<?php

namespace App\Http\Controllers;

use App\Models\Subscription;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function index()
    {
        return Subscription::all();
    }

    public function store(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'plan' => 'required|string|max:255',
        ]);

        $subscription = Subscription::create($request->all());

        return response()->json($subscription, 201);
    }

    public function show(Subscription $subscription)
    {
        return $subscription;
    }

    public function update(Request $request, Subscription $subscription)
    {
        $request->validate([
            'plan' => 'sometimes|required|string|max:255',
        ]);

        $subscription->update($request->only('plan'));

        return response()->json($subscription);
    }

    public function destroy(Subscription $subscription)
    {
        $subscription->delete();

        return response()->json(null, 204);
    }
}
