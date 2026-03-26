<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class WeatherController extends Controller
{
    // GET /api/weather?city=130010
    public function show(Request $request)
    {
        $city = $request->query('city');
        return $this->fetchWeather($city);
    }

    // POST /api/weather
    // body: { "city": "130010" }
    public function store(Request $request)
    {
        $city = $request->input('city');
        return $this->fetchWeather($city);
    }

    private function fetchWeather($city)
    {
        if (!$city) {
            return response()->json(['error' => 'city is required'], 400);
        }

        if (!ctype_digit($city)) {
            return response()->json(['error' => 'city must be a numeric ID (e.g. 130010)'], 400);
        }

        try {
            $response = Http::timeout(5)->get('https://weather.tsukumijima.net/api/forecast', [
                'city' => $city,
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to connect to weather API'], 503);
        }

        if ($response->failed()) {
            return response()->json(['error' => 'City not found or weather API error'], $response->status());
        }

        $data = $response->json();

        if (empty($data['forecasts']) || empty($data['location'])) {
            return response()->json(['error' => 'Unexpected response from weather API'], 500);
        }

        $today = collect($data['forecasts'])->first(function ($forecast) {
            return $forecast['dateLabel'] === '今日';
        });

        if (!$today) {
            return response()->json(['error' => 'Today forecast is not available'], 404);
        }

        return response()->json([
            'city'         => $data['location']['city'],
            'published_at' => $data['publicTimeFormatted'],
            'forecast' => [
                'date'        => $today['date'],
                'condition'   => $today['telop'],
                'temperature' => [
                    'max' => $today['temperature']['max']['celsius'] ?? null,
                    'min' => $today['temperature']['min']['celsius'] ?? null,
                ],
            ],
        ]);
    }
}
