<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class WeatherController extends Controller
{
    public function show(Request $request)
    {
        $city = $request->query('city');

        // cityパラメータがない場合
        if (!$city) {
            return response()->json([
                'error' => 'city parameter is required',
            ], 400);
        }

        // cityが数字以外の場合
        if (!ctype_digit($city)) {
            return response()->json([
                'error' => 'city must be a numeric ID (e.g. 130010)',
            ], 400);
        }

        // 外部APIへのリクエスト
        try {
            $response = Http::timeout(5)->get('https://weather.tsukumijima.net/api/forecast', [
                'city' => $city,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to connect to weather API',
            ], 503);
        }

        // APIがエラーを返した場合
        if ($response->failed()) {
            return response()->json([
                'error' => 'City not found or weather API error',
            ], $response->status());
        }

        $data = $response->json();

        // レスポンスの構造が想定外の場合
        if (empty($data['forecasts']) || empty($data['location'])) {
            return response()->json([
                'error' => 'Unexpected response from weather API',
            ], 500);
        }

        // 現在時刻から降水確率の時間帯を選ぶ
        $hour = (int) now()->format('H');
        if ($hour < 6) {
            $rainKey = 'T00_06';
        } elseif ($hour < 12) {
            $rainKey = 'T06_12';
        } elseif ($hour < 18) {
            $rainKey = 'T12_18';
        } else {
            $rainKey = 'T18_24';
        }

        // 今日の予報が取れない場合
        $today = collect($data['forecasts'])->first(function ($forecast) {
            return $forecast['dateLabel'] === '今日';
        });

        if (!$today) {
            return response()->json([
                'error' => 'Today forecast is not available',
            ], 404);
        }

        return response()->json([
            'location' => [
                'prefecture' => $data['location']['prefecture'],
                'city'       => $data['location']['city'],
            ],
            'published_time' => $data['publicTimeFormatted'],
            'forecast' => [
                'date'      => $today['date'],
                'condition' => $today['telop'],
                'temperature' => [
                    'max' => $today['temperature']['max']['celsius'] ?? null,
                    'min' => $today['temperature']['min']['celsius'] ?? null,
                ],
            ],
        ]);
    }
}