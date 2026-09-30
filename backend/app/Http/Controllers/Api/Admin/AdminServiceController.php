<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ServiceRequest;
use App\Http\Resources\AdminServiceResource;
use App\Models\Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AdminServiceController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return AdminServiceResource::collection(Service::orderBy('name')->get());
    }

    public function store(ServiceRequest $request): JsonResponse
    {
        $service = Service::create($request->validated());

        return (new AdminServiceResource($service->fresh()))
            ->response()
            ->setStatusCode(201);
    }

    public function update(ServiceRequest $request, Service $service): AdminServiceResource
    {
        $service->update($request->validated());

        return new AdminServiceResource($service->fresh());
    }
}