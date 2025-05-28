<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class GoogleController extends Controller
{
    public function redirectToGoogle()
    {
        $url = 'https://accounts.google.com/o/oauth2/v2/auth?response_type=code&client_id=' . env('GOOGLE_CLIENT_ID') . '&redirect_uri=' . env('GOOGLE_REDIRECT_URI') . '&scope=https://www.googleapis.com/auth/webmasters.readonly&access_type=offline';

        return redirect($url);
    }

    public function handleGoogleCallback(Request $request)
    {
        $code = $request->get('code');

        // درخواست برای گرفتن توکن دسترسی
        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'code' => $code,
            'client_id' => env('GOOGLE_CLIENT_ID'),
            'client_secret' => env('GOOGLE_CLIENT_SECRET'),
            'redirect_uri' => env('GOOGLE_REDIRECT_URI'),
            'grant_type' => 'authorization_code',
        ]);

        $tokens = $response->json();

        // ذخیره توکن‌ها در session یا دیتابیس
        session(['google_access_token' => $tokens['access_token']]);
        session(['google_refresh_token' => $tokens['refresh_token']]);

        return redirect('/dashboard'); // یا هر مسیری که نیاز دارید
    }

    public function fetchSearchData(Request $request)
    {
        $page = $request->input('page');
        $accessToken = session('google_access_token');

        $response = Http::withToken($accessToken)
            ->post('https://www.googleapis.com/webmasters/v3/sites/https%3A%2F%2Fwww.technolife.com%2F/searchAnalytics/query', [
                'startDate' => '2025-05-01',
                'endDate' => '2025-05-20',
                'dimensions' => ['query'],
                'dimensionFilterGroups' => [
                    [
                        'filters' => [
                            [
                                'dimension' => 'page',
                                'operator' => 'contains',
                                'expression' => $page
                            ]
                        ]
                    ]
                ],
                'rowLimit' => 10
            ]);

        return response()->json(
            collect($response['rows'])->map(function ($item) {
                return [
                    'key' => $item['keys'][0] ?? null,
                    'impressions' => $item['impressions'],
                    'ctr' => $item['ctr'],
                    'clicks' => $item['clicks'],
                    'position' => $item['position'],
                ];
            })
        );



    }

    public function refreshAccessToken()
    {
        $refreshToken = session('google_refresh_token');

        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'client_id' => env('GOOGLE_CLIENT_ID'),
            'client_secret' => env('GOOGLE_CLIENT_SECRET'),
            'refresh_token' => $refreshToken,
            'grant_type' => 'refresh_token',
        ]);

        $tokens = $response->json();
        session(['google_access_token' => $tokens['access_token']]);

        return $tokens;
    }

}

