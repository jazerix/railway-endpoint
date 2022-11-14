<?php

namespace App\Http\Controllers;

use App\Models\Joint;
use Illuminate\Http\Request;
use League\Csv\Reader;
use League\Csv\Statement;
use Location\Coordinate;
use Location\Distance\Vincenty;
use MatanYadaev\EloquentSpatial\Objects\Point;

class JointController extends Controller
{
    public function index()
    {
        \request()->validate([
            'take' => 'numeric|max:250'
        ]);
        $kilometerLimit = 15_000; // 15 km
        $origin = new Point(\request('lat'), \request('long'), 4326);
        $joints = Joint::query()
            ->whereDistance('coordinates', $origin, '<', $kilometerLimit)
            ->withDistance('coordinates', $origin)
            ->orderByDistance('coordinates', $origin)
            ->take(\request('take', 250))
            ->get();

        return $joints;
    }
}
