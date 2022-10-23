<?php

namespace App\Http\Controllers;

use App\Exceptions\ChatException;
use App\Models\Chat;

class ChatController extends Controller
{
    /**
     * Store chat into database
     *
     * @param int $chatId
     * @return bool
     * @throws ChatException
     */
    public static function store(int $chatId): bool
    {
        if (Chat::where('chat_id', $chatId)->exists()) {
            throw new ChatException('Chat already started!');
        }

        $chat = new Chat();
        $chat->chat_id = $chatId;
        $chat->save();

        return true;
    }
}
