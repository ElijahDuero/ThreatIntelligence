<?php

namespace App\Http\Controllers;

use App\Services\DarkWeb\TorClient;
use Illuminate\Http\JsonResponse;

class TorController extends Controller
{
    public function status(TorClient $torClient): JsonResponse
    {
        return response()->json($torClient->checkTorStatus());
    }

    public function start(TorClient $torClient): JsonResponse
    {
        return response()->json($torClient->startTor());
    }

    public function stop(TorClient $torClient): JsonResponse
    {
        return response()->json($torClient->stopTor());
    }
}
