<?php

namespace App\Http\Controllers;

use App\Support\SalesChatbot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatbotController extends Controller
{
    public function reply(Request $request, SalesChatbot $chatbot): JsonResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:500'],
        ]);

        return response()->json($chatbot->reply($validated['message']));
    }
}