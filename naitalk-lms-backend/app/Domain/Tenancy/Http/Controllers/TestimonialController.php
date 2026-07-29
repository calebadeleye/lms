<?php

namespace App\Domain\Tenancy\Http\Controllers;

use App\Domain\Tenancy\Models\TenantTestimonial;
use App\Domain\Tenancy\Services\TenantContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class TestimonialController extends Controller
{
    public function __construct(private TenantContext $tenantContext) {}

    public function index()
    {
        return response()->json(['data' => TenantTestimonial::orderBy('created_at')->get()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'quote' => ['required', 'string', 'max:1000'],
            'author' => ['required', 'string', 'max:255'],
        ]);

        $testimonial = TenantTestimonial::create($data);

        Cache::forget("tenant:{$this->tenantContext->id()}:public-config");

        return response()->json(['data' => $testimonial], 201);
    }

    public function destroy(string $testimonialId)
    {
        TenantTestimonial::findOrFail($testimonialId)->delete();

        Cache::forget("tenant:{$this->tenantContext->id()}:public-config");

        return response()->json(['data' => ['success' => true]]);
    }
}
