<?php

namespace App\Http\Controllers;

use App\Exceptions\AccountException;
use App\Models\Account;

class AccountController extends Controller
{
    /**
     * Store account into database
     *
     * @param int $chatId
     * @param string $accountName
     * @param string|null $description
     * @return bool
     * @throws AccountException
     */
    public static function store(int $chatId, string $accountName, string $description = null): bool
    {
        if (Account::where('name', $accountName)->exists()) {
            throw new AccountException('Account already exists!');
        }

        $account = new Account();
        $account->name = $accountName;
        $account->chat_id = $chatId;
        $account->description = $description;
        $account->balance = 0;
        $account->save();

        return true;
    }
}
