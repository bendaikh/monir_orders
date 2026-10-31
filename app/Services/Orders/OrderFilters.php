<?php

namespace App\Services\Orders;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class OrderFilters
{
    public function apply(Builder $query, Request $request): Builder
    {
        $reference = $this->term($request, 'reference');
        $customer = $this->term($request, 'customer');
        $phone = $this->term($request, 'phone');
        $city = $this->term($request, 'city');

        if ($reference !== null) {
            $query->where('reference', 'like', '%'.$reference.'%');
        }

        if ($customer !== null) {
            $query->where('customer_name', 'like', '%'.$customer.'%');
        }

        if ($phone !== null) {
            $query->where('phone', 'like', '%'.$phone.'%');
        }

        if ($city !== null) {
            $query->where('city', 'like', '%'.$city.'%');
        }

        if ($request->filled('service_id')) {
            $query->where('service_id', $request->integer('service_id'));
        }

        if ($request->filled('status')) {
            $query->withDisplayStatus($request->string('status')->toString());
        }

        if ($request->filled('date_from')) {
            $query->whereDate('received_at', '>=', $request->date('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('received_at', '<=', $request->date('date_to'));
        }

        if ($request->filled('country')) {
            $code = strtoupper($request->string('country')->toString());
            $query->whereHas('service.country', fn (Builder $country) => $country->where('code', $code));
        }

        return $query;
    }

    private function term(Request $request, string $key): ?string
    {
        $value = trim($request->string($key)->toString());
        $value = str_replace(['%', '_'], '', $value);

        return $value === '' ? null : $value;
    }
}
